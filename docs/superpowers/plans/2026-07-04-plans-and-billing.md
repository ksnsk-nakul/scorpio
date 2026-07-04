# Plans, Billing, Organizations & Announcements Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Expand from 3 flat plans to 6 plans (3 solo + 3 org), build a full organizations system with member portfolios and achievements, enforce plan-gated features throughout, and add an announcements/ad system in the dashboard.

**Architecture:** All limits and feature flags live in `config/billing.php`. `User::planFeature(string $key): bool` and `User::planLimit(string $key): ?int` are the only gateway methods — controllers call these. Organizations are a separate entity with their own plan; members inherit Pro features within the org. Announcements are created by admin and shown as banner/modal in every user's dashboard. Admin role bypasses all plan gates.

**Tech Stack:** Laravel 13, PHP 8.4, Vue 3, Inertia.js v2, Tailwind CSS v4, Spatie Laravel-Permission v8, Razorpay

---

## Phase 1 — Billing Config & Solo Plans

### Task 1: Rewrite config/billing.php for 6 plans

**Files:**
- Modify: `config/billing.php`

- [ ] **Step 1: Replace config entirely**

```php
<?php
// config/billing.php
return [
    'razorpay' => [
        'key_id'     => env('RAZORPAY_KEY_ID'),
        'key_secret' => env('RAZORPAY_KEY_SECRET'),
        'me_handle'  => env('RAZORPAY_ME_HANDLE'),
        'me_url'     => 'https://razorpay.me/@' . env('RAZORPAY_ME_HANDLE', ''),
    ],

    /*
     * Solo plans — applied to individual users.
     * null = unlimited.
     */
    'limits' => [
        'free'     => ['pages' => 1,    'workspaces' => 1, 'projects' => 3,  'skills' => 5,  'service_cards' => 1],
        'pro'      => ['pages' => 5,    'workspaces' => 3, 'projects' => 15, 'skills' => 25, 'service_cards' => 5],
        'creator'  => ['pages' => null, 'workspaces' => null, 'projects' => null, 'skills' => null, 'service_cards' => null],
        // org plans — limits per member (they inherit pro)
        'team'     => ['pages' => 5,    'workspaces' => 3, 'projects' => 15, 'skills' => 25, 'service_cards' => 5],
        'business' => ['pages' => 5,    'workspaces' => 3, 'projects' => 15, 'skills' => 25, 'service_cards' => 5],
        'enterprise'=> ['pages' => null, 'workspaces' => null, 'projects' => null, 'skills' => null, 'service_cards' => null],
    ],

    /*
     * Feature flags per plan.
     * true = feature available on this plan.
     */
    'features' => [
        'free'       => ['github_sync' => false, 'analytics' => false, 'seo_control' => false, 'password_pages' => false, 'scheduled_publish' => false, 'contact_attachments' => false, 'white_label' => false, 'audit_logs' => false, 'api_access' => false, 'priority_support' => false],
        'pro'        => ['github_sync' => true,  'analytics' => false, 'seo_control' => true,  'password_pages' => false, 'scheduled_publish' => false, 'contact_attachments' => false, 'white_label' => false, 'audit_logs' => false, 'api_access' => false, 'priority_support' => false],
        'creator'    => ['github_sync' => true,  'analytics' => true,  'seo_control' => true,  'password_pages' => true,  'scheduled_publish' => true,  'contact_attachments' => true,  'white_label' => false, 'audit_logs' => false, 'api_access' => false, 'priority_support' => true],
        'team'       => ['github_sync' => true,  'analytics' => false, 'seo_control' => true,  'password_pages' => false, 'scheduled_publish' => false, 'contact_attachments' => false, 'white_label' => false, 'audit_logs' => false, 'api_access' => false, 'priority_support' => false],
        'business'   => ['github_sync' => true,  'analytics' => true,  'seo_control' => true,  'password_pages' => true,  'scheduled_publish' => true,  'contact_attachments' => true,  'white_label' => true,  'audit_logs' => false, 'api_access' => false, 'priority_support' => true],
        'enterprise' => ['github_sync' => true,  'analytics' => true,  'seo_control' => true,  'password_pages' => true,  'scheduled_publish' => true,  'contact_attachments' => true,  'white_label' => true,  'audit_logs' => true,  'api_access' => true,  'priority_support' => true],
    ],

    /*
     * Plan display metadata for billing UI.
     */
    'plans' => [
        // ── Solo ──────────────────────────────────────────────────
        'free' => [
            'name' => 'Free', 'slug' => 'free', 'type' => 'solo',
            'price' => 0, 'currency' => 'INR', 'interval' => 'month',
            'features' => ['1 page', '1 workspace', '3 projects', '5 skills · 1 service card', 'Basic support'],
        ],
        'pro' => [
            'name' => 'Pro', 'slug' => 'pro', 'type' => 'solo',
            'price' => 49900, 'currency' => 'INR', 'interval' => 'month',
            'badge' => 'Most Popular',
            'features' => ['5 pages', '3 workspaces', '15 projects', '25 skills · 5 service cards', 'GitHub sync & webhooks', 'SEO control per page', 'Basic support'],
        ],
        'creator' => [
            'name' => 'Creator', 'slug' => 'creator', 'type' => 'solo',
            'price' => 99900, 'currency' => 'INR', 'interval' => 'month',
            'features' => ['Everything in Pro', 'Unlimited everything', 'Analytics', 'Password-protected pages', 'Scheduled publishing', 'Contact form attachments', 'Priority support'],
        ],
        // ── Organization ──────────────────────────────────────────
        'team' => [
            'name' => 'Team', 'slug' => 'team', 'type' => 'org',
            'price' => 149900, 'currency' => 'INR', 'interval' => 'month',
            'member_limit' => 5,
            'features' => ['Up to 5 members', 'Each member gets Pro features', 'Org public page', 'Member achievements', 'GitHub sync', 'SEO control'],
        ],
        'business' => [
            'name' => 'Business', 'slug' => 'business', 'type' => 'org',
            'price' => 299900, 'currency' => 'INR', 'interval' => 'month',
            'member_limit' => 15,
            'features' => ['Up to 15 members', 'Everything in Team', 'Analytics per member', 'Password-protected pages', 'Scheduled publishing', 'White-label (no platform branding)', 'Priority support'],
        ],
        'enterprise' => [
            'name' => 'Enterprise', 'slug' => 'enterprise', 'type' => 'org',
            'price' => 'dynamic', 'currency' => 'INR', 'interval' => 'month',
            'base_price' => 499900,
            'member_limit' => null,
            'extra_member_price' => 20000,   // ₹200 per member beyond 20
            'extra_workspace_price' => 10000, // ₹100 per workspace beyond 50
            'extra_page_price' => 5000,       // ₹50 per page beyond 100
            'included_members' => 20,
            'features' => ['Unlimited members', 'Everything in Business', 'Audit logs', 'API access', 'Bulk member import', 'Advanced org analytics', 'White-label', 'Dedicated support'],
        ],
    ],
];
```

- [ ] **Step 2: Add `planFeature()` helper to `app/Models/User.php`** (after `withinLimit`):

```php
public function planFeature(string $key): bool
{
    if ($this->hasRole('admin')) return true;
    $plan = $this->currentPlan();
    return (bool) config("billing.features.{$plan}.{$key}", false);
}
```

- [ ] **Step 3: Update `currentPlan()` to check org membership**

Replace the existing `currentPlan()` in `app/Models/User.php`:

```php
public function currentPlan(): string
{
    // If user belongs to an org, they inherit the org's plan for feature checks
    // but the org plan slug is returned so feature flags match
    $orgMember = $this->organizationMemberships()->with('organization')->first();
    if ($orgMember) {
        return $orgMember->organization->plan ?? 'team';
    }
    return $this->plan ?? 'free';
}
```

- [ ] **Step 4: Commit**

```bash
git add config/billing.php app/Models/User.php
git commit -m "feat: expand billing config to 6 plans with feature flags"
```

---

### Task 2: Update Billing UI for Solo/Org tabs

**Files:**
- Modify: `resources/js/Pages/Admin/Billing/Index.vue`
- Modify: `app/Http/Controllers/Admin/BillingController.php`

- [ ] **Step 1: Update BillingController to pass all plans split by type**

In `app/Http/Controllers/Admin/BillingController.php`, update `index()`:

