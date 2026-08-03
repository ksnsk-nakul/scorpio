# Plans & Billing Overhaul Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Replace the 3-plan billing config with 6 plans (3 solo + 3 org), enforce all feature gates across controllers, and update the billing UI.

**Architecture:** All plan limits live in `config/billing.php`. The `User` model exposes `planFeature(string $key): bool` and `planLimit(string $key): ?int`. Controllers call these; admin role always returns unlimited/true. The billing page splits into Solo and Organization tabs.

**Tech Stack:** Laravel 13, Vue 3, Inertia.js, Tailwind CSS v4, Spatie Laravel-Permission, Razorpay

---

### Task 1: Update config/billing.php with all 6 plans

**Files:**
- Modify: `config/billing.php`

- [ ] **Step 1: Replace billing config entirely**

```php
<?php

return [
    'razorpay' => [
        'key_id'     => env('RAZORPAY_KEY_ID'),
        'key_secret' => env('RAZORPAY_KEY_SECRET'),
        'me_handle'  => env('RAZORPAY_ME_HANDLE'),
        'me_url'     => 'https://razorpay.me/@' . env('RAZORPAY_ME_HANDLE', ''),
    ],

    /*
     * Per-plan quota limits. null = unlimited.
     * admin role always bypasses these — checked in User::planLimit().
     */
    'limits' => [
        'free'       => ['pages' => 1,    'workspaces' => 1, 'skills' => 5,    'service_cards' => 1,  'projects' => 3],
        'pro'        => ['pages' => 5,    'workspaces' => 3, 'skills' => 25,   'service_cards' => 5,  'projects' => 15],
        'creator'    => ['pages' => null, 'workspaces' => null, 'skills' => null, 'service_cards' => null, 'projects' => null],
        'team'       => ['pages' => null, 'workspaces' => null, 'skills' => null, 'service_cards' => null, 'projects' => null],
        'business'   => ['pages' => null, 'workspaces' => null, 'skills' => null, 'service_cards' => null, 'projects' => null],
        'enterprise' => ['pages' => null, 'workspaces' => null, 'skills' => null, 'service_cards' => null, 'projects' => null],
        'admin'      => ['pages' => null, 'workspaces' => null, 'skills' => null, 'service_cards' => null, 'projects' => null],
    ],

    /*
     * Boolean feature flags per plan.
     * admin always gets true for everything.
     */
    'features' => [
        'free' => [
            'github_sync'            => false,
            'analytics'              => false,
            'seo_control'            => false,
            'password_pages'         => false,
            'scheduled_publish'      => false,
            'file_attachments'       => false,
            'priority_support'       => false,
            'white_label'            => false,
            'audit_logs'             => false,
            'api_access'             => false,
            'bulk_invite'            => false,
            'org_analytics'          => false,
            'show_ads'               => true,
        ],
        'pro' => [
            'github_sync'            => true,
            'analytics'              => false,
            'seo_control'            => true,
            'password_pages'         => false,
            'scheduled_publish'      => false,
            'file_attachments'       => false,
            'priority_support'       => false,
            'white_label'            => false,
            'audit_logs'             => false,
            'api_access'             => false,
            'bulk_invite'            => false,
            'org_analytics'          => false,
            'show_ads'               => true,
        ],
        'creator' => [
            'github_sync'            => true,
            'analytics'              => true,
            'seo_control'            => true,
            'password_pages'         => true,
            'scheduled_publish'      => true,
            'file_attachments'       => true,
            'priority_support'       => true,
            'white_label'            => false,
            'audit_logs'             => false,
            'api_access'             => false,
            'bulk_invite'            => false,
            'org_analytics'          => false,
            'show_ads'               => false,
        ],
        'team' => [
            'github_sync'            => true,
            'analytics'              => false,
            'seo_control'            => true,
            'password_pages'         => false,
            'scheduled_publish'      => false,
            'file_attachments'       => false,
            'priority_support'       => false,
            'white_label'            => false,
            'audit_logs'             => false,
            'api_access'             => false,
            'bulk_invite'            => false,
            'org_analytics'          => false,
            'show_ads'               => true,
        ],
        'business' => [
            'github_sync'            => true,
            'analytics'              => true,
            'seo_control'            => true,
            'password_pages'         => true,
            'scheduled_publish'      => true,
            'file_attachments'       => true,
            'priority_support'       => true,
            'white_label'            => true,
            'audit_logs'             => false,
            'api_access'             => false,
            'bulk_invite'            => false,
            'org_analytics'          => true,
            'show_ads'               => false,
        ],
        'enterprise' => [
            'github_sync'            => true,
            'analytics'              => true,
            'seo_control'            => true,
            'password_pages'         => true,
            'scheduled_publish'      => true,
            'file_attachments'       => true,
            'priority_support'       => true,
            'white_label'            => true,
            'audit_logs'             => true,
            'api_access'             => true,
            'bulk_invite'            => true,
            'org_analytics'          => true,
            'show_ads'               => false,
        ],
        'admin' => [
            'github_sync'            => true,
            'analytics'              => true,
            'seo_control'            => true,
            'password_pages'         => true,
            'scheduled_publish'      => true,
            'file_attachments'       => true,
            'priority_support'       => true,
            'white_label'            => true,
            'audit_logs'             => true,
            'api_access'             => true,
            'bulk_invite'            => true,
            'org_analytics'          => true,
            'show_ads'               => false,
        ],
    ],

    'plans' => [
        // ── Solo ──────────────────────────────────────────────
        'free' => [
            'name'     => 'Free',
            'slug'     => 'free',
            'type'     => 'solo',
            'price'    => 0,
            'currency' => 'INR',
            'interval' => 'month',
            'features' => [
                '1 page',
                '1 workspace · 3 projects',
                '5 skills · 1 service card',
                'Basic support',
            ],
        ],
        'pro' => [
            'name'     => 'Pro',
            'slug'     => 'pro',
            'type'     => 'solo',
            'price'    => 49900,
            'currency' => 'INR',
            'interval' => 'month',
            'features' => [
                '5 pages',
                '3 workspaces · 15 projects',
                '25 skills · 5 service cards',
                'GitHub sync & webhooks',
                'SEO control per page',
            ],
        ],
        'creator' => [
            'name'     => 'Creator',
            'slug'     => 'creator',
            'type'     => 'solo',
            'price'    => 99900,
            'currency' => 'INR',
            'interval' => 'month',
            'features' => [
                'Unlimited everything',
                'Portfolio analytics',
                'Password-protected pages',
                'Scheduled publishing',
                'Contact file attachments',
                'Priority support',
                'No ads',
            ],
        ],
        // ── Organization ──────────────────────────────────────
        'team' => [
            'name'        => 'Team',
            'slug'        => 'team',
            'type'        => 'org',
            'price'       => 149900,
            'currency'    => 'INR',
            'interval'    => 'month',
            'max_members' => 5,
            'features'    => [
                'Up to 5 members',
                'Each member gets Pro features',
                'Org public page',
                'Member achievements',
                'GitHub sync',
            ],
        ],
        'business' => [
            'name'        => 'Business',
            'slug'        => 'business',
            'type'        => 'org',
            'price'       => 299900,
            'currency'    => 'INR',
            'interval'    => 'month',
            'max_members' => 15,
            'features'    => [
                'Up to 15 members',
                'Everything in Team',
                'Analytics & org analytics',
                'White-label (no KSNSK branding)',
                'Priority support',
                'No ads for members',
            ],
        ],
        'enterprise' => [
            'name'        => 'Enterprise',
            'slug'        => 'enterprise',
            'type'        => 'org',
            'price'       => null, // dynamic — calculated at billing cycle
            'base_price'  => 499900, // ₹4,999 base
            'per_member'  => 20000,  // ₹200 per extra member beyond 20
            'per_workspace' => 10000, // ₹100 per workspace beyond 50
            'per_page'    => 5000,   // ₹50 per page beyond 100
            'currency'    => 'INR',
            'interval'    => 'month',
            'max_members' => null,
            'features'    => [
                'Unlimited members',
                'Everything in Business',
                'Audit logs',
                'API access',
                'Bulk member import (CSV)',
                'Custom notification email name',
                'Dynamic billing based on usage',
                'Dedicated support',
            ],
        ],
    ],
];
```

