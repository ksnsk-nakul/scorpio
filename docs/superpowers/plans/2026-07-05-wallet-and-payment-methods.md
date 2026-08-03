# Wallet & Payment Methods Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Add a digital wallet where anyone (unauthenticated) can top-up a user's wallet via a public pay-to-wallet link, plus saved payment methods (encrypted UPI IDs, Razorpay card tokens), with a full transaction audit log that tracks payer, recipient, amount, purpose, and category — searchable by transaction reference, email, or date.

**Architecture:** Every wallet movement is recorded as a `WalletTransaction` with a unique human-readable reference (e.g. `TXN-20260705-A3F9`), payer info (name + encrypted email), recipient (`user_id`), category, and optional note. The public top-up page lives at `/pay/{username}/wallet` — no login required; payers supply their name, email, amount, and purpose, then pay via Razorpay. HMAC signature is verified server-side before crediting. The wallet owner's balance is stored as an integer (paise) on `users` to avoid float drift; all credit/debit operations use `DB::transaction` + `lockForUpdate` to prevent race-condition double-spend. Payment methods store only tokens — UPI IDs encrypted, card data only on Razorpay.

**Tech Stack:** Laravel 13, PHP 8.4, Inertia.js v2, Vue 3, Tailwind CSS v4, Razorpay PHP SDK

---

## File Map

| File | Action | Responsibility |
|------|--------|----------------|
| `database/migrations/2026_07_05_100001_add_wallet_and_customer_to_users.php` | Create | `wallet_balance_paise` (int) + `razorpay_customer_id` (nullable) on users |
| `database/migrations/2026_07_05_100002_create_wallet_transactions_table.php` | Create | Full audit log — payer, recipient, amount, category, ref, note, metadata |
| `database/migrations/2026_07_05_100003_create_payment_methods_table.php` | Create | Saved UPI IDs (encrypted) and Razorpay card tokens per user |
| `app/Models/WalletTransaction.php` | Create | Eloquent model; generates `txn_ref`; belongs to User (recipient) |
| `app/Models/PaymentMethod.php` | Create | Eloquent model; encrypted `upi_id`; belongs to User |
| `app/Models/User.php` | Modify | `creditWallet()`, `debitWallet()`, `walletTransactions()`, `paymentMethods()` |
| `app/Http/Controllers/WalletTopUpController.php` | Create | **Public, unauthenticated.** Show pay-to-wallet page, create Razorpay order, verify + credit |
| `app/Http/Controllers/Admin/WalletController.php` | Create | Authenticated wallet dashboard: balance, transaction history, search |
| `app/Http/Controllers/Admin/PaymentMethodController.php` | Create | Authenticated: list, add UPI, add card token, set default, delete |
| `app/Http/Controllers/Admin/BillingController.php` | Modify | Pass `customer_id` to Razorpay; add `payWithWallet`; share `walletBalance` |
| `routes/web.php` | Modify | Public pay-to-wallet routes + authenticated wallet/payment-methods routes |
| `resources/js/Pages/WalletTopUp.vue` | Create | **Public page** — payer form (name/email/amount/note), Razorpay button |
| `resources/js/Pages/Admin/Billing/Index.vue` | Modify | Wallet balance widget + "Pay with Wallet" button on plan cards |
| `resources/js/Pages/Admin/Billing/Wallet.vue` | Create | Authenticated wallet: balance, top-up link, searchable transaction history |
| `resources/js/Pages/Admin/Billing/PaymentMethods.vue` | Create | List saved methods, add UPI form, set default, delete |
| `resources/js/Layouts/AdminLayout.vue` | Modify | Add Wallet + Payment Methods nav links |
| `app/Http/Middleware/HandleInertiaRequests.php` | Modify | Share `wallet_balance` globally |

---

## Task 1: Migrations

**Files:**
- Create: `database/migrations/2026_07_05_100001_add_wallet_and_customer_to_users.php`
- Create: `database/migrations/2026_07_05_100002_create_wallet_transactions_table.php`
- Create: `database/migrations/2026_07_05_100003_create_payment_methods_table.php`

- [ ] **Step 1: Create users wallet columns migration**

```php
<?php
// database/migrations/2026_07_05_100001_add_wallet_and_customer_to_users.php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->unsignedBigInteger('wallet_balance_paise')->default(0)->after('plan');
            $table->string('razorpay_customer_id')->nullable()->after('wallet_balance_paise');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['wallet_balance_paise', 'razorpay_customer_id']);
        });
    }
};
```

- [ ] **Step 2: Create wallet_transactions migration**

This table is the full audit log. Every row represents one money movement.

```php
<?php
// database/migrations/2026_07_05_100002_create_wallet_transactions_table.php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('wallet_transactions', function (Blueprint $table) {
            $table->id();

            // Unique human-readable reference, e.g. TXN-20260705-A3F9K2
            $table->string('txn_ref', 30)->unique();

            // Who received the money (always a platform user)
            $table->foreignId('recipient_user_id')->constrained('users')->cascadeOnDelete();

            // Payer info — nullable for system-generated entries (subscription debits, refunds)
            $table->string('payer_name')->nullable();
            $table->text('payer_email')->nullable(); // encrypted via cast

            // Movement direction and amount
            $table->enum('type', ['credit', 'debit']);
            $table->unsignedBigInteger('amount_paise');
            $table->unsignedBigInteger('balance_after_paise');

            // Categorisation
            $table->enum('category', [
                'topup',          // someone added money via pay-to-wallet link
                'subscription',   // wallet used to pay a subscription
                'refund',         // admin-issued refund back to wallet
                'adjustment',     // manual admin adjustment
            ])->default('topup');

            // Human-readable purpose/note written by the payer or admin
            $table->string('note')->nullable();

            // Razorpay / external reference (payment_id, order_id, etc.)
            $table->string('razorpay_payment_id')->nullable();
            $table->string('razorpay_order_id')->nullable();

            // Extra supporting details (JSON blob for extensibility)
            $table->json('meta')->nullable();

            $table->timestamps();

            // Indexes for search
            $table->index('txn_ref');
            $table->index('recipient_user_id');
            $table->index('payer_email');
            $table->index('created_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('wallet_transactions');
    }
};
```

- [ ] **Step 3: Create payment_methods migration**

```php
<?php
// database/migrations/2026_07_05_100003_create_payment_methods_table.php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('payment_methods', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->enum('type', ['upi', 'card']);
            $table->string('label');               // display: "upi@paytm" or "Visa •••• 4242"
            $table->text('upi_id')->nullable();    // encrypted UPI VPA
            $table->string('razorpay_token_id')->nullable();
            $table->boolean('is_default')->default(false);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payment_methods');
    }
};
```

- [ ] **Step 4: Run migrations**

```bash
php artisan migrate
```

Expected: Three new migration steps complete, no errors.

- [ ] **Step 5: Commit**

```bash
git add database/migrations/2026_07_05_100001_add_wallet_and_customer_to_users.php \
        database/migrations/2026_07_05_100002_create_wallet_transactions_table.php \
        database/migrations/2026_07_05_100003_create_payment_methods_table.php
git commit -m "feat: wallet_balance on users, wallet_transactions audit log, payment_methods tables"
```