```php
public function index(): \Inertia\Response
{
    $user         = auth()->user();
    $subscription = $user->subscription;
    $plans        = config('billing.plans');

    return \Inertia\Inertia::render('Admin/Billing/Index', [
        'currentPlan'  => $user->plan ?? 'free',
        'subscription' => $subscription,
        'soloPlans'    => collect($plans)->filter(fn($p) => $p['type'] === 'solo')->toArray(),
        'orgPlans'     => collect($plans)->filter(fn($p) => $p['type'] === 'org')->toArray(),
    ]);
}
```

- [ ] **Step 2: Rewrite Billing/Index.vue with Solo/Org tab switcher**

Replace the entire `resources/js/Pages/Admin/Billing/Index.vue` template section:

```vue
<template>
  <AdminLayout>
    <div class="max-w-5xl mx-auto">
      <div class="flex items-center justify-between mb-6">
        <h1 class="text-2xl font-bold text-slate-800">Billing & Plans</h1>
        <span class="text-xs px-3 py-1 rounded-full font-medium" :class="planBadge(currentPlan)">
          Current: {{ planLabel(currentPlan) }}
        </span>
      </div>

      <!-- Flash -->
      <div v-if="$page.props.flash?.success" class="mb-6 bg-green-50 border border-green-200 text-green-800 text-sm rounded-xl px-4 py-3">{{ $page.props.flash.success }}</div>
      <div v-if="$page.props.flash?.error"   class="mb-6 bg-red-50 border border-red-200 text-red-800 text-sm rounded-xl px-4 py-3">{{ $page.props.flash.error }}</div>

      <!-- Active subscription -->
      <div v-if="subscription?.status === 'active'" class="mb-6 bg-blue-50 border border-blue-200 rounded-xl p-4 flex items-center justify-between">
        <div>
          <p class="text-sm font-medium text-blue-800">{{ planLabel(subscription.plan) }} plan active</p>
          <p v-if="subscription.current_period_end" class="text-xs text-blue-600 mt-0.5">Renews {{ formatDate(subscription.current_period_end) }}</p>
        </div>
        <button @click="cancelPlan" class="text-xs text-red-500 hover:text-red-700 border border-red-200 rounded px-3 py-1.5">Cancel plan</button>
      </div>

      <!-- Tab switcher -->
      <div class="flex gap-1 bg-slate-100 rounded-xl p-1 w-fit mb-6">
        <button v-for="tab in ['solo','org']" :key="tab" @click="activeTab = tab"
          class="px-5 py-2 rounded-lg text-sm font-medium transition"
          :class="activeTab === tab ? 'bg-white shadow text-slate-800' : 'text-slate-500 hover:text-slate-700'">
          {{ tab === 'solo' ? 'Solo Plans' : 'Organization Plans' }}
        </button>
      </div>

      <!-- Solo plans -->
      <div v-if="activeTab === 'solo'" class="grid grid-cols-1 md:grid-cols-3 gap-6">
        <div v-for="(plan, key) in soloPlans" :key="key"
          class="bg-white border rounded-2xl p-6 flex flex-col relative"
          :class="key === 'pro' ? 'border-blue-400 shadow-lg shadow-blue-50' : 'border-slate-200'">
          <div v-if="plan.badge" class="absolute -top-3 left-1/2 -translate-x-1/2 bg-blue-600 text-white text-xs font-semibold px-3 py-0.5 rounded-full">{{ plan.badge }}</div>
          <div class="mb-4">
            <h2 class="text-lg font-bold text-slate-800">{{ plan.name }}</h2>
            <div class="mt-2 flex items-end gap-1">
              <span class="text-3xl font-bold text-slate-900">{{ plan.price === 0 ? 'Free' : '₹' + (plan.price / 100) }}</span>
              <span v-if="plan.price > 0" class="text-sm text-slate-400 mb-1">/month</span>
            </div>
          </div>
          <ul class="space-y-2.5 flex-1 mb-6">
            <li v-for="feature in plan.features" :key="feature" class="flex items-start gap-2 text-sm text-slate-600">
              <svg class="w-4 h-4 text-green-500 flex-shrink-0 mt-0.5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>
              {{ feature }}
            </li>
          </ul>
          <button v-if="key === 'free'" disabled class="w-full text-sm py-2.5 rounded-xl font-medium bg-slate-100 text-slate-400 cursor-not-allowed">
            {{ currentPlan === 'free' ? 'Current plan' : 'Free tier' }}
          </button>
          <button v-else-if="currentPlan === key" disabled class="w-full text-sm py-2.5 rounded-xl font-medium bg-slate-100 text-slate-400 cursor-not-allowed">Current plan</button>
          <button v-else @click="subscribe(key)" :disabled="subscribing === key"
            class="w-full text-sm py-2.5 rounded-xl font-semibold transition"
            :class="key === 'pro' ? 'bg-blue-600 hover:bg-blue-700 text-white' : 'bg-slate-800 hover:bg-slate-900 text-white'">
            {{ subscribing === key ? 'Processing…' : 'Subscribe' }}
          </button>
        </div>
      </div>

      <!-- Org plans -->
      <div v-if="activeTab === 'org'" class="grid grid-cols-1 md:grid-cols-3 gap-6">
        <div v-for="(plan, key) in orgPlans" :key="key"
          class="bg-white border rounded-2xl p-6 flex flex-col relative"
          :class="key === 'business' ? 'border-blue-400 shadow-lg shadow-blue-50' : 'border-slate-200'">
          <div v-if="key === 'business'" class="absolute -top-3 left-1/2 -translate-x-1/2 bg-blue-600 text-white text-xs font-semibold px-3 py-0.5 rounded-full">Most Popular</div>
          <div class="mb-4">
            <h2 class="text-lg font-bold text-slate-800">{{ plan.name }}</h2>
            <div class="mt-2 flex items-end gap-1">
              <span v-if="key === 'enterprise'" class="text-xl font-bold text-slate-900">₹4,999 base</span>
              <span v-else class="text-3xl font-bold text-slate-900">₹{{ plan.price / 100 }}</span>
              <span class="text-sm text-slate-400 mb-1">/month</span>
            </div>
            <p v-if="key === 'enterprise'" class="text-xs text-slate-400 mt-1">+ usage based on members/workspaces/pages</p>
          </div>
          <ul class="space-y-2.5 flex-1 mb-6">
            <li v-for="feature in plan.features" :key="feature" class="flex items-start gap-2 text-sm text-slate-600">
              <svg class="w-4 h-4 text-green-500 flex-shrink-0 mt-0.5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>
              {{ feature }}
            </li>
          </ul>
          <a v-if="key === 'enterprise'" href="mailto:ksnsk2001@gmail.com?subject=Enterprise%20Plan%20Enquiry"
            class="w-full text-sm py-2.5 rounded-xl font-semibold bg-slate-800 hover:bg-slate-900 text-white text-center">
            Contact us
          </a>
          <button v-else-if="currentPlan === key" disabled class="w-full text-sm py-2.5 rounded-xl font-medium bg-slate-100 text-slate-400 cursor-not-allowed">Current plan</button>
          <button v-else @click="subscribe(key)" :disabled="subscribing === key"
            class="w-full text-sm py-2.5 rounded-xl font-semibold bg-blue-600 hover:bg-blue-700 text-white transition">
            {{ subscribing === key ? 'Processing…' : 'Subscribe' }}
          </button>
        </div>
      </div>

      <p class="text-center text-xs text-slate-400 mt-8">All plans include SSL, 99.9% uptime, and data export. Payments secured by Razorpay.</p>
    </div>
  </AdminLayout>
</template>
```

- [ ] **Step 3: Update script section of Billing/Index.vue**

```vue
<script setup>
import { ref } from 'vue'
import { router } from '@inertiajs/vue3'
import AdminLayout from '@/Layouts/AdminLayout.vue'

const props = defineProps({
  currentPlan: String,
  subscription: Object,
  soloPlans: Object,
  orgPlans: Object,
})

const activeTab   = ref('solo')
const subscribing = ref(null)

const planLabel = (key) => {
  const all = { ...props.soloPlans, ...props.orgPlans }
  return all[key]?.name ?? key
}

const planBadge = (key) => {
  const map = { free: 'bg-slate-100 text-slate-600', pro: 'bg-blue-100 text-blue-700', creator: 'bg-purple-100 text-purple-700', team: 'bg-green-100 text-green-700', business: 'bg-orange-100 text-orange-700', enterprise: 'bg-red-100 text-red-700' }
  return map[key] ?? 'bg-slate-100 text-slate-500'
}

const formatDate = (ts) => new Date(ts * 1000).toLocaleDateString('en-IN', { day: 'numeric', month: 'short', year: 'numeric' })

const subscribe = async (planKey) => {
  subscribing.value = planKey
  try {
    const res = await fetch('/admin/billing/order', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content, 'Accept': 'application/json' },
      body: JSON.stringify({ plan: planKey }),
    })
    if (!res.ok) throw new Error()
    const data = await res.json()
    const rzp = new window.Razorpay({
      key: data.key,
      subscription_id: data.subscription_id,
      name: 'KSNSK Portfolio CMS',
      description: `${planLabel(planKey)} Plan`,
      theme: { color: '#2563EB' },
      handler: (response) => {
        router.post('/admin/billing/verify', response, { onFinish: () => { subscribing.value = null } })
      },
      modal: { ondismiss: () => { subscribing.value = null } },
    })
    rzp.open()
  } catch {
    subscribing.value = null
    alert('Could not initiate payment. Please try again.')
  }
}

const cancelPlan = () => {
  if (confirm('Cancel your plan? It stays active until end of billing period.')) {
    router.post('/admin/billing/cancel')
  }
}
</script>
```