- [ ] **Step 2: Commit**

```bash
git add config/billing.php
git commit -m "feat: 6-plan billing config (free/pro/creator solo + team/business/enterprise org)"
```

---

### Task 2: Update User model with planFeature() helper

**Files:**
- Modify: `app/Models/User.php`

- [ ] **Step 1: Add planFeature() and isOrgPlan() methods**

Add these methods to `app/Models/User.php` inside the class, after `withinLimit()`:

```php
public function planFeature(string $key): bool
{
    if ($this->hasRole('admin')) return true;
    $plan = $this->currentPlan();
    return (bool) config("billing.features.{$plan}.{$key}", false);
}

public function isOrgPlan(): bool
{
    return in_array($this->currentPlan(), ['team', 'business', 'enterprise']);
}

public function isSoloPlan(): bool
{
    return in_array($this->currentPlan(), ['free', 'pro', 'creator']);
}

public function showAds(): bool
{
    if ($this->hasRole('admin')) return false;
    return (bool) config("billing.features.{$this->currentPlan()}.show_ads", true);
}
```

Also update `planLimit()` — add `pages` and `workspaces` to the existing method (no other changes needed; config now includes them).

- [ ] **Step 2: Commit**

```bash
git add app/Models/User.php
git commit -m "feat: add planFeature(), isOrgPlan(), showAds() to User model"
```