---

## Task 2: Models + User wallet methods

**Files:**
- Create: `app/Models/WalletTransaction.php`
- Create: `app/Models/PaymentMethod.php`
- Modify: `app/Models/User.php`

- [ ] **Step 1: Create WalletTransaction model**

```php
<?php
// app/Models/WalletTransaction.php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

class WalletTransaction extends Model
{
    protected $fillable = [
        'txn_ref', 'recipient_user_id',
        'payer_name', 'payer_email',
        'type', 'amount_paise', 'balance_after_paise',
        'category', 'note',
        'razorpay_payment_id', 'razorpay_order_id',
        'meta',
    ];

    protected $casts = [
        'payer_email' => 'encrypted',
        'meta'        => 'array',
    ];

    protected $hidden = ['payer_email']; // never expose encrypted email to frontend

    /** Generate a unique TXN-YYYYMMDD-XXXXXX reference. */
    public static function generateRef(): string
    {
        do {
            $ref = 'TXN-' . now()->format('Ymd') . '-' . strtoupper(Str::random(6));
        } while (static::where('txn_ref', $ref)->exists());

        return $ref;
    }

    public function recipient(): BelongsTo
    {
        return $this->belongsTo(User::class, 'recipient_user_id');
    }
}
```

- [ ] **Step 2: Create PaymentMethod model**

```php
<?php
// app/Models/PaymentMethod.php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PaymentMethod extends Model
{
    protected $fillable = [
        'user_id', 'type', 'label', 'upi_id', 'razorpay_token_id', 'is_default',
    ];

    protected $casts = [
        'upi_id'     => 'encrypted',
        'is_default' => 'boolean',
    ];

    protected $hidden = ['upi_id'];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
```

- [ ] **Step 3: Add wallet methods to User model**

Add the following to `app/Models/User.php` after the `activeSubscription()` relation and update `$fillable` and `$casts`:

**Update `$fillable`** (add two new fields):
```php
protected $fillable = [
    'name', 'email', 'password', 'avatar',
    'username', 'github_token',
    'site_name', 'og_image', 'custom_domain', 'plan',
    'google_id', 'github_id', 'email_verified_at',
    'wallet_balance_paise', 'razorpay_customer_id',
];
```

**Update `$casts`** (add encrypted customer id):
```php
protected $casts = [
    'password'             => 'hashed',
    'github_token'         => 'encrypted',
    'razorpay_customer_id' => 'encrypted',
];
```

**Add relations and wallet methods** after `activeSubscription()`:
```php
public function walletTransactions(): \Illuminate\Database\Eloquent\Relations\HasMany
{
    return $this->hasMany(WalletTransaction::class, 'recipient_user_id')->latest();
}

public function paymentMethods(): \Illuminate\Database\Eloquent\Relations\HasMany
{
    return $this->hasMany(PaymentMethod::class)->latest();
}

public function defaultPaymentMethod(): \Illuminate\Database\Eloquent\Relations\HasOne
{
    return $this->hasOne(PaymentMethod::class)->where('is_default', true);
}

public function walletBalanceRupees(): float
{
    return $this->wallet_balance_paise / 100;
}

/**
 * Credit wallet atomically. Must be called inside DB::transaction.
 * Returns updated balance in paise.
 */
public function creditWallet(
    int $amountPaise,
    string $category,
    string $note,
    ?string $payerName = null,
    ?string $payerEmail = null,
    ?string $razorpayPaymentId = null,
    ?string $razorpayOrderId = null,
    ?array $meta = null
): int {
    $this->increment('wallet_balance_paise', $amountPaise);
    $this->refresh();

    WalletTransaction::create([
        'txn_ref'              => WalletTransaction::generateRef(),
        'recipient_user_id'    => $this->id,
        'payer_name'           => $payerName,
        'payer_email'          => $payerEmail,
        'type'                 => 'credit',
        'amount_paise'         => $amountPaise,
        'balance_after_paise'  => $this->wallet_balance_paise,
        'category'             => $category,
        'note'                 => $note,
        'razorpay_payment_id'  => $razorpayPaymentId,
        'razorpay_order_id'    => $razorpayOrderId,
        'meta'                 => $meta,
    ]);

    return $this->wallet_balance_paise;
}

/**
 * Debit wallet atomically. Throws RuntimeException if insufficient balance.
 * Must be called inside DB::transaction.
 */
public function debitWallet(
    int $amountPaise,
    string $category,
    string $note,
    ?string $reference = null
): int {
    // Row-level lock prevents race conditions
    $fresh = static::lockForUpdate()->findOrFail($this->id);

    if ($fresh->wallet_balance_paise < $amountPaise) {
        throw new \RuntimeException('Insufficient wallet balance.');
    }

    $fresh->decrement('wallet_balance_paise', $amountPaise);
    $fresh->refresh();

    WalletTransaction::create([
        'txn_ref'             => WalletTransaction::generateRef(),
        'recipient_user_id'   => $this->id,
        'type'                => 'debit',
        'amount_paise'        => $amountPaise,
        'balance_after_paise' => $fresh->wallet_balance_paise,
        'category'            => $category,
        'note'                => $note,
        'razorpay_payment_id' => $reference,
    ]);

    $this->wallet_balance_paise = $fresh->wallet_balance_paise;
    return $fresh->wallet_balance_paise;
}
```

- [ ] **Step 4: Verify models**

```bash
php artisan tinker --execute="
echo App\Models\WalletTransaction::count() . PHP_EOL;
echo App\Models\PaymentMethod::count() . PHP_EOL;
\$u = App\Models\User::first();
echo \$u->wallet_balance_paise . PHP_EOL;
"
```

Expected: `0`, `0`, `0`

- [ ] **Step 5: Commit**

```bash
git add app/Models/WalletTransaction.php app/Models/PaymentMethod.php app/Models/User.php
git commit -m "feat: WalletTransaction + PaymentMethod models; User creditWallet/debitWallet with lock + audit"
```

---

## Task 3: Routes

**Files:**
- Modify: `routes/web.php`

- [ ] **Step 1: Add public pay-to-wallet routes**

Add before the `// Admin portfolio inner pages at root` comment:

```php
// Public pay-to-wallet (no auth required)
use App\Http\Controllers\WalletTopUpController;

Route::get('/pay/{username}/wallet',        [WalletTopUpController::class, 'show'])->name('wallet.pay.show');
Route::post('/pay/{username}/wallet/order', [WalletTopUpController::class, 'createOrder'])->name('wallet.pay.order')->middleware('throttle:10,1');
Route::post('/pay/{username}/wallet/verify',[WalletTopUpController::class, 'verify'])->name('wallet.pay.verify')->middleware('throttle:10,1');
Route::get('/pay/{username}/wallet/done',   [WalletTopUpController::class, 'done'])->name('wallet.pay.done');
```

- [ ] **Step 2: Add authenticated wallet + payment method routes**

Add after the existing billing routes block:

```php
use App\Http\Controllers\Admin\WalletController;
use App\Http\Controllers\Admin\PaymentMethodController;

Route::middleware(['auth'])->prefix('admin')->name('admin.')->group(function () {
    // Authenticated wallet dashboard (view own balance + transactions)
    Route::get('wallet',               [WalletController::class, 'index'])->name('wallet.index');

    // Pay a subscription from wallet
    Route::post('billing/pay-wallet',  [\App\Http\Controllers\Admin\BillingController::class, 'payWithWallet'])->name('billing.pay-wallet');

    // Payment methods
    Route::get('payment-methods',                    [PaymentMethodController::class, 'index'])->name('payment-methods.index');
    Route::post('payment-methods/upi',               [PaymentMethodController::class, 'storeUpi'])->name('payment-methods.store-upi');
    Route::post('payment-methods/card',              [PaymentMethodController::class, 'storeCard'])->name('payment-methods.store-card');
    Route::patch('payment-methods/{method}/default', [PaymentMethodController::class, 'setDefault'])->name('payment-methods.default');
    Route::delete('payment-methods/{method}',        [PaymentMethodController::class, 'destroy'])->name('payment-methods.destroy');
});
```

- [ ] **Step 3: Verify routes**

```bash
php artisan route:list --path=pay
php artisan route:list --path=admin/wallet
php artisan route:list --path=admin/payment-methods
```

Expected: All routes appear with correct methods and names.

- [ ] **Step 4: Commit**

```bash
git add routes/web.php
git commit -m "feat: public pay-to-wallet routes + authenticated wallet/payment-methods routes"
```

---

## Task 4: WalletTopUpController (public, no auth)

**Files:**
- Create: `app/Http/Controllers/WalletTopUpController.php`

- [ ] **Step 1: Create controller**

```php
<?php
// app/Http/Controllers/WalletTopUpController.php
namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;
use Razorpay\Api\Api;

class WalletTopUpController extends Controller
{
    private const MIN_PAISE = 10000;   // ₹100
    private const MAX_PAISE = 1000000; // ₹10,000

    private function razorpay(): Api
    {
        $key    = config('billing.razorpay.key_id');
        $secret = config('billing.razorpay.key_secret');
        abort_if(blank($key) || blank($secret), 503, 'Payment gateway not configured.');
        return new Api($key, $secret);
    }

    /** Public pay-to-wallet landing page — anyone can access */
    public function show(string $username): Response
    {
        $user = User::where('username', $username)
            ->select(['id', 'name', 'username', 'site_name'])
            ->firstOrFail();

        return Inertia::render('WalletTopUp', [
            'recipient'  => $user->only('name', 'username', 'site_name'),
            'razorpayKey'=> config('billing.razorpay.key_id'),
        ]);
    }

    /** Create a Razorpay order for the top-up amount */
    public function createOrder(string $username, Request $request): JsonResponse
    {
        $user = User::where('username', $username)->select(['id', 'name', 'email'])->firstOrFail();

        $data = $request->validate([
            'amount_paise' => ['required', 'integer', 'min:' . self::MIN_PAISE, 'max:' . self::MAX_PAISE],
            'payer_name'   => ['required', 'string', 'max:100'],
            'payer_email'  => ['required', 'email', 'max:255'],
            'note'         => ['nullable', 'string', 'max:255'],
        ]);

        $order = $this->razorpay()->order->create([
            'amount'   => $data['amount_paise'],
            'currency' => 'INR',
            'notes'    => [
                'purpose'          => 'wallet_topup',
                'recipient_user_id'=> (string) $user->id,
                'recipient_name'   => $user->name,
                'payer_name'       => $data['payer_name'],
            ],
        ]);

        // Stash payer details in session for verify step
        session(['wallet_topup' => [
            'order_id'         => $order->id,
            'recipient_user_id'=> $user->id,
            'amount_paise'     => $data['amount_paise'],
            'payer_name'       => $data['payer_name'],
            'payer_email'      => $data['payer_email'],
            'note'             => $data['note'] ?? null,
        ]]);

        return response()->json([
            'order_id'      => $order->id,
            'amount'        => $data['amount_paise'],
            'key'           => config('billing.razorpay.key_id'),
            'payer_name'    => $data['payer_name'],
            'payer_email'   => $data['payer_email'],
        ]);
    }

    /** Verify HMAC and credit the wallet */
    public function verify(string $username, Request $request): RedirectResponse
    {
        $request->validate([
            'razorpay_order_id'   => 'required|string',
            'razorpay_payment_id' => 'required|string',
            'razorpay_signature'  => 'required|string',
        ]);

        // HMAC verification before any DB writes
        $expected = hash_hmac(
            'sha256',
            $request->razorpay_order_id . '|' . $request->razorpay_payment_id,
            config('billing.razorpay.key_secret')
        );

        abort_unless(hash_equals($expected, $request->razorpay_signature), 422);

        $pending = session('wallet_topup');

        abort_if(
            ! $pending || $pending['order_id'] !== $request->razorpay_order_id,
            422,
            'Session mismatch. Please try again.'
        );

        $user = User::findOrFail($pending['recipient_user_id']);

        DB::transaction(function () use ($user, $pending, $request) {
            $user->creditWallet(
                (int) $pending['amount_paise'],
                'topup',
                $pending['note'] ?? 'Wallet top-up',
                $pending['payer_name'],
                $pending['payer_email'],
                $request->razorpay_payment_id,
                $request->razorpay_order_id,
                ['source' => 'public_topup_link']
            );
        });

        $txnRef = \App\Models\WalletTransaction::where('razorpay_payment_id', $request->razorpay_payment_id)
            ->value('txn_ref');

        session()->forget('wallet_topup');

        return redirect()->route('wallet.pay.done', ['username' => $username])
            ->with('topup_success', [
                'txn_ref'       => $txnRef,
                'amount_paise'  => $pending['amount_paise'],
                'recipient_name'=> $user->name,
                'payer_name'    => $pending['payer_name'],
            ]);
    }

    /** Success/confirmation page shown after payment */
    public function done(string $username, Request $request): Response
    {
        $payload = session('topup_success');

        return Inertia::render('WalletTopUp', [
            'recipient'   => ['username' => $username],
            'razorpayKey' => config('billing.razorpay.key_id'),
            'done'        => $payload, // null if page refreshed (no double-payment risk)
        ]);
    }
}
```

- [ ] **Step 2: Commit**

```bash
git add app/Http/Controllers/WalletTopUpController.php
git commit -m "feat: WalletTopUpController — public unauthenticated pay-to-wallet with HMAC verify + audit log"
```

---

## Task 5: Authenticated WalletController (dashboard + search)

**Files:**
- Create: `app/Http/Controllers/Admin/WalletController.php`

- [ ] **Step 1: Create WalletController**