- [ ] **Step 4: Commit**

```bash
git add resources/js/Pages/Admin/Billing/Index.vue app/Http/Controllers/Admin/BillingController.php
git commit -m "feat: billing UI with solo/org tabs, 6 plans"
```

---

## Phase 2 — Organizations

### Task 3: Migrations for organizations

**Files:**
- Create: `database/migrations/2026_07_04_000001_create_organizations_table.php`
- Create: `database/migrations/2026_07_04_000002_create_organization_members_table.php`
- Create: `database/migrations/2026_07_04_000003_create_organization_achievements_table.php`

- [ ] **Step 1: Create organizations migration**

```bash
php artisan make:migration create_organizations_table
```

Fill it:

```php
public function up(): void
{
    Schema::create('organizations', function (Blueprint $table) {
        $table->id();
        $table->foreignId('owner_id')->constrained('users')->cascadeOnDelete();
        $table->string('name');
        $table->string('slug')->unique();
        $table->text('description')->nullable();
        $table->string('logo')->nullable();         // path to logo image
        $table->string('plan')->default('team');    // team|business|enterprise
        $table->boolean('white_label')->default(false);
        $table->string('custom_brand_name')->nullable(); // shown instead of KSNSK when white_label=true
        $table->timestamps();
    });
}
```

- [ ] **Step 2: Create organization_members migration**

```bash
php artisan make:migration create_organization_members_table
```

Fill it:

```php
public function up(): void
{
    Schema::create('organization_members', function (Blueprint $table) {
        $table->id();
        $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
        $table->foreignId('user_id')->constrained()->cascadeOnDelete();
        $table->enum('role', ['viewer', 'editor'])->default('viewer');
        $table->timestamp('joined_at')->nullable();
        $table->timestamps();

        $table->unique(['organization_id', 'user_id']);
    });
}
```

- [ ] **Step 3: Create organization_achievements migration**

```bash
php artisan make:migration create_organization_achievements_table
```

Fill it:

```php
public function up(): void
{
    Schema::create('organization_achievements', function (Blueprint $table) {
        $table->id();
        $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
        $table->foreignId('user_id')->constrained()->cascadeOnDelete(); // which member
        $table->string('title');
        $table->text('description')->nullable();
        $table->string('icon')->nullable();  // emoji or icon class
        $table->date('achieved_at')->nullable();
        $table->unsignedInteger('sort_order')->default(0);
        $table->timestamps();
    });
}
```

- [ ] **Step 4: Run migrations**

```bash
php artisan migrate
```

Expected: 3 new tables created.

- [ ] **Step 5: Commit**

```bash
git add database/migrations/
git commit -m "feat: organizations, members, achievements migrations"
```

---

### Task 4: Organization models and relationships

**Files:**
- Create: `app/Models/Organization.php`
- Create: `app/Models/OrganizationMember.php`
- Create: `app/Models/OrganizationAchievement.php`
- Modify: `app/Models/User.php`

- [ ] **Step 1: Create Organization model**

```php
<?php
// app/Models/Organization.php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Organization extends Model
{
    protected $fillable = [
        'owner_id', 'name', 'slug', 'description', 'logo',
        'plan', 'white_label', 'custom_brand_name',
    ];

    protected $casts = ['white_label' => 'boolean'];

    public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'owner_id');
    }

    public function members(): HasMany
    {
        return $this->hasMany(OrganizationMember::class);
    }

    public function achievements(): HasMany
    {
        return $this->hasMany(OrganizationAchievement::class)->orderBy('sort_order');
    }

    public function memberLimit(): ?int
    {
        return config("billing.plans.{$this->plan}.member_limit");
    }

    public function withinMemberLimit(): bool
    {
        $limit = $this->memberLimit();
        return $limit === null || $this->members()->count() < $limit;
    }
}
```

- [ ] **Step 2: Create OrganizationMember model**

```php
<?php
// app/Models/OrganizationMember.php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class OrganizationMember extends Model
{
    protected $fillable = ['organization_id', 'user_id', 'role', 'joined_at'];

    protected $casts = ['joined_at' => 'datetime'];

    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
```

- [ ] **Step 3: Create OrganizationAchievement model**

```php
<?php
// app/Models/OrganizationAchievement.php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class OrganizationAchievement extends Model
{
    protected $fillable = [
        'organization_id', 'user_id', 'title',
        'description', 'icon', 'achieved_at', 'sort_order',
    ];

    protected $casts = ['achieved_at' => 'date'];

    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
```

- [ ] **Step 4: Add relationships to User model**

In `app/Models/User.php` add after existing relationships:

```php
public function ownedOrganizations(): \Illuminate\Database\Eloquent\Relations\HasMany
{
    return $this->hasMany(Organization::class, 'owner_id');
}

public function organizationMemberships(): \Illuminate\Database\Eloquent\Relations\HasMany
{
    return $this->hasMany(OrganizationMember::class);
}
```

- [ ] **Step 5: Commit**

```bash
git add app/Models/
git commit -m "feat: Organization, OrganizationMember, OrganizationAchievement models"
```

---

### Task 5: Organization policy

**Files:**
- Create: `app/Policies/OrganizationPolicy.php`

- [ ] **Step 1: Create policy**

```bash
php artisan make:policy OrganizationPolicy --model=Organization
```

- [ ] **Step 2: Fill policy**

```php
<?php
namespace App\Policies;

use App\Models\Organization;
use App\Models\User;

class OrganizationPolicy
{
    public function viewAny(User $user): bool  { return true; }

    public function view(User $user, Organization $org): bool
    {
        return $user->id === $org->owner_id
            || $org->members()->where('user_id', $user->id)->exists()
            || $user->hasRole('admin');
    }

    public function create(User $user): bool { return true; }

    public function update(User $user, Organization $org): bool
    {
        return $user->id === $org->owner_id || $user->hasRole('admin');
    }

    public function delete(User $user, Organization $org): bool
    {
        return $user->id === $org->owner_id || $user->hasRole('admin');
    }

    public function manageMember(User $user, Organization $org): bool
    {
        return $user->id === $org->owner_id || $user->hasRole('admin');
    }
}
```

- [ ] **Step 3: Commit**

```bash
git add app/Policies/OrganizationPolicy.php
git commit -m "feat: OrganizationPolicy"
```

---

### Task 6: OrganizationController (admin panel)

**Files:**
- Create: `app/Http/Controllers/Admin/OrganizationController.php`

- [ ] **Step 1: Create controller**