---

### Task 3: Add pages and workspaces limit enforcement in controllers

**Files:**
- Modify: `app/Http/Controllers/Admin/PageController.php`
- Modify: `app/Http/Controllers/Admin/WorkspaceController.php`

- [ ] **Step 1: Enforce page limit in PageController::store()**

In `app/Http/Controllers/Admin/PageController.php`, at the top of `store()`, before validation:

```php
public function store(Request $request)
{
    $user = auth()->user();
    $count = $user->pages()->count();
    abort_unless($user->withinLimit('pages', $count), 403, 'Page limit reached for your plan.');

    // ... existing validation and creation code
}
```

- [ ] **Step 2: Enforce workspace limit in WorkspaceController::store()**

In `app/Http/Controllers/Admin/WorkspaceController.php`, at the top of `store()`:

```php
public function store(Request $request)
{
    $user = auth()->user();
    $count = $user->workspaces()->count();
    abort_unless($user->withinLimit('workspaces', $count), 403, 'Workspace limit reached for your plan.');

    // ... existing validation and creation code
}
```

- [ ] **Step 3: Pass plan feature flags to Dashboard**

In `app/Http/Controllers/Admin/DashboardController.php`, add to the `index()` return data:

```php
'planFeatures' => [
    'analytics'         => $user->planFeature('analytics'),
    'github_sync'       => $user->planFeature('github_sync'),
    'password_pages'    => $user->planFeature('password_pages'),
    'scheduled_publish' => $user->planFeature('scheduled_publish'),
    'file_attachments'  => $user->planFeature('file_attachments'),
    'show_ads'          => $user->showAds(),
],
```

- [ ] **Step 4: Commit**

```bash
git add app/Http/Controllers/Admin/PageController.php \
        app/Http/Controllers/Admin/WorkspaceController.php \
        app/Http/Controllers/Admin/DashboardController.php
git commit -m "feat: enforce pages/workspaces limits, pass planFeatures to dashboard"
```

---

### Task 4: Update Billing UI — 6-plan grid with Solo/Org tabs

**Files:**
- Modify: `resources/js/Pages/Admin/Billing/Index.vue`

- [ ] **Step 1: Rewrite the billing page**

