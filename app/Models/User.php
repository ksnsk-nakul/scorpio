<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Str;
use Spatie\Permission\Traits\HasRoles;

class User extends Authenticatable
{
    use HasFactory, Notifiable, HasRoles;

    protected $fillable = [
        'name', 'email', 'password', 'avatar',
        'username', 'github_token',
        'site_name', 'og_image', 'custom_domain', 'plan',
        'google_id', 'github_id', 'email_verified_at',
        'wallet_balance_paise', 'razorpay_customer_id',
    ];

    protected $hidden = ['password', 'remember_token', 'github_token'];

    protected $casts = [
        'password'             => 'hashed',
        'github_token'         => 'encrypted',
        'razorpay_customer_id' => 'encrypted',
    ];

    protected static function booted(): void
    {
        static::creating(function (self $user) {
            if (empty($user->username)) {
                $base = Str::slug($user->name ?? explode('@', $user->email)[0]);
                $user->username = static::uniqueUsername($base);
            }
        });
    }

    public static function uniqueUsername(string $base): string
    {
        $slug = $base;
        $i = 2;
        while (static::where('username', $slug)->exists()) {
            $slug = "{$base}{$i}";
            $i++;
        }
        return $slug;
    }

    public function pages(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(Page::class);
    }

    public function serviceCards(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(ServiceCard::class);
    }

    public function workspaces(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(Workspace::class);
    }

    public function tasks(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(Task::class, 'assignee_id');
    }

    public function comments(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(Comment::class);
    }

    public function subscriptions(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(Subscription::class);
    }

    public function activeSubscription(): \Illuminate\Database\Eloquent\Relations\HasOne
    {
        return $this->hasOne(Subscription::class)->where('status', 'active')->latestOfMany();
    }

    public function ownedOrganizations(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(Organization::class, 'owner_id');
    }

    public function organizationMemberships(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(OrganizationMember::class);
    }

    public function currentPlan(): string
    {
        try {
            $orgMember = $this->organizationMemberships()->with('organization')->first();
            if ($orgMember && $orgMember->organization) {
                return $orgMember->organization->plan ?? 'team';
            }
        } catch (\Illuminate\Database\QueryException $e) {
            // organization_members table may not exist yet
        }
        return $this->plan ?? 'free';
    }

    public function planLimit(string $key): ?int
    {
        if ($this->hasRole('admin')) {
            return null; // unlimited
        }
        $plan = $this->currentPlan();
        return config("billing.limits.{$plan}.{$key}");
    }

    public function withinLimit(string $key, int $current): bool
    {
        $limit = $this->planLimit($key);
        return $limit === null || $current < $limit;
    }

    public function planFeature(string $key): bool
    {
        if ($this->hasRole('admin')) return true;
        $plan = $this->currentPlan();
        return (bool) config("billing.features.{$plan}.{$key}", false);
    }

    public function walletTransactions(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(WalletTransaction::class, 'recipient_user_id')->latest();
    }

    public function enrollments(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(Enrollment::class);
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

    public function debitWallet(
        int $amountPaise,
        string $category,
        string $note,
        ?string $reference = null
    ): int {
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
}