```php
<?php
namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Organization;
use App\Models\OrganizationAchievement;
use App\Models\OrganizationMember;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Inertia\Inertia;

class OrganizationController extends Controller
{
    public function index()
    {
        $user = auth()->user();
        return Inertia::render('Admin/Organizations/Index', [
            'owned'       => $user->ownedOrganizations()->withCount('members')->get(),
            'memberships' => $user->organizationMemberships()->with('organization')->get(),
        ]);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'name'        => 'required|string|max:100',
            'description' => 'nullable|string|max:500',
            'plan'        => 'required|in:team,business,enterprise',
        ]);

        $org = Organization::create([
            'owner_id'    => auth()->id(),
            'name'        => $data['name'],
            'slug'        => Str::slug($data['name']) . '-' . Str::random(4),
            'description' => $data['description'] ?? null,
            'plan'        => $data['plan'],
        ]);

        return redirect("/admin/organizations/{$org->id}")->with('success', 'Organization created.');
    }

    public function show(Organization $organization)
    {
        $this->authorize('view', $organization);

        return Inertia::render('Admin/Organizations/Show', [
            'org'          => $organization->load('owner:id,name,username'),
            'members'      => $organization->members()->with('user:id,name,username,og_image')->get(),
            'achievements' => $organization->achievements()->with('user:id,name,username')->get(),
            'isOwner'      => auth()->id() === $organization->owner_id,
        ]);
    }

    public function update(Request $request, Organization $organization)
    {
        $this->authorize('update', $organization);

        $data = $request->validate([
            'name'              => 'sometimes|string|max:100',
            'description'       => 'nullable|string|max:500',
            'white_label'       => 'sometimes|boolean',
            'custom_brand_name' => 'nullable|string|max:100',
        ]);

        $organization->update($data);
        return back()->with('success', 'Organization updated.');
    }

    public function destroy(Organization $organization)
    {
        $this->authorize('delete', $organization);
        $organization->delete();
        return redirect('/admin/organizations')->with('success', 'Organization deleted.');
    }

    // ── Members ──────────────────────────────────────────────────

    public function addMember(Request $request, Organization $organization)
    {
        $this->authorize('manageMember', $organization);

        abort_unless($organization->withinMemberLimit(), 403, 'Member limit reached for your plan.');

        $data = $request->validate([
            'username' => 'required|string',
            'email'    => 'required|email',
        ]);

        $user = User::where('username', $data['username'])
            ->where('email', $data['email'])
            ->first();

        abort_if(! $user, 404, 'No user found with that username and email combination.');
        abort_if($user->id === $organization->owner_id, 422, 'Owner is already a member.');

        $exists = $organization->members()->where('user_id', $user->id)->exists();
        abort_if($exists, 422, 'User is already a member.');

        $organization->members()->create([
            'user_id'   => $user->id,
            'role'      => 'viewer',
            'joined_at' => now(),
        ]);

        // TODO: send notification email to $user
        return back()->with('success', "{$user->name} added as a member.");
    }

    public function updateMemberRole(Request $request, Organization $organization, OrganizationMember $member)
    {
        $this->authorize('manageMember', $organization);
        $data = $request->validate(['role' => 'required|in:viewer,editor']);
        $member->update(['role' => $data['role']]);
        return back()->with('success', 'Member role updated.');
    }

    public function removeMember(Organization $organization, OrganizationMember $member)
    {
        $this->authorize('manageMember', $organization);
        $member->delete();
        return back()->with('success', 'Member removed.');
    }

    // ── Achievements ─────────────────────────────────────────────

    public function addAchievement(Request $request, Organization $organization)
    {
        $this->authorize('update', $organization);

        $data = $request->validate([
            'user_id'     => 'required|integer|exists:users,id',
            'title'       => 'required|string|max:150',
            'description' => 'nullable|string|max:500',
            'icon'        => 'nullable|string|max:10',
            'achieved_at' => 'nullable|date',
        ]);

        // ensure user_id is a member
        abort_unless(
            $organization->members()->where('user_id', $data['user_id'])->exists(),
            422, 'User is not a member of this organization.'
        );

        $organization->achievements()->create($data);
        return back()->with('success', 'Achievement added.');
    }

    public function removeAchievement(Organization $organization, OrganizationAchievement $achievement)
    {
        $this->authorize('update', $organization);
        $achievement->delete();
        return back()->with('success', 'Achievement removed.');
    }
}
```

- [ ] **Step 2: Commit**

```bash
git add app/Http/Controllers/Admin/OrganizationController.php
git commit -m "feat: OrganizationController with members and achievements"
```

---

### Task 7: Organization routes

**Files:**
- Modify: `routes/web.php`

- [ ] **Step 1: Add org routes after existing admin routes**

```php
use App\Http\Controllers\Admin\OrganizationController;

Route::middleware(['auth', 'role:admin,editor,viewer'])
    ->prefix('admin')->name('admin.')
    ->group(function () {
        Route::get('organizations',                                                   [OrganizationController::class, 'index'])->name('organizations.index');
        Route::post('organizations',                                                  [OrganizationController::class, 'store'])->name('organizations.store');
        Route::get('organizations/{organization}',                                    [OrganizationController::class, 'show'])->name('organizations.show');
        Route::patch('organizations/{organization}',                                  [OrganizationController::class, 'update'])->name('organizations.update');
        Route::delete('organizations/{organization}',                                 [OrganizationController::class, 'destroy'])->name('organizations.destroy');

        // Members
        Route::post('organizations/{organization}/members',                           [OrganizationController::class, 'addMember'])->name('organizations.members.add');
        Route::patch('organizations/{organization}/members/{member}/role',            [OrganizationController::class, 'updateMemberRole'])->name('organizations.members.role');
        Route::delete('organizations/{organization}/members/{member}',                [OrganizationController::class, 'removeMember'])->name('organizations.members.remove');

        // Achievements
        Route::post('organizations/{organization}/achievements',                      [OrganizationController::class, 'addAchievement'])->name('organizations.achievements.add');
        Route::delete('organizations/{organization}/achievements/{achievement}',       [OrganizationController::class, 'removeAchievement'])->name('organizations.achievements.remove');
    });
```

- [ ] **Step 2: Add public org route to `routes/web.php`** (before the final catch-all `/{slug}` route):

```php
Route::get('/org/{slug}', [\App\Http\Controllers\PublicController::class, 'orgPage'])
    ->name('org.page')
    ->where('slug', '[a-z0-9\-]+');
```

- [ ] **Step 3: Add `orgPage()` to PublicController**

In `app/Http/Controllers/PublicController.php`:

```php
public function orgPage(string $slug): Response
{
    $org = \App\Models\Organization::where('slug', $slug)->firstOrFail();

    $members = $org->members()->with([
        'user:id,name,username,og_image,site_name',
    ])->get()->map(function ($m) {
        $user = $m->user;
        $homePage = $user->pages()->where('is_home', true)->where('status', 'published')->first();
        return [
            'id'       => $user->id,
            'name'     => $user->name,
            'username' => $user->username,
            'avatar'   => $user->og_image,
            'role'     => $m->role,
            'portfolio_url' => $homePage ? "/portfolio/{$user->username}" : null,
        ];
    });

    $achievements = $org->achievements()->with('user:id,name,username')->get();

    return Inertia::render('Public/OrgPage', [
        'org'          => $org->only('id','name','slug','description','logo','white_label','custom_brand_name'),
        'members'      => $members,
        'achievements' => $achievements,
        'settings'     => [
            'site_name' => $org->white_label && $org->custom_brand_name
                ? $org->custom_brand_name
                : Setting::get('site_name', 'KSNSK'),
        ],
    ]);
}
```

- [ ] **Step 4: Add nav link for Organizations in AdminLayout**

In `resources/js/Layouts/AdminLayout.vue`, add to the nav array:

```js
{ label: 'Organizations', href: '/admin/organizations', roles: ['admin','editor','viewer'] },
```

- [ ] **Step 5: Commit**

```bash
git add routes/web.php app/Http/Controllers/PublicController.php resources/js/Layouts/AdminLayout.vue
git commit -m "feat: organization routes, public org page route, nav link"
```

---

### Task 8: Organization admin UI — Index and Show pages

**Files:**
- Create: `resources/js/Pages/Admin/Organizations/Index.vue`
- Create: `resources/js/Pages/Admin/Organizations/Show.vue`
- Create: `resources/js/Pages/Public/OrgPage.vue`

- [ ] **Step 1: Create Organizations/Index.vue**