```php
<?php
// app/Http/Controllers/Admin/WalletController.php
namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\WalletTransaction;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class WalletController extends Controller
{
    public function index(Request $request): Response
    {
        $user  = auth()->user();
        $query = WalletTransaction::where('recipient_user_id', $user->id);

        // Search filters
        if ($search = $request->get('search')) {
            $query->where(function ($q) use ($search) {
                $q->where('txn_ref', 'like', "%{$search}%")
                  ->orWhere('payer_name', 'like', "%{$search}%")
                  ->orWhere('note', 'like', "%{$search}%");
            });
        }

        if ($category = $request->get('category')) {
            $query->where('category', $category);
        }

        if ($type = $request->get('type')) {
            $query->where('type', $type);
        }

        if ($from = $request->get('from')) {
            $query->whereDate('created_at', '>=', $from);
        }

        if ($to = $request->get('to')) {
            $query->whereDate('created_at', '<=', $to);
        }

        $transactions = $query->latest()
            ->paginate(25)
            ->withQueryString()
            ->through(fn ($tx) => [
                'id'                  => $tx->id,
                'txn_ref'             => $tx->txn_ref,
                'type'                => $tx->type,
                'category'            => $tx->category,
                'amount_paise'        => $tx->amount_paise,
                'balance_after_paise' => $tx->balance_after_paise,
                'payer_name'          => $tx->payer_name,
                // payer_email is hidden on model — expose masked version for display
                'note'                => $tx->note,
                'razorpay_payment_id' => $tx->razorpay_payment_id,
                'created_at'          => $tx->created_at,
            ]);

        return Inertia::render('Admin/Billing/Wallet', [
            'balance'       => $user->wallet_balance_paise,
            'transactions'  => $transactions,
            'filters'       => $request->only(['search', 'category', 'type', 'from', 'to']),
            'payLink'       => route('wallet.pay.show', $user->username),
            'razorpayKey'   => config('billing.razorpay.key_id'),
        ]);
    }
}
```

- [ ] **Step 2: Commit**

```bash
git add app/Http/Controllers/Admin/WalletController.php
git commit -m "feat: Admin WalletController — balance, searchable transaction history, pay link"
```

---

## Task 6: PaymentMethodController

**Files:**
- Create: `app/Http/Controllers/Admin/PaymentMethodController.php`

- [ ] **Step 1: Create controller**

```php
<?php
// app/Http/Controllers/Admin/PaymentMethodController.php
namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\PaymentMethod;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class PaymentMethodController extends Controller
{
    public function index(): Response
    {
        return Inertia::render('Admin/Billing/PaymentMethods', [
            'methods' => auth()->user()->paymentMethods()
                ->get(['id', 'type', 'label', 'is_default', 'created_at']),
        ]);
    }

    public function storeUpi(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'upi_id' => ['required', 'string', 'max:100', 'regex:/^[\w.\-]+@[\w]+$/'],
            'label'  => ['nullable', 'string', 'max:50'],
        ]);

        $user = auth()->user();

        if ($user->paymentMethods()->count() >= 5) {
            return back()->withErrors(['upi_id' => 'You can save up to 5 payment methods.']);
        }

        DB::transaction(function () use ($user, $data) {
            $isDefault = ! $user->paymentMethods()->where('is_default', true)->exists();

            PaymentMethod::create([
                'user_id'    => $user->id,
                'type'       => 'upi',
                'label'      => $data['label'] ?? $data['upi_id'],
                'upi_id'     => $data['upi_id'],
                'is_default' => $isDefault,
            ]);
        });

        return back()->with('success', 'UPI ID saved.');
    }

    public function storeCard(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'razorpay_token_id' => 'required|string|max:100',
            'label'             => 'required|string|max:50',
        ]);

        $user = auth()->user();

        if ($user->paymentMethods()->count() >= 5) {
            return back()->withErrors(['token' => 'You can save up to 5 payment methods.']);
        }

        if ($user->paymentMethods()->where('razorpay_token_id', $data['razorpay_token_id'])->exists()) {
            return back()->with('success', 'Card already saved.');
        }

        DB::transaction(function () use ($user, $data) {
            $isDefault = ! $user->paymentMethods()->where('is_default', true)->exists();

            PaymentMethod::create([
                'user_id'           => $user->id,
                'type'              => 'card',
                'label'             => $data['label'],
                'razorpay_token_id' => $data['razorpay_token_id'],
                'is_default'        => $isDefault,
            ]);
        });

        return back()->with('success', 'Card saved.');
    }

    public function setDefault(Request $request, PaymentMethod $method): RedirectResponse
    {
        abort_if($method->user_id !== auth()->id(), 403);

        DB::transaction(function () use ($method) {
            auth()->user()->paymentMethods()->update(['is_default' => false]);
            $method->update(['is_default' => true]);
        });

        return back()->with('success', 'Default payment method updated.');
    }

    public function destroy(PaymentMethod $method): RedirectResponse
    {
        abort_if($method->user_id !== auth()->id(), 403);
        $method->delete();
        return back()->with('success', 'Payment method removed.');
    }
}
```

- [ ] **Step 2: Commit**

```bash
git add app/Http/Controllers/Admin/PaymentMethodController.php
git commit -m "feat: PaymentMethodController — save/default/delete UPI and card tokens"
```

---

## Task 7: BillingController — wallet pay + Razorpay customer

**Files:**
- Modify: `app/Http/Controllers/Admin/BillingController.php`

- [ ] **Step 1: Add `getRazorpayCustomer` private helper** after the existing `razorpay()` method:

```php
private function getRazorpayCustomer(\App\Models\User $user): string
{
    if ($user->razorpay_customer_id) {
        return $user->razorpay_customer_id;
    }

    $customer = $this->razorpay()->customer->create([
        'name'          => $user->name,
        'email'         => $user->email,
        'fail_existing' => '0',
    ]);

    $user->update(['razorpay_customer_id' => $customer->id]);

    return $customer->id;
}
```

- [ ] **Step 2: Update `createOrder` to pass customer_id**

In `createOrder()`, replace the final `return response()->json([...])` with:

```php
$customerId = $this->getRazorpayCustomer(auth()->user());

return response()->json([
    'order_id'    => $order->id,
    'amount'      => $order->amount,
    'currency'    => $order->currency,
    'key'         => config('billing.razorpay.key_id'),
    'customer_id' => $customerId,
]);
```

- [ ] **Step 3: Update `index()` to pass `walletBalance`**

```php
return Inertia::render('Admin/Billing/Index', [
    'currentPlan'   => $user->currentPlan(),
    'subscription'  => $subscription,
    'soloPlans'     => collect($plans)->filter(fn($p) => ($p['type'] ?? '') === 'solo')->toArray(),
    'orgPlans'      => collect($plans)->filter(fn($p) => ($p['type'] ?? '') === 'org')->map(fn($p, $key) => [...$p, 'key' => $key])->values()->toArray(),
    'walletBalance' => $user->wallet_balance_paise,
]);
```

- [ ] **Step 4: Add `payWithWallet` action** at the end of BillingController:

```php
public function payWithWallet(Request $request): RedirectResponse
{
    abort_if(app()->environment('demo'), 403, 'Wallet payments are disabled in demo mode.');

    $data = $request->validate([
        'plan' => 'required|in:pro,creator',
    ]);

    $plan       = config('billing.plans.' . $data['plan']);
    $pricePaise = (int) $plan['price'];
    $user       = auth()->user();

    if ($user->wallet_balance_paise < $pricePaise) {
        return redirect()->route('admin.billing.index')
            ->withErrors(['wallet' => 'Insufficient wallet balance. Please top up first.']);
    }

    DB::transaction(function () use ($user, $data, $pricePaise) {
        $user->debitWallet(
            $pricePaise,
            'subscription',
            ucfirst($data['plan']) . ' plan subscription',
        );

        Subscription::where('user_id', $user->id)
            ->where('status', 'active')
            ->update(['status' => 'cancelled']);

        Subscription::create([
            'user_id'            => $user->id,
            'plan'               => $data['plan'],
            'status'             => 'active',
            'current_period_end' => now()->addMonth(),
        ]);

        $user->update(['plan' => $data['plan']]);
    });

    return redirect()->route('admin.billing.index')
        ->with('success', 'Plan upgraded using wallet balance!');
}
```

Make sure `use Illuminate\Support\Facades\DB;` and `use App\Models\Subscription;` are at the top of the file (they already are from prior work).

- [ ] **Step 5: Verify**

```bash
php artisan route:list --path=admin/billing
```

Expected: `billing.pay-wallet` appears.

- [ ] **Step 6: Commit**

```bash
git add app/Http/Controllers/Admin/BillingController.php
git commit -m "feat: BillingController — Razorpay customer helper, pass customer_id, pay-with-wallet action"
```

---

## Task 8: Public WalletTopUp Vue page

**Files:**
- Create: `resources/js/Pages/WalletTopUp.vue`

- [ ] **Step 1: Create WalletTopUp.vue**

```vue
<!-- resources/js/Pages/WalletTopUp.vue -->
<template>
  <div class="min-h-screen bg-slate-50 flex items-center justify-center p-4">
    <!-- Success state shown after payment completes -->
    <div v-if="done" class="bg-white rounded-2xl shadow-lg p-8 max-w-md w-full text-center">
      <div class="text-5xl mb-4">✅</div>
      <h1 class="text-xl font-bold text-slate-800 mb-2">Payment Successful!</h1>
      <p class="text-slate-500 text-sm mb-6">
        ₹{{ (done.amount_paise / 100).toFixed(2) }} has been added to
        <strong>{{ done.recipient_name }}</strong>'s wallet.
      </p>
      <div class="bg-slate-50 rounded-xl p-4 text-left text-sm space-y-2 mb-6">
        <div class="flex justify-between">
          <span class="text-slate-500">Transaction ID</span>
          <span class="font-mono font-semibold text-slate-800">{{ done.txn_ref }}</span>
        </div>
        <div class="flex justify-between">
          <span class="text-slate-500">Paid by</span>
          <span class="text-slate-800">{{ done.payer_name }}</span>
        </div>
        <div class="flex justify-between">
          <span class="text-slate-500">Amount</span>
          <span class="font-semibold text-green-700">₹{{ (done.amount_paise / 100).toFixed(2) }}</span>
        </div>
      </div>
      <p class="text-xs text-slate-400">Save your Transaction ID for future reference.</p>
    </div>

    <!-- Top-up form -->
    <div v-else class="bg-white rounded-2xl shadow-lg p-8 max-w-md w-full">
      <div class="text-center mb-6">
        <div class="text-4xl mb-3">💰</div>
        <h1 class="text-xl font-bold text-slate-800">Add to Wallet</h1>
        <p class="text-sm text-slate-500 mt-1">
          Top up <strong>{{ recipient.site_name || recipient.name }}</strong>'s wallet
        </p>
      </div>

      <form @submit.prevent="submit" class="space-y-4">
        <!-- Payer name -->
        <div>
          <label class="block text-xs font-semibold text-slate-600 mb-1">Your Name *</label>
          <input v-model="form.payer_name" type="text" required maxlength="100"
            class="w-full border border-slate-300 rounded-xl px-4 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-blue-400"
            :class="errors.payer_name ? 'border-red-400' : ''"
            placeholder="John Doe" />
          <p v-if="errors.payer_name" class="text-xs text-red-500 mt-1">{{ errors.payer_name[0] }}</p>
        </div>

        <!-- Payer email -->
        <div>
          <label class="block text-xs font-semibold text-slate-600 mb-1">Your Email *</label>
          <input v-model="form.payer_email" type="email" required maxlength="255"
            class="w-full border border-slate-300 rounded-xl px-4 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-blue-400"
            :class="errors.payer_email ? 'border-red-400' : ''"
            placeholder="john@example.com" />
          <p v-if="errors.payer_email" class="text-xs text-red-500 mt-1">{{ errors.payer_email[0] }}</p>
          <p class="text-xs text-slate-400 mt-1">Stored securely and encrypted.</p>
        </div>

        <!-- Amount -->
        <div>
          <label class="block text-xs font-semibold text-slate-600 mb-1">Amount (₹) *</label>
          <div class="flex gap-2 flex-wrap mb-2">
            <button v-for="a in [100, 200, 500, 1000, 2000]" :key="a" type="button"
              @click="form.amount = a"
              class="px-3 py-1.5 rounded-lg text-xs border transition"
              :class="form.amount === a ? 'bg-blue-600 text-white border-blue-600' : 'border-slate-200 text-slate-600'">
              ₹{{ a }}
            </button>
          </div>
          <div class="relative">
            <span class="absolute left-3 top-1/2 -translate-y-1/2 text-slate-400 text-sm">₹</span>
            <input v-model.number="form.amount" type="number" min="100" max="10000" step="1" required
              class="w-full pl-8 pr-4 py-2.5 border border-slate-300 rounded-xl text-sm focus:outline-none focus:ring-2 focus:ring-blue-400"
              :class="errors.amount_paise ? 'border-red-400' : ''" />
          </div>
          <p v-if="errors.amount_paise" class="text-xs text-red-500 mt-1">{{ errors.amount_paise[0] }}</p>
          <p class="text-xs text-slate-400 mt-1">Min ₹100 · Max ₹10,000</p>
        </div>

        <!-- Note / Purpose -->
        <div>
          <label class="block text-xs font-semibold text-slate-600 mb-1">Note / Purpose</label>
          <input v-model="form.note" type="text" maxlength="255"
            class="w-full border border-slate-300 rounded-xl px-4 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-blue-400"
            placeholder="e.g. Project payment, Gift, etc." />
        </div>

        <!-- Error -->
        <div v-if="generalError" class="bg-red-50 border border-red-200 text-red-700 text-sm rounded-xl px-4 py-3">
          {{ generalError }}
        </div>

        <button type="submit" :disabled="paying"
          class="w-full py-3 bg-blue-600 hover:bg-blue-700 disabled:opacity-50 text-white font-semibold rounded-xl transition text-sm">
          {{ paying ? 'Opening payment…' : `Pay ₹${form.amount || 0} via Razorpay` }}
        </button>
      </form>

      <p class="text-xs text-slate-400 text-center mt-4">
        Payments secured by Razorpay · Your email is encrypted and stored securely.
      </p>
    </div>
  </div>
</template>

<script setup>
import { ref, reactive } from 'vue'
import { router } from '@inertiajs/vue3'

const props = defineProps({
  recipient:   Object,
  razorpayKey: String,
  done:        Object, // set after successful payment
})

const form = reactive({
  payer_name:  '',
  payer_email: '',
  amount:      500,
  note:        '',
})

const errors       = ref({})
const generalError = ref(null)
const paying       = ref(false)

const username = props.recipient.username

const submit = async () => {
  errors.value       = {}
  generalError.value = null
  paying.value       = true

  try {
    const res = await fetch(`/pay/${username}/wallet/order`, {
      method: 'POST',
      headers: {
        'Content-Type': 'application/json',
        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content,
        'Accept':       'application/json',
      },
      body: JSON.stringify({
        amount_paise: form.amount * 100,
        payer_name:   form.payer_name,
        payer_email:  form.payer_email,
        note:         form.note || null,
      }),
    })

    if (res.status === 422) {
      const data = await res.json()
      errors.value = data.errors ?? {}
      paying.value = false
      return
    }

    if (!res.ok) throw new Error('Could not initiate payment.')

    const data = await res.json()

    const rzp = new window.Razorpay({
      key:         data.key,
      order_id:    data.order_id,
      amount:      data.amount,
      currency:    'INR',
      name:        props.recipient.site_name || props.recipient.name,
      description: form.note || 'Wallet Top-up',
      prefill: {
        name:  data.payer_name,
        email: data.payer_email,
      },
      theme: { color: '#2563EB' },
      handler: (response) => {
        router.post(
          `/pay/${username}/wallet/verify`,
          response,
          { onFinish: () => { paying.value = false } }
        )
      },
      modal: { ondismiss: () => { paying.value = false } },
    })

    rzp.open()
  } catch (e) {
    generalError.value = e.message ?? 'Something went wrong. Please try again.'
    paying.value = false
  }
}
</script>
```