Replace `resources/js/Pages/Admin/Billing/Index.vue` with:

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
      <div v-if="$page.props.flash?.success"
        class="mb-6 bg-green-50 border border-green-200 text-green-800 text-sm rounded-xl px-4 py-3">
        {{ $page.props.flash.success }}
      </div>
      <div v-if="$page.props.flash?.error"
        class="mb-6 bg-red-50 border border-red-200 text-red-800 text-sm rounded-xl px-4 py-3">
        {{ $page.props.flash.error }}
      </div>

      <!-- Active subscription -->
      <div v-if="subscription?.status === 'active'"
        class="mb-6 bg-blue-50 border border-blue-200 rounded-xl p-4 flex items-center justify-between">
        <div>
          <p class="text-sm font-medium text-blue-800">{{ planLabel(subscription.plan) }} plan active</p>
          <p v-if="subscription.current_period_end" class="text-xs text-blue-600 mt-0.5">
            Renews {{ formatDate(subscription.current_period_end) }}
          </p>
        </div>
        <button @click="cancelPlan" class="text-xs text-red-500 hover:text-red-700 border border-red-200 rounded px-3 py-1.5">
          Cancel plan
        </button>
      </div>

      <!-- Solo / Org tab toggle -->
      <div class="flex gap-2 mb-6">
        <button @click="tab = 'solo'"
          class="px-5 py-2 rounded-xl text-sm font-semibold transition"
          :class="tab === 'solo' ? 'bg-slate-800 text-white' : 'bg-slate-100 text-slate-600 hover:bg-slate-200'">
          Solo Plans
        </button>
        <button @click="tab = 'org'"
          class="px-5 py-2 rounded-xl text-sm font-semibold transition"
          :class="tab === 'org' ? 'bg-slate-800 text-white' : 'bg-slate-100 text-slate-600 hover:bg-slate-200'">
          Organization Plans
        </button>
      </div>

      <!-- Solo plans -->
      <div v-if="tab === 'solo'" class="grid grid-cols-1 md:grid-cols-3 gap-6">
        <div v-for="(plan, key) in soloPlans" :key="key"
          class="bg-white border rounded-2xl p-6 flex flex-col relative"
          :class="key === 'pro' ? 'border-blue-400 shadow-lg shadow-blue-50' : 'border-slate-200'">
          <div v-if="key === 'pro'"
            class="absolute -top-3 left-1/2 -translate-x-1/2 bg-blue-600 text-white text-xs font-semibold px-3 py-0.5 rounded-full">
            Most Popular
          </div>
          <div v-if="key === 'creator'"
            class="absolute -top-3 left-1/2 -translate-x-1/2 bg-purple-600 text-white text-xs font-semibold px-3 py-0.5 rounded-full">
            Best Value
          </div>
          <div class="mb-4">
            <h2 class="text-lg font-bold text-slate-800">{{ plan.name }}</h2>
            <div class="mt-2 flex items-end gap-1">
              <span class="text-3xl font-bold text-slate-900">
                {{ plan.price === 0 ? 'Free' : '₹' + (plan.price / 100) }}
              </span>
              <span v-if="plan.price > 0" class="text-sm text-slate-400 mb-1">/month</span>
            </div>
          </div>
          <ul class="space-y-2.5 flex-1 mb-6">
            <li v-for="feature in plan.features" :key="feature"
              class="flex items-start gap-2 text-sm text-slate-600">
              <svg class="w-4 h-4 text-green-500 flex-shrink-0 mt-0.5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
              </svg>
              {{ feature }}
            </li>
          </ul>
          <button v-if="key === 'free'" disabled
            class="w-full text-sm py-2.5 rounded-xl font-medium bg-slate-100 text-slate-400 cursor-not-allowed">
            {{ currentPlan === 'free' ? 'Current plan' : 'Free tier' }}
          </button>
          <button v-else-if="currentPlan === key" disabled
            class="w-full text-sm py-2.5 rounded-xl font-medium bg-slate-100 text-slate-400 cursor-not-allowed">
            Current plan
          </button>
          <button v-else @click="subscribe(key)" :disabled="subscribing === key"
            class="w-full text-sm py-2.5 rounded-xl font-medium transition"
            :class="key === 'pro' ? 'bg-blue-600 hover:bg-blue-700 text-white' : 'bg-slate-800 hover:bg-slate-900 text-white'">
            {{ subscribing === key ? 'Processing…' : 'Subscribe' }}
          </button>
        </div>
      </div>

      <!-- Org plans -->
      <div v-if="tab === 'org'" class="grid grid-cols-1 md:grid-cols-3 gap-6">
        <div v-for="(plan, key) in orgPlans" :key="key"
          class="bg-white border rounded-2xl p-6 flex flex-col relative"
          :class="key === 'business' ? 'border-blue-400 shadow-lg shadow-blue-50' : 'border-slate-200'">
          <div v-if="key === 'business'"
            class="absolute -top-3 left-1/2 -translate-x-1/2 bg-blue-600 text-white text-xs font-semibold px-3 py-0.5 rounded-full">
            Most Popular
          </div>
          <div class="mb-4">
            <h2 class="text-lg font-bold text-slate-800">{{ plan.name }}</h2>
            <div class="mt-2 flex items-end gap-1">
              <span v-if="plan.price" class="text-3xl font-bold text-slate-900">₹{{ plan.price / 100 }}</span>
              <span v-else class="text-2xl font-bold text-slate-900">Custom</span>
              <span v-if="plan.price" class="text-sm text-slate-400 mb-1">/month</span>
            </div>
            <p v-if="key === 'enterprise'" class="text-xs text-slate-400 mt-1">
              ₹4,999 base + usage · calculated monthly
            </p>
          </div>
          <ul class="space-y-2.5 flex-1 mb-6">
            <li v-for="feature in plan.features" :key="feature"
              class="flex items-start gap-2 text-sm text-slate-600">
              <svg class="w-4 h-4 text-green-500 flex-shrink-0 mt-0.5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
              </svg>
              {{ feature }}
            </li>
          </ul>
          <button v-if="currentPlan === key" disabled
            class="w-full text-sm py-2.5 rounded-xl font-medium bg-slate-100 text-slate-400 cursor-not-allowed">
            Current plan
          </button>
          <button v-else-if="key === 'enterprise'" @click="contactEnterprise"
            class="w-full text-sm py-2.5 rounded-xl font-medium bg-slate-800 hover:bg-slate-900 text-white transition">
            Contact us
          </button>
          <button v-else @click="subscribe(key)" :disabled="subscribing === key"
            class="w-full text-sm py-2.5 rounded-xl font-medium transition"
            :class="key === 'business' ? 'bg-blue-600 hover:bg-blue-700 text-white' : 'bg-slate-800 hover:bg-slate-900 text-white'">
            {{ subscribing === key ? 'Processing…' : 'Subscribe' }}
          </button>
        </div>
      </div>

      <p class="text-center text-xs text-slate-400 mt-8">
        All plans include SSL, 99.9% uptime, and data export. Payments secured by Razorpay.
      </p>
    </div>
  </AdminLayout>