```vue
<template>
  <AdminLayout>
    <div class="max-w-4xl mx-auto">
      <div class="flex items-center justify-between mb-6">
        <h1 class="text-2xl font-bold text-slate-800">Organizations</h1>
        <button @click="showCreate = true" class="px-4 py-2 bg-slate-800 text-white text-sm rounded-lg hover:bg-slate-900">
          + New Organization
        </button>
      </div>

      <!-- Create modal -->
      <div v-if="showCreate" class="fixed inset-0 bg-black/40 flex items-center justify-center z-50">
        <div class="bg-white rounded-2xl p-6 w-full max-w-md shadow-xl">
          <h2 class="text-lg font-bold mb-4">Create Organization</h2>
          <div class="space-y-3">
            <input v-model="form.name" placeholder="Organization name" class="w-full border border-slate-200 rounded-lg px-3 py-2 text-sm outline-none focus:ring-2 focus:ring-blue-500" />
            <textarea v-model="form.description" placeholder="Description (optional)" rows="2" class="w-full border border-slate-200 rounded-lg px-3 py-2 text-sm outline-none focus:ring-2 focus:ring-blue-500" />
            <select v-model="form.plan" class="w-full border border-slate-200 rounded-lg px-3 py-2 text-sm outline-none focus:ring-2 focus:ring-blue-500">
              <option value="team">Team — ₹1,499/mo (up to 5 members)</option>
              <option value="business">Business — ₹2,999/mo (up to 15 members)</option>
              <option value="enterprise">Enterprise — dynamic pricing</option>
            </select>
          </div>
          <p v-if="form.errors.name" class="text-xs text-red-500 mt-2">{{ form.errors.name }}</p>
          <div class="flex gap-2 mt-4">
            <button @click="createOrg" :disabled="form.processing" class="flex-1 bg-slate-800 text-white text-sm py-2 rounded-lg hover:bg-slate-900 disabled:opacity-50">Create</button>
            <button @click="showCreate = false" class="flex-1 border border-slate-200 text-slate-600 text-sm py-2 rounded-lg hover:bg-slate-50">Cancel</button>
          </div>
        </div>
      </div>

      <!-- Owned orgs -->
      <div v-if="owned.length" class="mb-8">
        <h2 class="text-sm font-semibold text-slate-500 uppercase tracking-wide mb-3">Owned</h2>
        <div class="space-y-3">
          <Link v-for="org in owned" :key="org.id" :href="`/admin/organizations/${org.id}`"
            class="flex items-center justify-between bg-white border border-slate-200 rounded-xl p-4 hover:shadow-sm transition">
            <div>
              <p class="font-semibold text-slate-800">{{ org.name }}</p>
              <p class="text-xs text-slate-400 mt-0.5">{{ org.members_count }} member{{ org.members_count !== 1 ? 's' : '' }} · {{ org.plan }}</p>
            </div>
            <span class="text-xs text-blue-600">Manage →</span>
          </Link>
        </div>
      </div>

      <!-- Memberships -->
      <div v-if="memberships.length">
        <h2 class="text-sm font-semibold text-slate-500 uppercase tracking-wide mb-3">Member of</h2>
        <div class="space-y-3">
          <Link v-for="m in memberships" :key="m.id" :href="`/admin/organizations/${m.organization.id}`"
            class="flex items-center justify-between bg-white border border-slate-200 rounded-xl p-4 hover:shadow-sm transition">
            <div>
              <p class="font-semibold text-slate-800">{{ m.organization.name }}</p>
              <p class="text-xs text-slate-400 mt-0.5">Your role: {{ m.role }}</p>
            </div>
            <span class="text-xs text-blue-600">View →</span>
          </Link>
        </div>
      </div>

      <div v-if="!owned.length && !memberships.length" class="text-center text-slate-400 py-16 text-sm">
        No organizations yet. Create one to get started.
      </div>
    </div>
  </AdminLayout>
</template>

<script setup>
import { ref } from 'vue'
import { useForm, Link } from '@inertiajs/vue3'
import AdminLayout from '@/Layouts/AdminLayout.vue'

const props = defineProps({ owned: Array, memberships: Array })

const showCreate = ref(false)
const form = useForm({ name: '', description: '', plan: 'team' })

const createOrg = () => form.post('/admin/organizations', { onSuccess: () => { showCreate.value = false } })
</script>
```

- [ ] **Step 2: Create Organizations/Show.vue**

```vue
<template>
  <AdminLayout>
    <div class="max-w-4xl mx-auto space-y-6">

      <!-- Header -->
      <div class="flex items-center justify-between">
        <div>
          <h1 class="text-2xl font-bold text-slate-800">{{ org.name }}</h1>
          <p class="text-sm text-slate-400 mt-0.5">{{ org.plan }} plan · /org/{{ org.slug }}</p>
        </div>
        <div class="flex gap-2">
          <a :href="`/org/${org.slug}`" target="_blank" class="text-xs border border-slate-200 px-3 py-1.5 rounded-lg hover:bg-slate-50">View public page ↗</a>
          <button v-if="isOwner" @click="deleteOrg" class="text-xs text-red-500 border border-red-200 px-3 py-1.5 rounded-lg hover:bg-red-50">Delete</button>
        </div>
      </div>

      <!-- Flash -->
      <div v-if="$page.props.flash?.success" class="bg-green-50 border border-green-200 text-green-800 text-sm rounded-xl px-4 py-3">{{ $page.props.flash.success }}</div>
      <div v-if="$page.props.flash?.error"   class="bg-red-50 border border-red-200 text-red-800 text-sm rounded-xl px-4 py-3">{{ $page.props.flash.error }}</div>

      <!-- Members panel -->
      <div class="bg-white border border-slate-200 rounded-2xl p-5">
        <div class="flex items-center justify-between mb-4">
          <h2 class="font-semibold text-slate-700">Members</h2>
          <button v-if="isOwner" @click="showAddMember = !showAddMember" class="text-xs bg-slate-800 text-white px-3 py-1.5 rounded-lg hover:bg-slate-900">+ Add member</button>
        </div>

        <!-- Add member form -->
        <div v-if="showAddMember" class="mb-4 p-4 bg-slate-50 rounded-xl space-y-2">
          <p class="text-xs text-slate-500">Both username and email must match the user's account.</p>
          <div class="flex gap-2">
            <input v-model="memberForm.username" placeholder="Username" class="flex-1 border border-slate-200 rounded-lg px-3 py-2 text-sm outline-none focus:ring-2 focus:ring-blue-500" />
            <input v-model="memberForm.email" placeholder="Email" type="email" class="flex-1 border border-slate-200 rounded-lg px-3 py-2 text-sm outline-none focus:ring-2 focus:ring-blue-500" />
            <button @click="addMember" :disabled="memberForm.processing" class="px-4 bg-blue-600 text-white text-sm rounded-lg hover:bg-blue-700 disabled:opacity-50">Add</button>
          </div>
          <p v-if="memberForm.hasErrors" class="text-xs text-red-500">{{ Object.values(memberForm.errors)[0] }}</p>
        </div>

        <div class="divide-y divide-slate-100">
          <div v-for="m in members" :key="m.id" class="flex items-center justify-between py-3">
            <div class="flex items-center gap-3">
              <img v-if="m.user.og_image" :src="m.user.og_image" class="w-8 h-8 rounded-full object-cover" />
              <div v-else class="w-8 h-8 rounded-full bg-slate-200 flex items-center justify-center text-xs font-bold text-slate-500">{{ m.user.name[0] }}</div>
              <div>
                <p class="text-sm font-medium text-slate-800">{{ m.user.name }}</p>
                <p class="text-xs text-slate-400">@{{ m.user.username }}</p>
              </div>
            </div>
            <div v-if="isOwner" class="flex items-center gap-2">
              <select :value="m.role" @change="updateRole(m, $event.target.value)"
                class="text-xs border border-slate-200 rounded-lg px-2 py-1 outline-none">
                <option value="viewer">Viewer</option>
                <option value="editor">Editor</option>
              </select>
              <button @click="removeMember(m)" class="text-xs text-red-400 hover:text-red-600">Remove</button>
            </div>
            <span v-else class="text-xs text-slate-400 capitalize">{{ m.role }}</span>
          </div>
        </div>
      </div>

      <!-- Achievements panel -->
      <div class="bg-white border border-slate-200 rounded-2xl p-5">
        <div class="flex items-center justify-between mb-4">
          <h2 class="font-semibold text-slate-700">Member Achievements</h2>
          <button v-if="isOwner" @click="showAddAchievement = !showAddAchievement" class="text-xs bg-slate-800 text-white px-3 py-1.5 rounded-lg hover:bg-slate-900">+ Add achievement</button>
        </div>

        <!-- Add achievement form -->
        <div v-if="showAddAchievement" class="mb-4 p-4 bg-slate-50 rounded-xl space-y-2">
          <div class="grid grid-cols-2 gap-2">
            <select v-model="achievementForm.user_id" class="border border-slate-200 rounded-lg px-3 py-2 text-sm outline-none col-span-2">
              <option value="">Select member</option>
              <option v-for="m in members" :key="m.user.id" :value="m.user.id">{{ m.user.name }}</option>
            </select>
            <input v-model="achievementForm.title" placeholder="Achievement title" class="border border-slate-200 rounded-lg px-3 py-2 text-sm outline-none col-span-2" />
            <input v-model="achievementForm.icon" placeholder="Icon (emoji, e.g. 🏆)" class="border border-slate-200 rounded-lg px-3 py-2 text-sm outline-none" />
            <input v-model="achievementForm.achieved_at" type="date" class="border border-slate-200 rounded-lg px-3 py-2 text-sm outline-none" />
            <textarea v-model="achievementForm.description" placeholder="Description (optional)" rows="2" class="border border-slate-200 rounded-lg px-3 py-2 text-sm outline-none col-span-2" />
          </div>
          <p v-if="achievementForm.hasErrors" class="text-xs text-red-500">{{ Object.values(achievementForm.errors)[0] }}</p>
          <button @click="addAchievement" :disabled="achievementForm.processing" class="px-4 py-2 bg-blue-600 text-white text-sm rounded-lg hover:bg-blue-700 disabled:opacity-50">Save</button>
        </div>

        <div v-if="achievements.length" class="divide-y divide-slate-100">
          <div v-for="a in achievements" :key="a.id" class="flex items-start justify-between py-3">
            <div class="flex items-start gap-3">
              <span class="text-2xl">{{ a.icon || '🏅' }}</span>
              <div>
                <p class="text-sm font-semibold text-slate-800">{{ a.title }}</p>
                <p class="text-xs text-slate-500">{{ a.user.name }}{{ a.achieved_at ? ' · ' + formatDate(a.achieved_at) : '' }}</p>
                <p v-if="a.description" class="text-xs text-slate-400 mt-0.5">{{ a.description }}</p>
              </div>
            </div>
            <button v-if="isOwner" @click="removeAchievement(a)" class="text-xs text-red-400 hover:text-red-600 ml-4">Remove</button>
          </div>
        </div>
        <p v-else class="text-sm text-slate-400 text-center py-4">No achievements yet.</p>
      </div>

    </div>
  </AdminLayout>
</template>

<script setup>
import { ref } from 'vue'
import { useForm, router } from '@inertiajs/vue3'
import AdminLayout from '@/Layouts/AdminLayout.vue'

const props = defineProps({ org: Object, members: Array, achievements: Array, isOwner: Boolean })

const showAddMember      = ref(false)
const showAddAchievement = ref(false)

const memberForm      = useForm({ username: '', email: '' })
const achievementForm = useForm({ user_id: '', title: '', description: '', icon: '', achieved_at: '' })

const formatDate = (d) => new Date(d).toLocaleDateString('en-IN', { day: 'numeric', month: 'short', year: 'numeric' })

const addMember = () => memberForm.post(`/admin/organizations/${props.org.id}/members`, {
  onSuccess: () => { showAddMember.value = false; memberForm.reset() }
})

const updateRole = (m, role) => router.patch(`/admin/organizations/${props.org.id}/members/${m.id}/role`, { role })

const removeMember = (m) => {
  if (confirm('Remove this member?')) router.delete(`/admin/organizations/${props.org.id}/members/${m.id}`)
}

const addAchievement = () => achievementForm.post(`/admin/organizations/${props.org.id}/achievements`, {
  onSuccess: () => { showAddAchievement.value = false; achievementForm.reset() }
})

const removeAchievement = (a) => {
  if (confirm('Remove this achievement?')) router.delete(`/admin/organizations/${props.org.id}/achievements/${a.id}`)
}

const deleteOrg = () => {
  if (confirm('Delete this organization? This cannot be undone.')) {
    router.delete(`/admin/organizations/${props.org.id}`)
  }
}
</script>
```