- [ ] **Step 2: Commit**

```bash
git add resources/js/Pages/WalletTopUp.vue
git commit -m "feat: public WalletTopUp.vue — payer form, Razorpay checkout, success confirmation with TXN ref"
```

---

## Task 9: Admin Wallet Vue page (balance + searchable history)

**Files:**
- Create: `resources/js/Pages/Admin/Billing/Wallet.vue`

- [ ] **Step 1: Create Wallet.vue**

```vue
<!-- resources/js/Pages/Admin/Billing/Wallet.vue -->
<template>
  <AdminLayout>
    <div class="max-w-3xl mx-auto">
      <div class="flex items-center gap-3 mb-6">
        <Link href="/admin/billing" class="text-slate-400 hover:text-slate-600 text-sm">← Billing</Link>
        <h1 class="text-2xl font-bold text-slate-800">My Wallet</h1>
      </div>

      <!-- Flash -->
      <div v-if="$page.props.flash?.success" class="mb-4 bg-green-50 border border-green-200 text-green-800 text-sm rounded-xl px-4 py-3">
        {{ $page.props.flash.success }}
      </div>

      <!-- Balance + pay link -->
      <div class="bg-gradient-to-br from-blue-600 to-blue-800 rounded-2xl p-6 text-white mb-6 shadow-lg">
        <p class="text-sm text-blue-200 mb-1">Available Balance</p>
        <p class="text-4xl font-bold mb-4">₹{{ (balance / 100).toFixed(2) }}</p>
        <div class="flex gap-3 flex-wrap">
          <a :href="payLink" target="_blank"
            class="inline-flex items-center gap-2 bg-white text-blue-700 text-xs font-semibold px-4 py-2 rounded-lg hover:bg-blue-50 transition">
            🔗 Share top-up link
          </a>
          <button @click="copyLink"
            class="inline-flex items-center gap-2 bg-blue-500 bg-opacity-40 hover:bg-opacity-60 text-white text-xs font-semibold px-4 py-2 rounded-lg transition">
            {{ copied ? '✅ Copied!' : '📋 Copy link' }}
          </button>
        </div>
        <p class="text-xs text-blue-200 mt-3">Share your top-up link with anyone — no account needed to send money.</p>
      </div>

      <!-- Transaction history + search -->
      <div class="bg-white border border-slate-200 rounded-2xl p-6">
        <div class="flex items-center justify-between mb-4">
          <h2 class="text-base font-semibold text-slate-800">Transaction History</h2>
          <span class="text-xs text-slate-400">{{ transactions.total }} total</span>
        </div>

        <!-- Search filters -->
        <div class="grid grid-cols-2 md:grid-cols-4 gap-2 mb-4">
          <input v-model="filters.search" @input="applyFilters" type="text"
            placeholder="Search TXN ref, payer, note…"
            class="col-span-2 border border-slate-200 rounded-lg px-3 py-2 text-xs focus:outline-none focus:ring-2 focus:ring-blue-400" />
          <select v-model="filters.category" @change="applyFilters"
            class="border border-slate-200 rounded-lg px-3 py-2 text-xs focus:outline-none">
            <option value="">All categories</option>
            <option value="topup">Top-up</option>
            <option value="subscription">Subscription</option>
            <option value="refund">Refund</option>
            <option value="adjustment">Adjustment</option>
          </select>
          <select v-model="filters.type" @change="applyFilters"
            class="border border-slate-200 rounded-lg px-3 py-2 text-xs focus:outline-none">
            <option value="">Credit & Debit</option>
            <option value="credit">Credits only</option>
            <option value="debit">Debits only</option>
          </select>
          <input v-model="filters.from" @change="applyFilters" type="date"
            class="border border-slate-200 rounded-lg px-3 py-2 text-xs focus:outline-none" />
          <input v-model="filters.to" @change="applyFilters" type="date"
            class="border border-slate-200 rounded-lg px-3 py-2 text-xs focus:outline-none" />
          <button v-if="hasFilters" @click="clearFilters"
            class="col-span-2 text-xs text-slate-500 hover:text-slate-700 border border-slate-200 rounded-lg px-3 py-2">
            Clear filters
          </button>
        </div>

        <!-- Transaction rows -->
        <div v-if="transactions.data.length === 0" class="text-slate-400 text-sm text-center py-12">
          No transactions match your filters.
        </div>
        <div v-else class="divide-y divide-slate-100">
          <div v-for="tx in transactions.data" :key="tx.id"
            class="py-3.5 flex items-start justify-between gap-3">
            <div class="flex-1 min-w-0">
              <div class="flex items-center gap-2 flex-wrap">
                <span class="font-mono text-xs text-slate-500">{{ tx.txn_ref }}</span>
                <span class="text-xs px-2 py-0.5 rounded-full font-medium" :class="categoryColor(tx.category)">
                  {{ tx.category }}
                </span>
              </div>
              <p class="text-sm text-slate-800 mt-0.5 truncate">{{ tx.note || '—' }}</p>
              <p v-if="tx.payer_name" class="text-xs text-slate-400">From: {{ tx.payer_name }}</p>
              <p class="text-xs text-slate-400">{{ formatDate(tx.created_at) }}</p>
              <p v-if="tx.razorpay_payment_id" class="text-xs text-slate-300 font-mono truncate">
                {{ tx.razorpay_payment_id }}
              </p>
            </div>
            <div class="text-right shrink-0">
              <p class="text-sm font-bold" :class="tx.type === 'credit' ? 'text-green-600' : 'text-red-500'">
                {{ tx.type === 'credit' ? '+' : '-' }}₹{{ (tx.amount_paise / 100).toFixed(2) }}
              </p>
              <p class="text-xs text-slate-400">Bal: ₹{{ (tx.balance_after_paise / 100).toFixed(2) }}</p>
            </div>
          </div>
        </div>

        <!-- Pagination -->
        <div v-if="transactions.last_page > 1" class="flex gap-1.5 mt-4 justify-center flex-wrap">
          <Link v-for="link in transactions.links" :key="link.label"
            :href="link.url ?? '#'"
            class="px-3 py-1.5 text-xs rounded-lg border transition"
            :class="link.active ? 'bg-blue-600 text-white border-blue-600' : 'border-slate-200 text-slate-500 hover:border-blue-300'"
            v-html="link.label" />
        </div>
      </div>
    </div>
  </AdminLayout>
</template>

<script setup>
import { ref, computed, reactive } from 'vue'
import { Link, router } from '@inertiajs/vue3'
import AdminLayout from '@/Layouts/AdminLayout.vue'

const props = defineProps({
  balance:      Number,
  transactions: Object,
  filters:      Object,
  payLink:      String,
})

const copied  = ref(false)
const filters = reactive({ ...props.filters })

const hasFilters = computed(() =>
  Object.values(filters).some(v => v && v !== '')
)

const copyLink = async () => {
  await navigator.clipboard.writeText(props.payLink)
  copied.value = true
  setTimeout(() => { copied.value = false }, 2000)
}

const applyFilters = () => {
  router.get('/admin/wallet', filters, { preserveState: true, replace: true })
}

const clearFilters = () => {
  Object.keys(filters).forEach(k => { filters[k] = '' })
  applyFilters()
}

const formatDate = (ts) =>
  new Date(ts).toLocaleString('en-IN', { day: 'numeric', month: 'short', year: 'numeric', hour: '2-digit', minute: '2-digit' })

const categoryColor = (cat) => ({
  topup:        'bg-blue-100 text-blue-700',
  subscription: 'bg-purple-100 text-purple-700',
  refund:       'bg-green-100 text-green-700',
  adjustment:   'bg-orange-100 text-orange-700',
}[cat] ?? 'bg-slate-100 text-slate-600')
</script>
```