</template>

<script setup>
import { ref, computed } from 'vue'
import { router } from '@inertiajs/vue3'
import AdminLayout from '@/Layouts/AdminLayout.vue'

const props = defineProps({
  plans:        Object,
  currentPlan:  String,
  subscription: Object,
  razorpayKey:  String,
})

const tab         = ref(['team','business','enterprise'].includes(props.currentPlan) ? 'org' : 'solo')
const subscribing = ref(null)

const soloPlans = computed(() => Object.fromEntries(
  Object.entries(props.plans).filter(([, p]) => p.type === 'solo')
))
const orgPlans = computed(() => Object.fromEntries(
  Object.entries(props.plans).filter(([, p]) => p.type === 'org')
))

const planLabel = (key) => props.plans[key]?.name ?? key
const planBadge = (key) => ({
  'free':       'bg-slate-100 text-slate-600',
  'pro':        'bg-blue-100 text-blue-700',
  'creator':    'bg-purple-100 text-purple-700',
  'team':       'bg-teal-100 text-teal-700',
  'business':   'bg-blue-100 text-blue-700',
  'enterprise': 'bg-slate-800 text-white',
})[key] ?? 'bg-slate-100 text-slate-600'

const formatDate = (d) => new Date(d).toLocaleDateString('en-IN', { day: 'numeric', month: 'short', year: 'numeric' })

const contactEnterprise = () => {
  window.location.href = 'mailto:ksnsk2001@gmail.com?subject=Enterprise Plan Enquiry'
}

const subscribe = async (planKey) => {
  subscribing.value = planKey
  const plan = props.plans[planKey]

  try {
    const res = await fetch('/admin/billing/order', {
      method: 'POST',
      headers: {
        'Content-Type': 'application/json',
        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content ?? '',
        'Accept': 'application/json',
      },
      body: JSON.stringify({ plan: planKey }),
    })
    if (!res.ok) throw new Error('Order failed')
    const data = await res.json()

    const rzp = new window.Razorpay({
      key:         props.razorpayKey,
      order_id:    data.order_id,
      amount:      data.amount,
      currency:    data.currency,
      name:        'Portfolio CMS',
      description: `${plan.name} Plan`,
      theme:       { color: '#2563EB' },
      handler: (response) => {
        router.post('/admin/billing/verify', {
          razorpay_order_id:   response.razorpay_order_id,
          razorpay_payment_id: response.razorpay_payment_id,
          razorpay_signature:  response.razorpay_signature,
          plan:                planKey,
        }, {
          onSuccess: () => { subscribing.value = null },
          onError:   () => { subscribing.value = null },
        })
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
  if (!confirm('Cancel your subscription? Your plan stays active until the end of the billing period.')) return
  router.post('/admin/billing/cancel')
}
</script>
```

- [ ] **Step 2: Update BillingController to pass all 6 plans**

In `app/Http/Controllers/Admin/BillingController.php`, update `index()`:

```php
public function index(): Response
{
    $user = auth()->user();
    return Inertia::render('Admin/Billing/Index', [
        'plans'       => config('billing.plans'),
        'currentPlan' => $user->currentPlan(),
        'subscription'=> $user->activeSubscription,
        'razorpayKey' => config('billing.razorpay.key_id'),
    ]);
}
```

- [ ] **Step 3: Commit**

```bash
git add resources/js/Pages/Admin/Billing/Index.vue \
        app/Http/Controllers/Admin/BillingController.php
git commit -m "feat: 6-plan billing UI with Solo/Org tab toggle"
```

---

### Task 5: Build and verify

- [ ] **Step 1: Run build**

```bash
npm run build
```

Expected: `✓ built in Xs` with no errors.

- [ ] **Step 2: Clear config cache**

```bash
php artisan config:clear
```

- [ ] **Step 3: Verify billing page loads with 6 plans**

Navigate to `/admin/billing` — confirm Solo tab shows Free/Pro/Creator, Org tab shows Team/Business/Enterprise.

- [ ] **Step 4: Commit**

```bash
git add -A
git commit -m "feat: plans & billing overhaul complete"
git push
```