- [ ] **Step 3: Create Public/OrgPage.vue**

```vue
<template>
  <Head><title>{{ org.name }} · {{ settings.site_name }}</title></Head>
  <div class="min-h-screen bg-gradient-to-br from-slate-50 to-slate-100">
    <nav class="border-b border-slate-200 bg-white px-6 py-4">
      <a href="/" class="text-sm font-bold text-slate-800 hover:text-orange-500 transition-colors">← {{ settings.site_name }}</a>
    </nav>

    <!-- Hero -->
    <div class="bg-white border-b border-slate-200 px-6 py-12 text-center">
      <h1 class="text-4xl font-bold text-slate-900 mb-2">{{ org.name }}</h1>
      <p v-if="org.description" class="text-slate-500 max-w-xl mx-auto">{{ org.description }}</p>
    </div>

    <main class="max-w-5xl mx-auto px-6 py-12 space-y-12">

      <!-- Members grid -->
      <section>
        <h2 class="text-xl font-bold text-slate-800 mb-6">Team Members</h2>
        <div class="grid grid-cols-2 md:grid-cols-3 lg:grid-cols-4 gap-4">
          <a v-for="m in members" :key="m.id"
            :href="m.portfolio_url ?? '#'"
            :class="m.portfolio_url ? 'hover:shadow-md cursor-pointer' : 'cursor-default'"
            class="bg-white border border-slate-200 rounded-2xl p-5 text-center transition">
            <img v-if="m.avatar" :src="m.avatar" class="w-16 h-16 rounded-full mx-auto mb-3 object-cover" />
            <div v-else class="w-16 h-16 rounded-full mx-auto mb-3 bg-slate-200 flex items-center justify-center text-xl font-bold text-slate-500">{{ m.name[0] }}</div>
            <p class="font-semibold text-slate-800 text-sm">{{ m.name }}</p>
            <p class="text-xs text-slate-400 mt-0.5">@{{ m.username }}</p>
            <span v-if="m.portfolio_url" class="inline-block mt-2 text-xs text-blue-600">View portfolio →</span>
          </a>
        </div>
      </section>

      <!-- Achievements -->
      <section v-if="achievements.length">
        <h2 class="text-xl font-bold text-slate-800 mb-6">Achievements</h2>
        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
          <div v-for="a in achievements" :key="a.id" class="bg-white border border-slate-200 rounded-2xl p-5 flex items-start gap-4">
            <span class="text-3xl">{{ a.icon || '🏅' }}</span>
            <div>
              <p class="font-semibold text-slate-800">{{ a.title }}</p>
              <p class="text-xs text-slate-500 mt-0.5">{{ a.user.name }}{{ a.achieved_at ? ' · ' + formatDate(a.achieved_at) : '' }}</p>
              <p v-if="a.description" class="text-sm text-slate-500 mt-1">{{ a.description }}</p>
            </div>
          </div>
        </div>
      </section>

    </main>

    <footer v-if="!org.white_label" class="text-center text-xs text-slate-400 py-8">
      Powered by <a href="/" class="hover:underline">{{ settings.site_name }}</a>
    </footer>
  </div>
</template>

<script setup>
import { Head } from '@inertiajs/vue3'
defineProps({ org: Object, members: Array, achievements: Array, settings: Object })
const formatDate = (d) => new Date(d).toLocaleDateString('en-IN', { day: 'numeric', month: 'short', year: 'numeric' })
</script>
```

- [ ] **Step 4: Commit**

```bash
git add resources/js/Pages/Admin/Organizations/ resources/js/Pages/Public/OrgPage.vue
git commit -m "feat: organization admin UI (index, show) and public org page"
```

---

## Phase 3 — Announcements & Ads System

### Task 9: Announcements migration and model

**Files:**
- Create: `database/migrations/2026_07_04_000004_create_announcements_table.php`
- Create: `app/Models/Announcement.php`

- [ ] **Step 1: Create migration**

```bash
php artisan make:migration create_announcements_table
```

Fill it:

```php
public function up(): void
{
    Schema::create('announcements', function (Blueprint $table) {
        $table->id();
        $table->string('title');
        $table->text('body');
        $table->enum('type', ['info', 'warning', 'success', 'ad'])->default('info');
        $table->enum('display', ['banner', 'modal'])->default('banner');
        $table->string('cta_label')->nullable();   // button text
        $table->string('cta_url')->nullable();     // button link
        $table->boolean('active')->default(true);
        $table->timestamp('starts_at')->nullable();
        $table->timestamp('ends_at')->nullable();
        $table->timestamps();
    });
}
```

- [ ] **Step 2: Create Announcement model**

```php
<?php
// app/Models/Announcement.php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Builder;

class Announcement extends Model
{
    protected $fillable = [
        'title', 'body', 'type', 'display',
        'cta_label', 'cta_url', 'active', 'starts_at', 'ends_at',
    ];

    protected $casts = [
        'active'    => 'boolean',
        'starts_at' => 'datetime',
        'ends_at'   => 'datetime',
    ];

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('active', true)
            ->where(fn($q) => $q->whereNull('starts_at')->orWhere('starts_at', '<=', now()))
            ->where(fn($q) => $q->whereNull('ends_at')->orWhere('ends_at', '>=', now()));
    }
}
```

- [ ] **Step 3: Run migration**

```bash
php artisan migrate
```

- [ ] **Step 4: Commit**