- [ ] **Step 2: Commit**

```bash
git add resources/js/Pages/Admin/Billing/Wallet.vue
git commit -m "feat: Admin Wallet.vue — balance, shareable pay link, searchable + filterable transaction history"
```

---

## Task 10: PaymentMethods Vue page

**Files:**
- Create: `resources/js/Pages/Admin/Billing/PaymentMethods.vue`

- [ ] **Step 1: Create PaymentMethods.vue**

```vue
<!-- resources/js/Pages/Admin/Billing/PaymentMethods.vue -->
<template>
  <AdminLayout>
    <div class="max-w-2xl mx-auto">
      <div class="flex items-center gap-3 mb-6">
        <Link href="/admin/billing" class="text-slate-400 hover:text-slate-600 text-sm">← Billing</Link>
        <h1 class="text-2xl font-bold text-slate-800">Payment Methods</h1>
      </div>

      <div v-if="$page.props.flash?.success" class="mb-4 bg-green-50 border border-green-200 text-green-800 text-sm rounded-xl px-4 py-3">
        {{ $page.props.flash.success }}
      </div>

      <!-- Saved methods -->
      <div class="bg-white border border-slate-200 rounded-2xl p-6 mb-6">
        <h2 class="text-base font-semibold text-slate-800 mb-4">Saved Methods ({{ methods.length }}/5)</h2>
        <div v-if="methods.length === 0" class="text-slate-400 text-sm text-center py-8">
          No saved payment methods yet.
        </div>
        <div v-else class="space-y-3">
          <div v-for="m in methods" :key="m.id"
            class="flex items-center justify-between border border-slate-100 rounded-xl px-4 py-3 hover:bg-slate-50">
            <div class="flex items-center gap-3">
              <div class="w-10 h-10 rounded-xl flex items-center justify-center text-xl"
                :class="m.type === 'upi' ? 'bg-purple-50' : 'bg-blue-50'">
                {{ m.type === 'upi' ? '💸' : '💳' }}
              </div>
              <div>
                <p class="text-sm font-medium text-slate-800">{{ m.label }}</p>
                <p class="text-xs text-slate-400 capitalize">
                  {{ m.type }}
                  <span v-if="m.is_default" class="ml-2 text-green-600 font-semibold">· Default</span>
                </p>
              </div>
            </div>
            <div class="flex gap-2">
              <button v-if="!m.is_default"
                @click="setDefault(m.id)"
                class="text-xs text-blue-600 hover:text-blue-800 border border-blue-200 rounded-lg px-3 py-1.5 transition">
                Set default
              </button>
              <button @click="remove(m.id)"
                class="text-xs text-red-500 hover:text-red-700 border border-red-200 rounded-lg px-3 py-1.5 transition">
                Remove
              </button>
            </div>
          </div>
        </div>
      </div>

      <!-- Add UPI ID -->
      <div v-if="methods.length < 5" class="bg-white border border-slate-200 rounded-2xl p-6">
        <h2 class="text-base font-semibold text-slate-800 mb-1">Add UPI ID</h2>
        <p class="text-xs text-slate-400 mb-4">
          Your UPI ID is encrypted before storage — never visible in plain text.
        </p>
        <form @submit.prevent="submitUpi" class="space-y-3">
          <div>
            <input v-model="upiForm.upi_id" type="text"
              placeholder="yourname@upi"
              maxlength="100"
              class="w-full border rounded-xl px-4 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-blue-400"
              :class="upiErrors.upi_id ? 'border-red-400' : 'border-slate-300'" />
            <p v-if="upiErrors.upi_id" class="text-xs text-red-500 mt-1">{{ upiErrors.upi_id[0] }}</p>
            <p class="text-xs text-slate-400 mt-1">Format: <code>username@bankname</code></p>
          </div>
          <div>
            <input v-model="upiForm.label" type="text"
              placeholder="Label (optional — e.g. 'Personal UPI')"
              maxlength="50"
              class="w-full border border-slate-300 rounded-xl px-4 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-blue-400" />
          </div>
          <button type="submit" :disabled="upiForm.processing"
            class="w-full py-2.5 bg-slate-800 hover:bg-slate-900 text-white text-sm font-semibold rounded-xl disabled:opacity-50 transition">
            Save UPI ID
          </button>
        </form>
      </div>
    </div>
  </AdminLayout>
</template>

<script setup>
import { computed } from 'vue'
import { useForm, Link, router, usePage } from '@inertiajs/vue3'
import AdminLayout from '@/Layouts/AdminLayout.vue'

const props = defineProps({ methods: Array })

const page      = usePage()
const upiErrors = computed(() => page.props.errors ?? {})
const upiForm   = useForm({ upi_id: '', label: '' })

const submitUpi = () => {
  upiForm.post('/admin/payment-methods/upi', { onSuccess: () => upiForm.reset() })
}

const setDefault = (id) => router.patch(`/admin/payment-methods/${id}/default`)

const remove = (id) => {
  if (confirm('Remove this payment method?')) {
    router.delete(`/admin/payment-methods/${id}`)
  }
}
</script>
```