```bash
git add database/migrations/ app/Models/Announcement.php
git commit -m "feat: announcements migration and model"
```

---

### Task 10: AnnouncementController (admin only)

**Files:**
- Create: `app/Http/Controllers/Admin/AnnouncementController.php`
- Modify: `routes/web.php`
- Modify: `app/Http/Controllers/Admin/DashboardController.php`

- [ ] **Step 1: Create AnnouncementController**

```php
<?php
namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Announcement;
use Illuminate\Http\Request;
use Inertia\Inertia;

class AnnouncementController extends Controller
{
    public function index()
    {
        return Inertia::render('Admin/Announcements/Index', [
            'announcements' => Announcement::latest()->get(),
        ]);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'title'     => 'required|string|max:150',
            'body'      => 'required|string|max:1000',
            'type'      => 'required|in:info,warning,success,ad',
            'display'   => 'required|in:banner,modal',
            'cta_label' => 'nullable|string|max:50',
            'cta_url'   => 'nullable|url|max:255',
            'active'    => 'boolean',
            'starts_at' => 'nullable|date',
            'ends_at'   => 'nullable|date|after_or_equal:starts_at',
        ]);

        Announcement::create($data);
        return back()->with('success', 'Announcement created.');
    }

    public function update(Request $request, Announcement $announcement)
    {
        $data = $request->validate([
            'title'     => 'sometimes|string|max:150',
            'body'      => 'sometimes|string|max:1000',
            'type'      => 'sometimes|in:info,warning,success,ad',
            'display'   => 'sometimes|in:banner,modal',
            'cta_label' => 'nullable|string|max:50',
            'cta_url'   => 'nullable|url|max:255',
            'active'    => 'boolean',
            'starts_at' => 'nullable|date',
            'ends_at'   => 'nullable|date',
        ]);

        $announcement->update($data);
        return back()->with('success', 'Announcement updated.');
    }

    public function destroy(Announcement $announcement)
    {
        $announcement->delete();
        return back()->with('success', 'Announcement deleted.');
    }
}
```

- [ ] **Step 2: Add routes (admin only)**

```php
use App\Http\Controllers\Admin\AnnouncementController;

Route::middleware(['auth', 'role:admin'])
    ->prefix('admin')->name('admin.')
    ->group(function () {
        Route::get('announcements',                   [AnnouncementController::class, 'index'])->name('announcements.index');
        Route::post('announcements',                  [AnnouncementController::class, 'store'])->name('announcements.store');
        Route::patch('announcements/{announcement}',  [AnnouncementController::class, 'update'])->name('announcements.update');
        Route::delete('announcements/{announcement}', [AnnouncementController::class, 'destroy'])->name('announcements.destroy');
    });
```

- [ ] **Step 3: Inject active announcements into every dashboard**

In `app/Http/Controllers/Admin/DashboardController.php`, add to the `index()` return data:

```php
'announcements' => \App\Models\Announcement::active()->get(['id','title','body','type','display','cta_label','cta_url']),
```

- [ ] **Step 4: Add Announcements nav link (admin only) in AdminLayout.vue**

```js
{ label: 'Announcements', href: '/admin/announcements', roles: ['admin'] },
```

- [ ] **Step 5: Commit**

```bash
git add app/Http/Controllers/Admin/AnnouncementController.php routes/web.php app/Http/Controllers/Admin/DashboardController.php resources/js/Layouts/AdminLayout.vue
git commit -m "feat: AnnouncementController, routes, inject into dashboard"
```

---

### Task 11: Announcement UI — Admin management page + Dashboard display

**Files:**
- Create: `resources/js/Pages/Admin/Announcements/Index.vue`
- Modify: `resources/js/Pages/Admin/Dashboard.vue`

- [ ] **Step 1: Create Announcements/Index.vue**

```vue
<template>
  <AdminLayout>
    <div class="max-w-4xl mx-auto">
      <div class="flex items-center justify-between mb-6">
        <h1 class="text-2xl font-bold text-slate-800">Announcements</h1>
        <button @click="showCreate = !showCreate" class="px-4 py-2 bg-slate-800 text-white text-sm rounded-lg hover:bg-slate-900">
          + New
        </button>
      </div>

      <!-- Create form -->
      <div v-if="showCreate" class="bg-white border border-slate-200 rounded-2xl p-5 mb-6 space-y-3">
        <h2 class="font-semibold text-slate-700 mb-1">New Announcement</h2>
        <input v-model="form.title" placeholder="Title" class="w-full border border-slate-200 rounded-lg px-3 py-2 text-sm outline-none focus:ring-2 focus:ring-blue-500" />
        <textarea v-model="form.body" placeholder="Body text" rows="3" class="w-full border border-slate-200 rounded-lg px-3 py-2 text-sm outline-none focus:ring-2 focus:ring-blue-500" />
        <div class="grid grid-cols-2 gap-3">
          <select v-model="form.type" class="border border-slate-200 rounded-lg px-3 py-2 text-sm outline-none">
            <option value="info">ℹ️ Info</option>
            <option value="success">✅ Success</option>
            <option value="warning">⚠️ Warning</option>
            <option value="ad">📢 Ad / Promotion</option>
          </select>
          <select v-model="form.display" class="border border-slate-200 rounded-lg px-3 py-2 text-sm outline-none">
            <option value="banner">Banner (top of dashboard)</option>
            <option value="modal">Modal (popup on dashboard)</option>
          </select>
          <input v-model="form.cta_label" placeholder="Button label (optional)" class="border border-slate-200 rounded-lg px-3 py-2 text-sm outline-none" />
          <input v-model="form.cta_url" placeholder="Button URL (optional)" class="border border-slate-200 rounded-lg px-3 py-2 text-sm outline-none" />
          <input v-model="form.starts_at" type="datetime-local" class="border border-slate-200 rounded-lg px-3 py-2 text-sm outline-none" />
          <input v-model="form.ends_at" type="datetime-local" class="border border-slate-200 rounded-lg px-3 py-2 text-sm outline-none" />
        </div>
        <label class="flex items-center gap-2 text-sm text-slate-600 cursor-pointer">
          <input v-model="form.active" type="checkbox" class="rounded" /> Active now
        </label>
        <p v-if="form.hasErrors" class="text-xs text-red-500">{{ Object.values(form.errors)[0] }}</p>
        <button @click="createAnnouncement" :disabled="form.processing" class="px-4 py-2 bg-blue-600 text-white text-sm rounded-lg hover:bg-blue-700 disabled:opacity-50">Create</button>
      </div>

      <!-- List -->
      <div class="space-y-3">
        <div v-for="a in announcements" :key="a.id" class="bg-white border border-slate-200 rounded-2xl p-5 flex items-start justify-between gap-4">
          <div class="flex-1">
            <div class="flex items-center gap-2 mb-1">
              <span class="text-xs font-semibold uppercase px-2 py-0.5 rounded-full" :class="typeBadge(a.type)">{{ a.type }}</span>
              <span class="text-xs text-slate-400 uppercase">{{ a.display }}</span>
              <span v-if="!a.active" class="text-xs text-slate-400">(inactive)</span>
            </div>
            <p class="font-medium text-slate-800">{{ a.title }}</p>
            <p class="text-sm text-slate-500 mt-0.5">{{ a.body }}</p>
            <p v-if="a.cta_label" class="text-xs text-blue-600 mt-1">CTA: {{ a.cta_label }} → {{ a.cta_url }}</p>
          </div>
          <div class="flex gap-2 flex-shrink-0">
            <button @click="toggleActive(a)" class="text-xs border border-slate-200 px-2 py-1 rounded hover:bg-slate-50">
              {{ a.active ? 'Deactivate' : 'Activate' }}
            </button>
            <button @click="deleteAnnouncement(a)" class="text-xs text-red-400 hover:text-red-600">Delete</button>
          </div>
        </div>
        <p v-if="!announcements.length" class="text-center text-slate-400 text-sm py-12">No announcements yet.</p>
      </div>
    </div>
  </AdminLayout>
</template>

<script setup>
import { ref } from 'vue'
import { useForm, router } from '@inertiajs/vue3'
import AdminLayout from '@/Layouts/AdminLayout.vue'

const props = defineProps({ announcements: Array })
const showCreate = ref(false)
const form = useForm({ title: '', body: '', type: 'info', display: 'banner', cta_label: '', cta_url: '', active: true, starts_at: '', ends_at: '' })

const typeBadge = (t) => ({ info: 'bg-blue-100 text-blue-700', success: 'bg-green-100 text-green-700', warning: 'bg-amber-100 text-amber-700', ad: 'bg-purple-100 text-purple-700' })[t]

const createAnnouncement = () => form.post('/admin/announcements', { onSuccess: () => { showCreate.value = false; form.reset() } })

const toggleActive = (a) => router.patch(`/admin/announcements/${a.id}`, { active: !a.active }, { preserveScroll: true })

const deleteAnnouncement = (a) => { if (confirm('Delete this announcement?')) router.delete(`/admin/announcements/${a.id}`, { preserveScroll: true }) }
</script>
```