- [ ] **Step 2: Commit**

```bash
git add resources/js/Pages/Admin/Billing/PaymentMethods.vue
git commit -m "feat: PaymentMethods.vue — list saved UPI/card with set-default and remove"
```

---

## Task 11: Billing/Index.vue — wallet widget + pay-with-wallet

**Files:**
- Modify: `resources/js/Pages/Admin/Billing/Index.vue`

- [ ] **Step 1: Add `walletBalance` prop and `Link` import**

In `<script setup>`:
```js
import { ref } from 'vue'
import { router, Link } from '@inertiajs/vue3'
import AdminLayout from '@/Layouts/AdminLayout.vue'

const props = defineProps({
  currentPlan:   String,
  subscription:  Object,
  soloPlans:     Object,
  orgPlans:      Object,
  walletBalance: Number,
})
```

- [ ] **Step 2: Add wallet balance widget in template**

Insert after the active subscription block and before the tab switcher:

```html
<!-- Wallet balance widget -->
<div class="mb-6 flex items-center justify-between bg-slate-50 border border-slate-200 rounded-xl px-5 py-3.5">
  <div class="flex items-center gap-3">
    <span class="text-2xl">💰</span>
    <div>
      <p class="text-xs text-slate-500 font-medium">Wallet Balance</p>
      <p class="text-lg font-bold text-slate-800">₹{{ (walletBalance / 100).toFixed(2) }}</p>
    </div>
  </div>
  <div class="flex gap-2">
    <Link href="/admin/wallet"
      class="text-xs px-3 py-1.5 rounded-lg border border-slate-300 text-slate-600 hover:bg-slate-100 transition">
      View Wallet
    </Link>
    <Link href="/admin/payment-methods"
      class="text-xs px-3 py-1.5 rounded-lg border border-slate-300 text-slate-600 hover:bg-slate-100 transition">
      Payment Methods
    </Link>
  </div>
</div>
```

- [ ] **Step 3: Add "Pay with wallet" button on solo plan cards**

In the solo plans loop, replace the `<button v-else @click="subscribe(key)">` with:

```html
<div v-else class="space-y-2">
  <button v-if="plan.price > 0 && walletBalance >= plan.price"
    @click="payWithWallet(key)"
    class="w-full text-sm py-2.5 rounded-xl font-semibold bg-green-600 hover:bg-green-700 text-white transition">
    💰 Pay with Wallet (₹{{ (plan.price / 100).toFixed(0) }})
  </button>
  <button @click="subscribe(key)" :disabled="subscribing === key"
    class="w-full text-sm py-2.5 rounded-xl font-semibold transition"
    :class="key === 'pro' ? 'bg-blue-600 hover:bg-blue-700 text-white' : 'bg-slate-800 hover:bg-slate-900 text-white'">
    {{ subscribing === key ? 'Processing…' : 'Pay with Razorpay' }}
  </button>
</div>
```

- [ ] **Step 4: Add `payWithWallet` function in `<script setup>`**

```js
const payWithWallet = (planKey) => {
  const plan = props.soloPlans[planKey]
  if (!confirm(`Pay ₹${(plan.price / 100).toFixed(0)} from your wallet for the ${plan.name} plan?`)) return
  router.post('/admin/billing/pay-wallet', { plan: planKey })
}
```

- [ ] **Step 5: Build**

```bash
npm run build
```

Expected: Build completes in under 2s with no errors.

- [ ] **Step 6: Commit**

```bash
git add resources/js/Pages/Admin/Billing/Index.vue
git commit -m "feat: Billing/Index — wallet balance widget, payment-methods link, pay-with-wallet button"
```

---

## Task 12: Nav links + global wallet_balance share

**Files:**
- Modify: `resources/js/Layouts/AdminLayout.vue`
- Modify: `app/Http/Middleware/HandleInertiaRequests.php`

- [ ] **Step 1: Add nav links in AdminLayout.vue**

Find the navItems array and add after the Billing entry:

```js
{ label: 'Wallet',           href: '/admin/wallet',           roles: ['admin', 'editor', 'viewer'] },
{ label: 'Payment Methods',  href: '/admin/payment-methods',  roles: ['admin', 'editor', 'viewer'] },
```

- [ ] **Step 2: Share wallet_balance in Inertia middleware**

In `app/Http/Middleware/HandleInertiaRequests.php`, inside `share()`, add:

```php
'wallet_balance' => fn () => $request->user()?->wallet_balance_paise ?? 0,
```

- [ ] **Step 3: Build and verify routes**

```bash
npm run build
php artisan route:list --path=pay
php artisan route:list --path=admin/wallet
php artisan route:list --path=admin/payment
```

Expected: All routes appear, build succeeds.

- [ ] **Step 4: Commit**

```bash
git add resources/js/Layouts/AdminLayout.vue \
        app/Http/Middleware/HandleInertiaRequests.php
git commit -m "feat: Wallet + Payment Methods nav links, share wallet_balance globally via Inertia"
```

---

## Self-Review

**Spec coverage:**
- ✅ Add money without authentication — `WalletTopUpController` (public, no `auth` middleware)
- ✅ Transaction-based — every top-up creates a `WalletTransaction` with unique `txn_ref`
- ✅ Who pays to who — `payer_name` + `payer_email` (encrypted) + `recipient_user_id`
- ✅ Amount and what for — `amount_paise`, `note`, `category`
- ✅ Search/track — `WalletController::index` filters by TXN ref, payer name, note, category, type, date range
- ✅ Supporting details — `razorpay_payment_id`, `razorpay_order_id`, `meta` JSON, `balance_after_paise`
- ✅ Secure storage — `payer_email` encrypted cast, `upi_id` encrypted cast, `razorpay_customer_id` encrypted, card numbers only on Razorpay
- ✅ Pay with wallet — `payWithWallet` in BillingController with `lockForUpdate` + `DB::transaction`
- ✅ Payment methods — UPI + card tokens, max 5, set default, delete

**Placeholder scan:** No TODOs or stubs found.

**Type consistency:**
- `wallet_balance_paise` (int) consistent across User model, WalletTransaction, frontend divide-by-100
- `creditWallet(int $amountPaise, string $category, string $note, ...)` — used in WalletTopUpController with named args ✅
- `debitWallet(int $amountPaise, string $category, string $note, ...)` — used in BillingController ✅
- `txn_ref` generated in `WalletTransaction::generateRef()` — used in verify to return to frontend ✅
- Public routes at `/pay/{username}/wallet` — match `route('wallet.pay.show', $user->username)` in WalletController ✅