- [ ] **Step 2: Add announcement banner and modal to Dashboard.vue**

In `resources/js/Pages/Admin/Dashboard.vue`, add to `<script setup>`:

```js
import { ref, computed, onMounted } from 'vue'

// announcements
const props = defineProps({ /* existing props */ announcements: { type: Array, default: () => [] } })

const banners = computed(() => props.announcements.filter(a => a.display === 'banner'))
const modals  = computed(() => props.announcements.filter(a => a.display === 'modal'))

const dismissedModals = ref(new Set())
const activeModal = computed(() => modals.value.find(m => !dismissedModals.value.has(m.id)) ?? null)
const dismissModal = () => { if (activeModal.value) dismissedModals.value.add(activeModal.value.id) }

const bannerColors = { info: 'bg-blue-50 border-blue-200 text-blue-800', warning: 'bg-amber-50 border-amber-200 text-amber-800', success: 'bg-green-50 border-green-200 text-green-800', ad: 'bg-purple-50 border-purple-200 text-purple-800' }
```

Add to template (right after `<AdminLayout>` opening):

```vue
<!-- Announcement banners -->
<div v-for="b in banners" :key="b.id"
  class="mb-4 border rounded-xl px-4 py-3 flex items-center justify-between text-sm"
  :class="bannerColors[b.type]">
  <div>
    <span class="font-semibold">{{ b.title }}</span>
    <span class="ml-2 opacity-80">{{ b.body }}</span>
  </div>
  <a v-if="b.cta_url" :href="b.cta_url" target="_blank"
    class="ml-4 flex-shrink-0 text-xs font-semibold underline hover:opacity-70">
    {{ b.cta_label || 'Learn more' }} →
  </a>
</div>

<!-- Announcement modal -->
<div v-if="activeModal" class="fixed inset-0 bg-black/40 flex items-center justify-center z-50">
  <div class="bg-white rounded-2xl p-6 w-full max-w-md shadow-xl">
    <h2 class="text-lg font-bold text-slate-800 mb-2">{{ activeModal.title }}</h2>
    <p class="text-sm text-slate-600 mb-4">{{ activeModal.body }}</p>
    <div class="flex gap-2">
      <a v-if="activeModal.cta_url" :href="activeModal.cta_url" target="_blank"
        class="flex-1 bg-blue-600 text-white text-sm text-center py-2.5 rounded-xl font-semibold hover:bg-blue-700">
        {{ activeModal.cta_label || 'Learn more' }}
      </a>
      <button @click="dismissModal"
        class="flex-1 border border-slate-200 text-slate-600 text-sm py-2.5 rounded-xl hover:bg-slate-50">
        Dismiss
      </button>
    </div>
  </div>
</div>
```

- [ ] **Step 3: Commit**

```bash
git add resources/js/Pages/Admin/Announcements/ resources/js/Pages/Admin/Dashboard.vue
git commit -m "feat: announcements UI, dashboard banner and modal display"
```

---

## Phase 4 — Enforce Plan Feature Gates

### Task 12: Enforce feature gates in controllers and views

**Files:**
- Modify: `app/Http/Controllers/Admin/GitHubController.php` — check `github_sync`
- Modify: `app/Http/Controllers/Admin/PageController.php` — check `scheduled_publish`, `password_pages`
- Modify: `resources/js/Layouts/AdminLayout.vue` — show upgrade prompt when feature locked

- [ ] **Step 1: Gate GitHub sync behind `github_sync` feature**

In `GitHubController::index()` add at the top:

```php
abort_unless(auth()->user()->planFeature('github_sync') || auth()->user()->hasRole('admin'), 403, 'GitHub sync requires Pro plan or above.');
```

- [ ] **Step 2: Add `pages` limit check in PageController::store()**

In `app/Http/Controllers/Admin/PageController.php`, inside `store()`:

```php
$user = auth()->user();
$count = $user->pages()->count();
abort_unless($user->withinLimit('pages', $count), 403, 'Page limit reached for your plan.');
```

- [ ] **Step 3: Add `planFeatures` to every Inertia shared data**

In `app/Http/Middleware/HandleInertiaRequests.php` (or wherever `share()` is called), add to the shared data array:

```php
'planFeatures' => fn () => auth()->check() ? [
    'github_sync'         => auth()->user()->planFeature('github_sync'),
    'analytics'           => auth()->user()->planFeature('analytics'),
    'seo_control'         => auth()->user()->planFeature('seo_control'),
    'password_pages'      => auth()->user()->planFeature('password_pages'),
    'scheduled_publish'   => auth()->user()->planFeature('scheduled_publish'),
    'contact_attachments' => auth()->user()->planFeature('contact_attachments'),
    'white_label'         => auth()->user()->planFeature('white_label'),
    'audit_logs'          => auth()->user()->planFeature('audit_logs'),
    'api_access'          => auth()->user()->planFeature('api_access'),
    'priority_support'    => auth()->user()->planFeature('priority_support'),
] : [],
```

- [ ] **Step 4: Create a reusable `UpgradeGate.vue` component**

```vue
<!-- resources/js/Components/Admin/UpgradeGate.vue -->
<template>
  <div v-if="!allowed" class="relative">
    <div class="absolute inset-0 bg-white/80 backdrop-blur-sm rounded-xl z-10 flex items-center justify-center">
      <div class="text-center px-4">
        <span class="text-2xl">🔒</span>
        <p class="text-sm font-semibold text-slate-700 mt-1">{{ label }}</p>
        <p class="text-xs text-slate-400 mb-3">Available on {{ requiredPlan }} and above</p>
        <a href="/admin/billing" class="text-xs bg-blue-600 text-white px-4 py-1.5 rounded-lg hover:bg-blue-700">Upgrade</a>
      </div>
    </div>
    <div class="pointer-events-none opacity-30 select-none">
      <slot />
    </div>
  </div>
  <slot v-else />
</template>

<script setup>
defineProps({
  allowed:      { type: Boolean, required: true },
  label:        { type: String, default: 'Feature locked' },
  requiredPlan: { type: String, default: 'Pro' },
})
</script>
```

- [ ] **Step 5: Use UpgradeGate in GitHub Index page**

In `resources/js/Pages/Admin/GitHub/Index.vue`, wrap the token connect section:

```vue
<UpgradeGate :allowed="$page.props.planFeatures.github_sync" label="GitHub Sync" required-plan="Pro">
  <!-- existing token connect UI -->
</UpgradeGate>
```

Import at top:
```js
import UpgradeGate from '@/Components/Admin/UpgradeGate.vue'
```

- [ ] **Step 6: Commit**

```bash
git add app/Http/Controllers/ app/Http/Middleware/ resources/js/Components/Admin/UpgradeGate.vue resources/js/Pages/Admin/GitHub/Index.vue
git commit -m "feat: plan feature gates — server-side abort, shared planFeatures, UpgradeGate component"
```

---

## Final

- [ ] **Run full build to verify no TS/Vue errors**

```bash
npm run build
```

Expected: `✓ built in X.XXs` with no errors.

- [ ] **Push everything**

```bash
git push
```

---

## Self-Review

**Spec coverage:**
- ✅ 3 solo plans (free/pro/creator) with correct limits
- ✅ 3 org plans (team/business/enterprise) with member limits
- ✅ Enterprise dynamic pricing defined in config
- ✅ Admin bypasses all gates via `hasRole('admin')`
- ✅ Each org member inherits Pro features via `currentPlan()`
- ✅ Owner-only billing, members cannot manage plan
- ✅ Members default viewer, owner promotes to editor
- ✅ Owner adds achievements, not members
- ✅ Invite by username + email (both required)
- ✅ Org public page at `/org/{slug}`
- ✅ White-label hides platform branding on org public page
- ✅ Announcements: banner + modal types, admin-only management
- ✅ `UpgradeGate` component for UI-level gating
- ✅ Server-side `planFeature()` + `withinLimit()` enforcement
- ✅ Billing UI tabs for solo vs org plans
- ✅ No custom domain (removed per user request)
