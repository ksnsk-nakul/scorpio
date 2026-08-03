# EdTech Platform v1 Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Ingest the CourseWork `Courses/` catalog into Scorpio as a browsable, enrollable course platform, reusing the e-library's `Book→Chapter` architecture and the existing wallet system.

**Architecture:** `Course → Module → Topic → Material` mirrors `Book → Chapter` exactly (same status/slug/ownership conventions). A new `edtech:import-courses` artisan command parses the on-disk course folders directly (plain markdown/HTML, no external API needed) into these tables; a queued per-topic job then calls the existing `GeminiClient` to generate a slide deck. Public browsing mirrors `/library`'s routes/controller/Vue shape. Enrollment reuses the **existing** `User::debitWallet()` (see Correction to the approved spec, below) rather than a new service, and a small `NotificationSender` interface — bound to a no-op today — is called from inside `creditWallet()`/`debitWallet()` so every wallet change (top-up, enrollment, and anything else that touches the wallet later) is covered by one hook, ready for the separate Notification System spec to bind a real implementation to later.

**Tech Stack:** Laravel 13.16.1, PHP 8.4.23, SQLite, Inertia.js v2 + Vue 3, Pest.

**Correction to the approved spec:** [2026-08-03-edtech-platform-design.md](../specs/2026-08-03-edtech-platform-design.md) proposed a new `WalletService` to "consolidate logic currently inlined in `WalletTopUpController`." That premise was wrong — `app/Models/User.php:151-210` already has both `creditWallet()` (used by `WalletTopUpController::verify()`) and `debitWallet()` (already row-locks via `lockForUpdate()`, already throws `RuntimeException('Insufficient wallet balance.')` on insufficient funds, unused anywhere yet). There's nothing to consolidate — this plan has enrollment call `User::debitWallet()` directly, and puts the `NotificationSender` hook inside both `creditWallet()`/`debitWallet()` on `User` itself, which is strictly simpler than the spec's proposal and satisfies the same requirement (one call site per wallet change, ready for real notifications later).

---

### Task 1: Migrations

**Files:**
- Create: `database/migrations/2026_08_03_100001_create_courses_table.php`
- Create: `database/migrations/2026_08_03_100002_create_modules_table.php`
- Create: `database/migrations/2026_08_03_100003_create_topics_table.php`
- Create: `database/migrations/2026_08_03_100004_create_materials_table.php`
- Create: `database/migrations/2026_08_03_100005_create_pricing_tiers_table.php`
- Create: `database/migrations/2026_08_03_100006_create_enrollments_table.php`
- Create: `database/migrations/2026_08_03_100007_add_enrollment_to_wallet_transactions_category.php`

- [ ] **Step 1: Write the courses migration**

```php
<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('courses', function (Blueprint $table) {
            $table->id();
            $table->string('code')->unique();
            $table->string('title');
            $table->string('slug')->unique();
            $table->string('subtitle')->nullable();
            $table->text('description')->nullable();
            $table->string('status')->default('pending');
            $table->string('status_reason')->nullable();
            $table->string('source_path');
            $table->timestamp('imported_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('courses');
    }
};
```

- [ ] **Step 2: Write the modules migration**

```php
<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('modules', function (Blueprint $table) {
            $table->id();
            $table->foreignId('course_id')->constrained()->cascadeOnDelete();
            $table->string('title');
            $table->string('slug');
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();
            $table->index(['course_id', 'sort_order']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('modules');
    }
};
```

- [ ] **Step 3: Write the topics migration**

```php
<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('topics', function (Blueprint $table) {
            $table->id();
            $table->foreignId('module_id')->constrained()->cascadeOnDelete();
            $table->string('title');
            $table->string('slug');
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();
            $table->index(['module_id', 'sort_order']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('topics');
    }
};
```

- [ ] **Step 4: Write the materials migration**

```php
<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('materials', function (Blueprint $table) {
            $table->id();
            $table->foreignId('topic_id')->constrained()->cascadeOnDelete();
            $table->string('type'); // notes|task|slides|demo_problem|demo_try_it|demo_solution|video
            $table->longText('content')->nullable();
            $table->string('download_policy')->default('view_only'); // downloadable|view_only
            $table->string('status')->default('ready'); // ready|generating|not_generated|failed
            $table->timestamps();
            $table->unique(['topic_id', 'type']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('materials');
    }
};
```

- [ ] **Step 5: Write the pricing_tiers migration**

```php
<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('pricing_tiers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('course_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->unsignedBigInteger('price_inr_paise');
            $table->unsignedBigInteger('price_usd_cents');
            $table->text('description')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pricing_tiers');
    }
};
```

- [ ] **Step 6: Write the enrollments migration**

```php
<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('enrollments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('course_id')->constrained()->cascadeOnDelete();
            $table->foreignId('pricing_tier_id')->constrained()->cascadeOnDelete();
            $table->unsignedBigInteger('amount_paise_charged');
            $table->timestamp('enrolled_at');
            $table->timestamps();
            $table->unique(['user_id', 'course_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('enrollments');
    }
};
```

- [ ] **Step 7: Add `enrollment` to the wallet_transactions category enum**

SQLite doesn't enforce enum constraints at the DB level (Laravel's `enum()` column type on SQLite is just a `varchar` with a check constraint) — check the existing column first:

```bash
cd /Users/nakul/Herd/PortFolio/.worktrees/e-library-backend
sqlite3 database/database.sqlite ".schema wallet_transactions" | grep category
```

If it shows a `CHECK` constraint listing the old values, write a migration to recreate the column with the new value included (SQLite can't `ALTER COLUMN` a check constraint directly — rebuild via a temporary table is the standard approach, but simplest: since this is a `string` category column in practice — confirm the actual migration at `database/migrations/2026_07_05_100002_create_wallet_transactions_table.php` uses `$table->enum('category', [...])`, which on SQLite Laravel implements as a `CHECK` constraint):

```php
<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('wallet_transactions', function (Blueprint $table) {
            $table->string('category')->default('topup')->change();
        });
    }

    public function down(): void
    {
        Schema::table('wallet_transactions', function (Blueprint $table) {
            $table->enum('category', ['topup', 'subscription', 'refund', 'adjustment'])->default('topup')->change();
        });
    }
};
```

Changing `enum` to a plain `string` (rather than trying to list `'enrollment'` in a new enum) avoids needing `doctrine/dbal` for the SQLite enum rebuild and matches how `category` is actually used elsewhere (`Admin/WalletController.php` just filters by an arbitrary string, never validates against a fixed list) — the enum was over-constraining a value that's read/written as a plain string everywhere already.

- [ ] **Step 8: Run migrations and verify**

```bash
cd /Users/nakul/Herd/PortFolio/.worktrees/e-library-backend
php artisan migrate
```

Expected: all 7 new migrations run without error.

- [ ] **Step 9: Commit**

```bash
git add database/migrations/2026_08_03_*.php
git commit -m "feat: add EdTech platform tables (courses, modules, topics, materials, pricing tiers, enrollments)"
```

---

### Task 2: Models

**Files:**
- Create: `app/Models/Course.php`
- Create: `app/Models/Module.php`
- Create: `app/Models/Topic.php`
- Create: `app/Models/Material.php`
- Create: `app/Models/PricingTier.php`
- Create: `app/Models/Enrollment.php`
- Test: `tests/Unit/CourseModelsTest.php`

- [ ] **Step 1: Write the failing test**

```php
<?php

use App\Models\Course;
use App\Models\Enrollment;
use App\Models\Material;
use App\Models\Module;
use App\Models\PricingTier;
use App\Models\Topic;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('builds the full Course -> Module -> Topic -> Material chain with a unique slug', function () {
    $course = Course::create(['code' => 'C001-HTML-101', 'title' => 'Introduction to HTML', 'source_path' => '/tmp/x']);
    expect($course->slug)->toBe('introduction-to-html');

    $module = Module::create(['course_id' => $course->id, 'title' => 'HTML Fundamentals', 'slug' => 'html-fundamentals', 'sort_order' => 0]);
    $topic = Topic::create(['module_id' => $module->id, 'title' => 'Intro to HTML', 'slug' => 'intro-to-html', 'sort_order' => 0]);
    $material = Material::create(['topic_id' => $topic->id, 'type' => 'notes', 'content' => '# Notes', 'download_policy' => 'downloadable']);

    expect($course->modules)->toHaveCount(1)
        ->and($course->modules->first()->topics)->toHaveCount(1)
        ->and($course->modules->first()->topics->first()->materials)->toHaveCount(1)
        ->and($material->topic->id)->toBe($topic->id);
});

it('generates a unique slug when two courses would otherwise collide', function () {
    Course::create(['code' => 'C001-HTML-101', 'title' => 'Intro', 'source_path' => '/tmp/x']);
    $second = Course::create(['code' => 'C002-CSS-101', 'title' => 'Intro', 'source_path' => '/tmp/y']);

    expect($second->slug)->toBe('intro-2');
});

it('creates a pricing tier belonging to a course', function () {
    $course = Course::create(['code' => 'C001-HTML-101', 'title' => 'Intro to HTML', 'source_path' => '/tmp/x']);
    $tier = PricingTier::create([
        'course_id' => $course->id, 'name' => 'Self-Paced Pro',
        'price_inr_paise' => 599900, 'price_usd_cents' => 14900,
        'description' => 'Full notes, slides, videos, tasks, demo (all 20 topics), certificate',
    ]);

    expect($course->pricingTiers->first()->id)->toBe($tier->id);
});

it('creates an enrollment linking a user, course, and pricing tier', function () {
    $user = User::factory()->create();
    $course = Course::create(['code' => 'C001-HTML-101', 'title' => 'Intro to HTML', 'source_path' => '/tmp/x']);
    $tier = PricingTier::create(['course_id' => $course->id, 'name' => 'Pro', 'price_inr_paise' => 599900, 'price_usd_cents' => 14900]);

    $enrollment = Enrollment::create([
        'user_id' => $user->id, 'course_id' => $course->id, 'pricing_tier_id' => $tier->id,
        'amount_paise_charged' => 599900, 'enrolled_at' => now(),
    ]);

    expect($user->enrollments->first()->id)->toBe($enrollment->id)
        ->and($enrollment->course->id)->toBe($course->id)
        ->and($enrollment->pricingTier->id)->toBe($tier->id);
});
```

- [ ] **Step 2: Run test to verify it fails**

```bash
php artisan test --filter=CourseModelsTest
```

Expected: FAIL — `Class "App\Models\Course" not found`.

- [ ] **Step 3: Write the models**

`app/Models/Course.php`:
```php
<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

class Course extends Model
{
    protected $fillable = [
        'code', 'title', 'slug', 'subtitle', 'description',
        'status', 'status_reason', 'source_path', 'imported_at',
    ];

    protected $casts = ['imported_at' => 'datetime'];

    protected $attributes = ['status' => 'pending'];

    protected static function booted(): void
    {
        static::creating(function (Course $course) {
            if (! $course->slug) {
                $course->slug = static::uniqueSlug($course->title);
            }
        });
    }

    public static function uniqueSlug(string $title, ?int $excludeId = null): string
    {
        $base = Str::slug($title) ?: 'course';
        $slug = $base;
        $i = 2;

        while (static::where('slug', $slug)->when($excludeId, fn ($q) => $q->where('id', '!=', $excludeId))->exists()) {
            $slug = "{$base}-{$i}";
            $i++;
        }

        return $slug;
    }

    public function modules(): HasMany
    {
        return $this->hasMany(Module::class)->orderBy('sort_order');
    }

    public function pricingTiers(): HasMany
    {
        return $this->hasMany(PricingTier::class);
    }

    public function enrollments(): HasMany
    {
        return $this->hasMany(Enrollment::class);
    }

    public function isReady(): bool
    {
        return $this->status === 'ready';
    }
}
```

`app/Models/Module.php`:
```php
<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Module extends Model
{
    protected $fillable = ['course_id', 'title', 'slug', 'sort_order'];

    public function course(): BelongsTo
    {
        return $this->belongsTo(Course::class);
    }

    public function topics(): HasMany
    {
        return $this->hasMany(Topic::class)->orderBy('sort_order');
    }
}
```

`app/Models/Topic.php`:
```php
<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Topic extends Model
{
    protected $fillable = ['module_id', 'title', 'slug', 'sort_order'];

    public function module(): BelongsTo
    {
        return $this->belongsTo(Module::class);
    }

    public function materials(): HasMany
    {
        return $this->hasMany(Material::class);
    }

    public function material(string $type): ?Material
    {
        return $this->materials->firstWhere('type', $type);
    }
}
```

`app/Models/Material.php`:
```php
<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Material extends Model
{
    protected $fillable = ['topic_id', 'type', 'content', 'download_policy', 'status'];

    protected $attributes = ['download_policy' => 'view_only', 'status' => 'ready'];

    public function topic(): BelongsTo
    {
        return $this->belongsTo(Topic::class);
    }

    public function isDownloadable(): bool
    {
        return $this->download_policy === 'downloadable';
    }
}
```

`app/Models/PricingTier.php`:
```php
<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PricingTier extends Model
{
    protected $fillable = ['course_id', 'name', 'price_inr_paise', 'price_usd_cents', 'description'];

    public function course(): BelongsTo
    {
        return $this->belongsTo(Course::class);
    }

    public function enrollments(): HasMany
    {
        return $this->hasMany(Enrollment::class);
    }
}
```

`app/Models/Enrollment.php`:
```php
<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Enrollment extends Model
{
    protected $fillable = ['user_id', 'course_id', 'pricing_tier_id', 'amount_paise_charged', 'enrolled_at'];

    protected $casts = ['enrolled_at' => 'datetime'];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function course(): BelongsTo
    {
        return $this->belongsTo(Course::class);
    }

    public function pricingTier(): BelongsTo
    {
        return $this->belongsTo(PricingTier::class);
    }
}
```

Add the inverse relation on `User` — modify `app/Models/User.php`, adding near `walletTransactions()`:
```php
    public function enrollments(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(Enrollment::class);
    }
```

- [ ] **Step 4: Run test to verify it passes**

```bash
php artisan test --filter=CourseModelsTest
```

Expected: PASS, 4/4.

- [ ] **Step 5: Commit**

```bash
git add app/Models/Course.php app/Models/Module.php app/Models/Topic.php app/Models/Material.php app/Models/PricingTier.php app/Models/Enrollment.php app/Models/User.php tests/Unit/CourseModelsTest.php
git commit -m "feat: add Course/Module/Topic/Material/PricingTier/Enrollment models"
```

---

### Task 3: Course fixture builder (test helper)

**Files:**
- Create: `tests/support/CourseFixtureBuilder.php`

- [ ] **Step 1: Write the fixture builder**

Mirrors `tests/support/EpubFixtureBuilder.php`'s role: builds a minimal, real-on-disk fake course folder so import tests never touch `/Users/nakul/Learn/CourseWork/Courses/` (a path that won't exist in CI or on another machine).

```php
<?php

namespace Tests\Support;

class CourseFixtureBuilder
{
    /**
     * @param array<int, array{title: string, topics: array<int, array{title: string, notes?: string, task?: string}>}> $modules
     * @param array<int, array{name: string, price_inr: string, price_usd: string, included: string}> $tiers
     */
    public static function build(
        string $tmpDir,
        string $code,
        string $courseTitle,
        string $description,
        array $modules,
        array $tiers = [],
    ): string {
        $courseDir = "{$tmpDir}/{$code}-" . \Illuminate\Support\Str::slug($courseTitle);
        @mkdir("{$courseDir}/00-course-info", 0777, true);
        @mkdir("{$courseDir}/selfpaced", 0777, true);

        file_put_contents("{$courseDir}/00-course-info/intro-and-summary.md", "# Intro\n\n{$description}\n");

        $tierRows = implode("\n", array_map(
            fn ($t) => "| {$t['name']} | {$t['included']} | {$t['price_inr']} | {$t['price_usd']} |",
            $tiers
        ));
        file_put_contents("{$courseDir}/00-course-info/pricing-and-format.md", <<<MD
        # Pricing & Format

        ## Tiers

        | Tier | Included | Price (India) | Price (International) |
        |---|---|---|---|
        {$tierRows}
        MD);

        foreach ($modules as $mi => $module) {
            $mn = str_pad((string) ($mi + 1), 2, '0', STR_PAD_LEFT);
            $moduleDir = "{$courseDir}/selfpaced/module-{$mn}-" . \Illuminate\Support\Str::slug($module['title']);
            @mkdir($moduleDir, 0777, true);
            file_put_contents("{$moduleDir}/README.md", "# {$module['title']}\n");

            foreach ($module['topics'] as $ti => $topic) {
                $tn = str_pad((string) ($ti + 1), 2, '0', STR_PAD_LEFT);
                $topicDir = "{$moduleDir}/topic-{$tn}-" . \Illuminate\Support\Str::slug($topic['title']);
                @mkdir("{$topicDir}/notes", 0777, true);
                @mkdir("{$topicDir}/tasks", 0777, true);
                @mkdir("{$topicDir}/demo/problem", 0777, true);
                @mkdir("{$topicDir}/demo/try-it", 0777, true);
                @mkdir("{$topicDir}/demo/solution", 0777, true);

                file_put_contents("{$topicDir}/README.md", "# {$topic['title']}\n");
                file_put_contents("{$topicDir}/notes/notes.md", $topic['notes'] ?? "# {$topic['title']}\n\nSome notes.");
                file_put_contents("{$topicDir}/tasks/task.md", $topic['task'] ?? "# Task\n\nDo the thing.");
                file_put_contents("{$topicDir}/demo/problem/index.html", '<html><body>problem</body></html>');
                file_put_contents("{$topicDir}/demo/try-it/index.html", '<html><body>try-it</body></html>');
                file_put_contents("{$topicDir}/demo/solution/index.html", '<html><body>solution</body></html>');
            }
        }

        return $courseDir;
    }

    public static function buildIndex(string $tmpDir, array $rows): void
    {
        $lines = implode("\n", array_map(
            fn ($r) => "| {$r['code']} | {$r['title']} | `{$r['slug']}` | Language | Standalone | Generated, content complete |",
            $rows
        ));
        file_put_contents("{$tmpDir}/COURSE-INDEX.md", <<<MD
        # Course Index

        | Code | Course | Slug | Layer | Coupling | Status |
        |---|---|---|---|---|---|
        {$lines}
        MD);
    }
}
```

- [ ] **Step 2: Commit**

```bash
git add tests/support/CourseFixtureBuilder.php
git commit -m "test: add CourseFixtureBuilder for EdTech import tests"
```

---

### Task 4: CourseImportService — course-level metadata + pricing

**Files:**
- Create: `app/Services/CourseImportService.php`
- Test: `tests/Feature/CourseImportServiceTest.php`

- [ ] **Step 1: Write the failing test**

```php
<?php

use App\Models\Course;
use App\Services\CourseImportService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\CourseFixtureBuilder;

uses(RefreshDatabase::class);

it('imports course metadata and pricing tiers, converting rupee/dollar strings to paise/cents', function () {
    $tmp = sys_get_temp_dir() . '/edtech-test-' . uniqid();
    $courseDir = CourseFixtureBuilder::build(
        tmpDir: $tmp,
        code: 'C001-HTML-101',
        courseTitle: 'Introduction to HTML',
        description: 'Learn HTML from scratch.',
        modules: [],
        tiers: [
            ['name' => 'Free preview', 'included' => 'Sample topic', 'price_inr' => 'Free', 'price_usd' => 'Free'],
            ['name' => 'Self-Paced Pro', 'included' => 'Full course', 'price_inr' => '₹5,999', 'price_usd' => '$149'],
        ],
    );

    $course = (new CourseImportService())->importCourse($courseDir, 'C001-HTML-101');

    expect($course->code)->toBe('C001-HTML-101')
        ->and($course->title)->toBe('Introduction to HTML')
        ->and($course->description)->toBe('Learn HTML from scratch.')
        ->and($course->status)->toBe('ready')
        ->and($course->pricingTiers)->toHaveCount(2);

    $free = $course->pricingTiers->firstWhere('name', 'Free preview');
    expect($free->price_inr_paise)->toBe(0)->and($free->price_usd_cents)->toBe(0);

    $pro = $course->pricingTiers->firstWhere('name', 'Self-Paced Pro');
    expect($pro->price_inr_paise)->toBe(599900)->and($pro->price_usd_cents)->toBe(14900);
});

it('re-importing the same course code updates it in place instead of duplicating', function () {
    $tmp = sys_get_temp_dir() . '/edtech-test-' . uniqid();
    $courseDir = CourseFixtureBuilder::build($tmp, 'C001-HTML-101', 'Introduction to HTML', 'v1 description', [], []);

    $service = new CourseImportService();
    $first = $service->importCourse($courseDir, 'C001-HTML-101');

    file_put_contents("{$courseDir}/00-course-info/intro-and-summary.md", "# Intro\n\nv2 description\n");
    $second = $service->importCourse($courseDir, 'C001-HTML-101');

    expect($second->id)->toBe($first->id)
        ->and($second->description)->toBe('v2 description')
        ->and(Course::count())->toBe(1);
});
```

- [ ] **Step 2: Run test to verify it fails**

```bash
php artisan test --filter=CourseImportServiceTest
```

Expected: FAIL — `Class "App\Services\CourseImportService" not found`.

- [ ] **Step 3: Write the implementation**

```php
<?php
namespace App\Services;

use App\Models\Course;
use App\Models\PricingTier;
use Throwable;

class CourseImportService
{
    public function importCourse(string $courseDir, string $code): Course
    {
        $title = $this->extractTitle($courseDir) ?? $code;
        $existing = Course::where('code', $code)->first();

        $course = Course::updateOrCreate(
            ['code' => $code],
            [
                'title' => $title,
                'slug' => $existing?->slug ?? Course::uniqueSlug($title),
                'description' => $this->extractDescription($courseDir),
                'source_path' => $courseDir,
                'status' => 'importing',
                'status_reason' => null,
            ]
        );

        try {
            $this->importPricingTiers($course, $courseDir);
            // Task 5 adds a call to $this->importModules($course, $courseDir); here.

            $course->status = 'ready';
            $course->imported_at = now();
            $course->save();
        } catch (Throwable $e) {
            // One malformed course must not abort the whole catalog import — isolate
            // the failure onto this course's own row (mirrors Book's pending/processing/
            // ready/failed lifecycle) so ImportCoursesCommand's loop can continue to the
            // next course, and the admin UI shows exactly which course needs attention.
            $course->update(['status' => 'failed', 'status_reason' => $e->getMessage()]);
        }

        return $course->fresh('pricingTiers');
    }

    private function extractTitle(string $courseDir): ?string
    {
        $path = "{$courseDir}/00-course-info/intro-and-summary.md";
        if (! is_file($path)) {
            return null;
        }

        $line = collect(file($path))->first(fn ($l) => str_starts_with(trim($l), '# '));
        return $line ? trim(substr(trim($line), 2)) : null;
    }

    private function extractDescription(string $courseDir): ?string
    {
        $path = "{$courseDir}/00-course-info/intro-and-summary.md";
        if (! is_file($path)) {
            return null;
        }

        $lines = array_filter(
            array_map('trim', file($path)),
            fn ($l) => $l !== '' && ! str_starts_with($l, '#')
        );

        return $lines ? implode(' ', $lines) : null;
    }

    private function importPricingTiers(Course $course, string $courseDir): void
    {
        $path = "{$courseDir}/00-course-info/pricing-and-format.md";
        if (! is_file($path)) {
            return;
        }

        $content = file_get_contents($path);
        if (! preg_match('/## Tiers\s*\n\s*\n(\|.+\|(?:\n\|.+\|)*)/', $content, $m)) {
            return;
        }

        $rows = array_slice(explode("\n", trim($m[1])), 2); // drop header + separator rows

        $course->pricingTiers()->delete();

        foreach ($rows as $row) {
            $cells = array_map('trim', explode('|', trim($row, '| ')));
            if (count($cells) < 4) {
                continue;
            }

            [$name, $included, $inr, $usd] = $cells;

            PricingTier::create([
                'course_id' => $course->id,
                'name' => $name,
                'description' => $included,
                'price_inr_paise' => $this->parseMoneyToMinorUnits($inr),
                'price_usd_cents' => $this->parseMoneyToMinorUnits($usd),
            ]);
        }
    }

    private function parseMoneyToMinorUnits(string $raw): int
    {
        if (stripos($raw, 'free') !== false) {
            return 0;
        }

        $digits = preg_replace('/[^0-9.]/', '', $raw);
        return (int) round(((float) $digits) * 100);
    }
}
```

- [ ] **Step 4: Run test to verify it passes**

```bash
php artisan test --filter=CourseImportServiceTest
```

Expected: PASS, 2/2.

- [ ] **Step 5: Commit**

```bash
git add app/Services/CourseImportService.php tests/Feature/CourseImportServiceTest.php
git commit -m "feat: add CourseImportService for course metadata and pricing-tier ingestion"
```

---

### Task 5: CourseImportService — modules, topics, materials

**Files:**
- Modify: `app/Services/CourseImportService.php`
- Test: `tests/Feature/CourseImportServiceTest.php`

- [ ] **Step 1: Write the failing test**

Append to `tests/Feature/CourseImportServiceTest.php`:

```php
it('imports modules, topics, and their materials with correct download policy', function () {
    $tmp = sys_get_temp_dir() . '/edtech-test-' . uniqid();
    $courseDir = CourseFixtureBuilder::build(
        tmpDir: $tmp,
        code: 'C001-HTML-101',
        courseTitle: 'Introduction to HTML',
        description: 'Learn HTML.',
        modules: [
            ['title' => 'HTML Fundamentals', 'topics' => [
                ['title' => 'Intro to HTML', 'notes' => '# Intro to HTML notes'],
                ['title' => 'Document Structure', 'notes' => '# Doc structure notes'],
            ]],
        ],
    );

    $course = (new CourseImportService())->importCourse($courseDir, 'C001-HTML-101');

    expect($course->modules)->toHaveCount(1);
    $module = $course->modules->first();
    expect($module->title)->toBe('HTML Fundamentals')
        ->and($module->sort_order)->toBe(0)
        ->and($module->topics)->toHaveCount(2);

    $topic = $module->topics->first();
    expect($topic->title)->toBe('Intro to HTML')
        ->and($topic->sort_order)->toBe(0);

    $notes = $topic->material('notes');
    expect($notes->content)->toContain('Intro to HTML notes')
        ->and($notes->download_policy)->toBe('downloadable')
        ->and($notes->status)->toBe('ready');

    $task = $topic->material('task');
    expect($task->download_policy)->toBe('view_only');

    $demoProblem = $topic->material('demo_problem');
    expect($demoProblem->download_policy)->toBe('downloadable')
        ->and($demoProblem->content)->toContain('problem');

    $demoTryIt = $topic->material('demo_try_it');
    expect($demoTryIt->download_policy)->toBe('view_only');

    $slides = $topic->material('slides');
    expect($slides->status)->toBe('generating');

    $video = $topic->material('video');
    expect($video->status)->toBe('not_generated');
});

it('re-importing updates existing topics in place rather than duplicating', function () {
    $tmp = sys_get_temp_dir() . '/edtech-test-' . uniqid();
    $courseDir = CourseFixtureBuilder::build(
        $tmp, 'C001-HTML-101', 'Introduction to HTML', 'desc',
        modules: [['title' => 'Fundamentals', 'topics' => [['title' => 'Intro', 'notes' => 'v1 notes']]]],
    );

    $service = new CourseImportService();
    $service->importCourse($courseDir, 'C001-HTML-101');

    $topicDir = "{$courseDir}/selfpaced/module-01-fundamentals/topic-01-intro";
    file_put_contents("{$topicDir}/notes/notes.md", 'v2 notes');
    $course = $service->importCourse($courseDir, 'C001-HTML-101');

    expect(\App\Models\Topic::count())->toBe(1);
    expect($course->modules->first()->topics->first()->material('notes')->content)->toBe('v2 notes');
});

it('marks the course as failed with a status_reason, without throwing, when importing modules errors', function () {
    $tmp = sys_get_temp_dir() . '/edtech-test-' . uniqid();
    $courseDir = CourseFixtureBuilder::build($tmp, 'C001-HTML-101', 'Introduction to HTML', 'desc', []);

    $service = Mockery::mock(CourseImportService::class)->makePartial();
    $service->shouldReceive('importModules')->once()->andThrow(new RuntimeException('disk read error'));

    $course = $service->importCourse($courseDir, 'C001-HTML-101');

    expect($course->status)->toBe('failed')
        ->and($course->status_reason)->toBe('disk read error');
});
```

- [ ] **Step 2: Run test to verify it fails**

```bash
php artisan test --filter=CourseImportServiceTest
```

Expected: FAIL — modules/topics not created (only metadata import exists so far).

- [ ] **Step 3: Extend the implementation**

Add to `app/Services/CourseImportService.php`, imports at top:

```php
use App\Jobs\GenerateTopicSlidesJob;
use App\Models\Material;
use App\Models\Module;
use App\Models\Topic;
use Illuminate\Support\Str;
```

In `importCourse()`, replace the comment line `// Task 5 adds a call to $this->importModules($course, $courseDir); here.` with the real call:

```php
            $this->importModules($course, $courseDir);
```

Add these methods:

```php
    protected function importModules(Course $course, string $courseDir): void
    {
        $selfpacedDir = "{$courseDir}/selfpaced";
        if (! is_dir($selfpacedDir)) {
            return;
        }

        $moduleDirs = collect(glob("{$selfpacedDir}/module-*", GLOB_ONLYDIR))->sort()->values();

        foreach ($moduleDirs as $index => $moduleDir) {
            $slug = $this->slugFromDirName(basename($moduleDir));
            $title = $this->extractH1($moduleDir . '/README.md') ?? Str::headline($slug);

            $module = Module::updateOrCreate(
                ['course_id' => $course->id, 'slug' => $slug],
                ['title' => $title, 'sort_order' => $index]
            );

            $this->importTopics($module, $moduleDir);
        }
    }

    private function importTopics(Module $module, string $moduleDir): void
    {
        $topicDirs = collect(glob("{$moduleDir}/topic-*", GLOB_ONLYDIR))->sort()->values();

        foreach ($topicDirs as $index => $topicDir) {
            $slug = $this->slugFromDirName(basename($topicDir));
            $title = $this->extractH1($topicDir . '/README.md') ?? Str::headline($slug);

            $topic = Topic::updateOrCreate(
                ['module_id' => $module->id, 'slug' => $slug],
                ['title' => $title, 'sort_order' => $index]
            );

            $this->importMaterials($topic, $topicDir);
        }
    }

    private function importMaterials(Topic $topic, string $topicDir): void
    {
        $files = [
            'notes' => ["{$topicDir}/notes/notes.md", 'downloadable'],
            'task' => ["{$topicDir}/tasks/task.md", 'view_only'],
            'demo_problem' => [$this->firstDemoFile("{$topicDir}/demo/problem"), 'downloadable'],
            'demo_try_it' => [$this->firstDemoFile("{$topicDir}/demo/try-it"), 'view_only'],
            'demo_solution' => [$this->firstDemoFile("{$topicDir}/demo/solution"), 'view_only'],
        ];

        foreach ($files as $type => [$path, $policy]) {
            if (! $path || ! is_file($path)) {
                continue;
            }

            Material::updateOrCreate(
                ['topic_id' => $topic->id, 'type' => $type],
                ['content' => file_get_contents($path), 'download_policy' => $policy, 'status' => 'ready']
            );
        }

        $slides = Material::updateOrCreate(
            ['topic_id' => $topic->id, 'type' => 'slides'],
            ['download_policy' => 'view_only', 'status' => 'generating']
        );
        GenerateTopicSlidesJob::dispatch($topic);

        Material::updateOrCreate(
            ['topic_id' => $topic->id, 'type' => 'video'],
            ['download_policy' => 'view_only', 'status' => 'not_generated']
        );
    }

    private function firstDemoFile(string $dir): ?string
    {
        $files = glob("{$dir}/*.html") ?: glob("{$dir}/*");
        $files = array_filter($files ?: [], fn ($f) => basename($f) !== 'README.md');
        return $files ? array_values($files)[0] : null;
    }

    private function extractH1(string $path): ?string
    {
        if (! is_file($path)) {
            return null;
        }

        $line = collect(file($path))->first(fn ($l) => str_starts_with(trim($l), '# '));
        return $line ? trim(substr(trim($line), 2)) : null;
    }

    private function slugFromDirName(string $dirName): string
    {
        // "module-01-html-fundamentals" -> "html-fundamentals"; "topic-03-tables" -> "tables"
        return preg_replace('/^(module|topic)-\d+-/', '', $dirName);
    }
```

Note: `GenerateTopicSlidesJob` doesn't exist yet — Task 6 creates it. This task's test will fail at the `dispatch()` call until then unless the test fakes the queue. Add `Queue::fake();` to both new tests' setup (add a `beforeEach` at the top of the test file, alongside the existing `uses(RefreshDatabase::class);`):

```php
use Illuminate\Support\Facades\Queue;

beforeEach(fn () => Queue::fake());
```

- [ ] **Step 4: Run test to verify it passes**

```bash
php artisan test --filter=CourseImportServiceTest
```

Expected: PASS, 5/5 (all tests in the file, including Task 4's).

- [ ] **Step 5: Commit**

```bash
git add app/Services/CourseImportService.php tests/Feature/CourseImportServiceTest.php
git commit -m "feat: import modules, topics, and materials in CourseImportService"
```

---

### Task 6: GenerateTopicSlidesJob

**Files:**
- Create: `app/Jobs/GenerateTopicSlidesJob.php`
- Test: `tests/Feature/GenerateTopicSlidesJobTest.php`

- [ ] **Step 1: Write the failing test**

```php
<?php

use App\Jobs\GenerateTopicSlidesJob;
use App\Models\Course;
use App\Models\Material;
use App\Models\Module;
use App\Models\Topic;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;

uses(RefreshDatabase::class);

function makeTopicWithNotes(string $notes): Topic
{
    $course = Course::create(['code' => 'C001-HTML-101', 'title' => 'Intro to HTML', 'source_path' => '/tmp/x']);
    $module = Module::create(['course_id' => $course->id, 'title' => 'Fundamentals', 'slug' => 'fundamentals', 'sort_order' => 0]);
    $topic = Topic::create(['module_id' => $module->id, 'title' => 'Intro', 'slug' => 'intro', 'sort_order' => 0]);
    Material::create(['topic_id' => $topic->id, 'type' => 'notes', 'content' => $notes, 'download_policy' => 'downloadable', 'status' => 'ready']);
    Material::create(['topic_id' => $topic->id, 'type' => 'slides', 'download_policy' => 'view_only', 'status' => 'generating']);
    return $topic;
}

it('generates a slide deck from notes and stores it on the slides material', function () {
    $topic = makeTopicWithNotes('# What is HTML\n\nHTML is a markup language.');

    Http::fake([
        'generativelanguage.googleapis.com/*generateContent*' => Http::response([
            'candidates' => [['content' => ['parts' => [['text' => "# What is HTML\n- Markup language\n---\n# Summary\n- Structure not style"]]]]],
        ], 200),
    ]);

    (new GenerateTopicSlidesJob($topic))->handle(app(\App\Services\GeminiClient::class));

    $slides = $topic->fresh()->material('slides');
    expect($slides->status)->toBe('ready')
        ->and($slides->content)->toContain('---')
        ->and($slides->content)->toContain('Markup language');
});

it('marks the slides material as failed, without throwing, when Gemini errors', function () {
    $topic = makeTopicWithNotes('# Notes');

    Http::fake(['generativelanguage.googleapis.com/*' => Http::response(['error' => ['message' => 'quota exceeded']], 429)]);

    (new GenerateTopicSlidesJob($topic))->handle(app(\App\Services\GeminiClient::class));

    expect($topic->fresh()->material('slides')->status)->toBe('failed');
});

it('does nothing if the topic has no notes material yet', function () {
    $course = Course::create(['code' => 'C001-HTML-101', 'title' => 'Intro', 'source_path' => '/tmp/x']);
    $module = Module::create(['course_id' => $course->id, 'title' => 'F', 'slug' => 'f', 'sort_order' => 0]);
    $topic = Topic::create(['module_id' => $module->id, 'title' => 'T', 'slug' => 't', 'sort_order' => 0]);
    Material::create(['topic_id' => $topic->id, 'type' => 'slides', 'download_policy' => 'view_only', 'status' => 'generating']);

    Http::fake();

    (new GenerateTopicSlidesJob($topic))->handle(app(\App\Services\GeminiClient::class));

    expect($topic->fresh()->material('slides')->status)->toBe('failed');
    Http::assertNothingSent();
});
```

- [ ] **Step 2: Run test to verify it fails**

```bash
php artisan test --filter=GenerateTopicSlidesJobTest
```

Expected: FAIL — `Class "App\Jobs\GenerateTopicSlidesJob" not found`.

- [ ] **Step 3: Write the implementation**

```php
<?php
namespace App\Jobs;

use App\Models\Material;
use App\Models\Topic;
use App\Services\GeminiClient;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Throwable;

class GenerateTopicSlidesJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(public Topic $topic) {}

    public function handle(GeminiClient $gemini): void
    {
        $slides = $this->topic->material('slides');
        if (! $slides) {
            return;
        }

        $notes = $this->topic->material('notes');
        if (! $notes || ! trim((string) $notes->content)) {
            $slides->update(['status' => 'failed']);
            return;
        }

        $prompt = <<<PROMPT
        Read this topic's notes below. Summarize it into a markdown slide deck for
        a beginner-friendly coding course. Rules:
        - One slide per major concept or section heading in the notes, in the same order.
        - Each slide: a short title line, then 3-6 bullet points, plain language.
        - Include short code snippets from the notes verbatim where they illustrate
          a concept - don't invent new examples.
        - Separate slides with a line containing exactly "---".
        - Output ONLY the slide deck, no commentary before or after.

        Notes:
        {$notes->content}
        PROMPT;

        try {
            $deck = $gemini->generate($prompt);
            $slides->update(['content' => $deck, 'status' => 'ready']);
        } catch (Throwable $e) {
            Log::warning('GenerateTopicSlidesJob: failed to generate slides', [
                'topic_id' => $this->topic->id,
                'error' => $e->getMessage(),
            ]);
            $slides->update(['status' => 'failed']);
        }
    }
}
```

- [ ] **Step 4: Run test to verify it passes**

```bash
php artisan test --filter=GenerateTopicSlidesJobTest
```

Expected: PASS, 3/3.

- [ ] **Step 5: Re-run Task 5's test to confirm no regression now that the job class exists**

```bash
php artisan test --filter=CourseImportServiceTest
```

Expected: PASS, 4/4.

- [ ] **Step 6: Commit**

```bash
git add app/Jobs/GenerateTopicSlidesJob.php tests/Feature/GenerateTopicSlidesJobTest.php
git commit -m "feat: add GenerateTopicSlidesJob for Gemini-generated slide decks"
```

---

### Task 7: `edtech:import-courses` artisan command

**Files:**
- Create: `app/Console/Commands/ImportCoursesCommand.php`
- Test: `tests/Feature/ImportCoursesCommandTest.php`

- [ ] **Step 1: Write the failing test**

```php
<?php

use App\Models\Course;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\Support\CourseFixtureBuilder;

uses(RefreshDatabase::class);
beforeEach(fn () => Queue::fake());

it('imports every course listed as content-complete in COURSE-INDEX.md', function () {
    $tmp = sys_get_temp_dir() . '/edtech-cmd-test-' . uniqid();
    mkdir($tmp, 0777, true);

    CourseFixtureBuilder::build($tmp, 'C001-HTML-101', 'Introduction to HTML', 'HTML desc', [
        ['title' => 'Fundamentals', 'topics' => [['title' => 'Intro']]],
    ]);
    CourseFixtureBuilder::build($tmp, 'C002-CSS-101', 'Introduction to CSS', 'CSS desc', [
        ['title' => 'Fundamentals', 'topics' => [['title' => 'Selectors']]],
    ]);
    CourseFixtureBuilder::buildIndex($tmp, [
        ['code' => 'C001-HTML-101', 'title' => 'Introduction to HTML', 'slug' => 'C001-HTML-101-introduction-to-html'],
        ['code' => 'C002-CSS-101', 'title' => 'Introduction to CSS', 'slug' => 'C002-CSS-101-introduction-to-css'],
    ]);

    $this->artisan('edtech:import-courses', ['path' => $tmp])->assertSuccessful();

    expect(Course::count())->toBe(2)
        ->and(Course::where('code', 'C001-HTML-101')->first()->status)->toBe('ready');
});

it('skips courses whose COURSE-INDEX.md status is not content-complete', function () {
    $tmp = sys_get_temp_dir() . '/edtech-cmd-test-' . uniqid();
    mkdir($tmp, 0777, true);

    CourseFixtureBuilder::buildIndex($tmp, [
        ['code' => 'C099-PLANNED-201', 'title' => 'Not Built Yet', 'slug' => 'not-built'],
    ]);
    // Overwrite the fixture's status column to something not "content complete":
    file_put_contents("{$tmp}/COURSE-INDEX.md", str_replace(
        'Generated, content complete', 'Planned', file_get_contents("{$tmp}/COURSE-INDEX.md")
    ));

    $this->artisan('edtech:import-courses', ['path' => $tmp])->assertSuccessful();

    expect(Course::count())->toBe(0);
});
```

- [ ] **Step 2: Run test to verify it fails**

```bash
php artisan test --filter=ImportCoursesCommandTest
```

Expected: FAIL — command not defined.

- [ ] **Step 3: Write the implementation**

```php
<?php
namespace App\Console\Commands;

use App\Services\CourseImportService;
use Illuminate\Console\Command;

class ImportCoursesCommand extends Command
{
    protected $signature = 'edtech:import-courses {path : Path to the Courses directory containing COURSE-INDEX.md}';

    protected $description = 'Import every content-complete course from a CourseWork-style Courses directory into the database.';

    public function handle(CourseImportService $service): int
    {
        $path = rtrim($this->argument('path'), '/');
        $indexPath = "{$path}/COURSE-INDEX.md";

        if (! is_file($indexPath)) {
            $this->error("No COURSE-INDEX.md found at {$indexPath}");
            return self::FAILURE;
        }

        $rows = $this->parseIndexRows(file_get_contents($indexPath));
        $imported = 0;
        $skipped = 0;

        foreach ($rows as $row) {
            if (! str_contains($row['status'], 'content complete')) {
                $skipped++;
                continue;
            }

            $courseDir = collect(glob("{$path}/{$row['code']}-*", GLOB_ONLYDIR))->first();
            if (! $courseDir) {
                $this->warn("No folder found for {$row['code']}, skipping.");
                $skipped++;
                continue;
            }

            $this->info("Importing {$row['code']}: {$row['title']}");
            $service->importCourse($courseDir, $row['code']);
            $imported++;
        }

        $this->info("Imported: {$imported}, Skipped: {$skipped}");
        return self::SUCCESS;
    }

    /** @return array<int, array{code: string, title: string, status: string}> */
    private function parseIndexRows(string $content): array
    {
        if (! preg_match('/## Catalog\s*\n\s*### Generated\s*\n.*?\n\|.+\|\n\|[-| ]+\|\n((?:\|.+\|\n?)+)/s', $content, $m)) {
            return [];
        }

        $rows = [];
        foreach (explode("\n", trim($m[1])) as $line) {
            $cells = array_map('trim', explode('|', trim($line, '| ')));
            if (count($cells) < 6) {
                continue;
            }
            [$code, $title, , , , $status] = $cells;
            $rows[] = ['code' => $code, 'title' => $title, 'status' => $status];
        }

        return $rows;
    }
}
```

- [ ] **Step 4: Run test to verify it passes**

```bash
php artisan test --filter=ImportCoursesCommandTest
```

Expected: PASS, 2/2. If the `## Catalog` regex doesn't match the real `COURSE-INDEX.md` table shape, adjust the regex to match — verify directly against the real file before considering this step done:

```bash
php artisan tinker --execute="
\$rows = (new ReflectionMethod(App\Console\Commands\ImportCoursesCommand::class, 'parseIndexRows'))->getClosure(new App\Console\Commands\ImportCoursesCommand())(file_get_contents('/Users/nakul/Learn/CourseWork/Courses/COURSE-INDEX.md'));
echo count(\$rows) . ' rows parsed' . PHP_EOL;
print_r(\$rows);
"
```

Expected: 7 rows (C001 through C007), all with status containing "content complete".

- [ ] **Step 5: Commit**

```bash
git add app/Console/Commands/ImportCoursesCommand.php tests/Feature/ImportCoursesCommandTest.php
git commit -m "feat: add edtech:import-courses artisan command"
```

---

### Task 8: Public routes + course listing page

**Files:**
- Create: `app/Http/Controllers/CourseController.php`
- Create: `resources/js/Pages/Public/EdTech/Index.vue`
- Modify: `routes/web.php`
- Test: `tests/Feature/CourseBrowsingTest.php`

- [ ] **Step 1: Write the failing test**

```php
<?php

use App\Models\Course;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('lists only ready courses on the public index, paginated', function () {
    Course::factory()->count(3)->create(['status' => 'ready']);
    Course::factory()->create(['status' => 'pending']);

    $response = $this->get('/courses');

    $response->assertOk();
    $response->assertInertia(fn ($page) => $page
        ->component('Public/EdTech/Index')
        ->has('courses.data', 3));
});
```

This needs a `Course` factory — create `database/factories/CourseFactory.php`:

```php
<?php
namespace Database\Factories;

use App\Models\Course;
use Illuminate\Database\Eloquent\Factories\Factory;

class CourseFactory extends Factory
{
    protected $model = Course::class;

    public function definition(): array
    {
        return [
            'code' => 'C' . $this->faker->unique()->numberBetween(100, 999) . '-TEST-101',
            'title' => $this->faker->sentence(3),
            'source_path' => '/tmp/fake',
            'status' => 'ready',
            'imported_at' => now(),
        ];
    }
}
```

Add `use Illuminate\Database\Eloquent\Factories\HasFactory;` and `use HasFactory;` to `app/Models/Course.php` (matches `Book`'s convention).

- [ ] **Step 2: Run test to verify it fails**

```bash
php artisan test --filter=CourseBrowsingTest
```

Expected: FAIL — route/controller/page don't exist.

- [ ] **Step 3: Write the controller (index method only for now) and route**

`app/Http/Controllers/CourseController.php`:
```php
<?php
namespace App\Http\Controllers;

use App\Models\Course;
use Inertia\Inertia;
use Inertia\Response;

class CourseController extends Controller
{
    public function index(): Response
    {
        return Inertia::render('Public/EdTech/Index', [
            'courses' => Course::where('status', 'ready')
                ->latest()
                ->latest('id')
                ->paginate(15)
                ->withQueryString()
                ->through(fn (Course $course) => [
                    'title' => $course->title,
                    'slug' => $course->slug,
                    'subtitle' => $course->subtitle,
                ]),
        ]);
    }
}
```

Add to `routes/web.php`, right after the existing `Route::get('/library/authors/{slug}', ...)` block (before the catch-all `/{slug}` route):

```php
use App\Http\Controllers\CourseController;

Route::get('/courses', [CourseController::class, 'index'])->name('courses.index');
```

`resources/js/Pages/Public/EdTech/Index.vue` (plain page, no template-system integration — see plan note below):
```vue
<template>
  <Head><title>Courses</title></Head>

  <div class="min-h-screen bg-white font-sans">
    <nav class="fixed top-0 left-0 right-0 z-50 backdrop-blur-xl bg-white/80 border-b border-slate-100">
      <div class="max-w-6xl mx-auto px-6 h-14 flex items-center justify-between">
        <a href="/" class="text-sm text-slate-500 hover:text-orange-500 transition-colors">← Home</a>
        <span class="text-sm font-semibold text-slate-800">Courses</span>
      </div>
    </nav>

    <main class="pt-20 pb-16 max-w-6xl mx-auto px-6">
      <h1 class="text-3xl font-bold text-slate-800 mb-8">Courses</h1>

      <div v-if="courses.data.length === 0" class="text-sm text-slate-400 py-12 text-center">
        No courses available yet.
      </div>

      <div v-else class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-6">
        <Link v-for="course in courses.data" :key="course.slug" :href="`/courses/${course.slug}`"
          class="block p-5 rounded-xl border border-slate-100 hover:border-orange-200 hover:shadow-sm transition">
          <h2 class="text-base font-semibold text-slate-800">{{ course.title }}</h2>
          <p v-if="course.subtitle" class="text-sm text-slate-500 mt-1">{{ course.subtitle }}</p>
        </Link>
      </div>

      <div v-if="courses.last_page > 1" class="flex flex-wrap gap-1 mt-10 justify-center">
        <Link
          v-for="(link, i) in courses.links"
          :key="i"
          :href="link.url ?? '#'"
          v-html="link.label"
          class="px-3 py-1.5 text-sm rounded-lg"
          :class="link.active ? 'bg-orange-500 text-white' : link.url ? 'text-slate-600 hover:bg-slate-100' : 'text-slate-300 pointer-events-none'"
        />
      </div>
    </main>
  </div>
</template>

<script setup>
import { Head, Link } from '@inertiajs/vue3'

defineProps({ courses: { type: Object, required: true } })
</script>
```

Note: the library's public pages (`Public/Library/Index.vue` etc.) were extended by a separate, parallel initiative to support a public-template override system (`useActiveTemplate`/`resolvePublicPage`). This plan deliberately does **not** integrate EdTech's pages with that system — it's out of scope for this spec, and the override pattern degrades safely to a plain page when no template component is registered for a given key, so skipping it here has no negative effect.

- [ ] **Step 4: Run test to verify it passes**

```bash
php artisan test --filter=CourseBrowsingTest
```

Expected: PASS, 1/1.

- [ ] **Step 5: Commit**

```bash
git add app/Http/Controllers/CourseController.php resources/js/Pages/Public/EdTech/Index.vue routes/web.php database/factories/CourseFactory.php app/Models/Course.php tests/Feature/CourseBrowsingTest.php
git commit -m "feat: add public course listing page"
```

---

### Task 9: Course detail page

**Files:**
- Modify: `app/Http/Controllers/CourseController.php`
- Create: `resources/js/Pages/Public/EdTech/CourseDetail.vue`
- Modify: `routes/web.php`
- Modify: `tests/Feature/CourseBrowsingTest.php`

- [ ] **Step 1: Write the failing test**

Append to `tests/Feature/CourseBrowsingTest.php`:

```php
it('shows a course detail page with modules, topics, and pricing tiers', function () {
    $course = Course::factory()->create(['status' => 'ready', 'title' => 'Intro to HTML']);
    $module = \App\Models\Module::create(['course_id' => $course->id, 'title' => 'Fundamentals', 'slug' => 'fundamentals', 'sort_order' => 0]);
    \App\Models\Topic::create(['module_id' => $module->id, 'title' => 'Intro', 'slug' => 'intro', 'sort_order' => 0]);
    \App\Models\PricingTier::create(['course_id' => $course->id, 'name' => 'Pro', 'price_inr_paise' => 599900, 'price_usd_cents' => 14900]);

    $response = $this->get("/courses/{$course->slug}");

    $response->assertOk();
    $response->assertInertia(fn ($page) => $page
        ->component('Public/EdTech/CourseDetail')
        ->where('course.title', 'Intro to HTML')
        ->has('course.modules', 1)
        ->has('course.modules.0.topics', 1)
        ->has('course.pricingTiers', 1));
});

it('404s for a pending (not-yet-imported) course', function () {
    $course = Course::factory()->create(['status' => 'pending']);

    $this->get("/courses/{$course->slug}")->assertNotFound();
});
```

- [ ] **Step 2: Run test to verify it fails**

```bash
php artisan test --filter=CourseBrowsingTest
```

Expected: FAIL — `show` route/method don't exist.

- [ ] **Step 3: Write the implementation**

Add to `app/Http/Controllers/CourseController.php`:

```php
    public function show(string $slug): Response
    {
        $course = Course::where('slug', $slug)
            ->where('status', 'ready')
            ->with(['modules.topics', 'pricingTiers'])
            ->firstOrFail();

        return Inertia::render('Public/EdTech/CourseDetail', [
            'course' => [
                'title' => $course->title,
                'slug' => $course->slug,
                'subtitle' => $course->subtitle,
                'description' => $course->description,
                'modules' => $course->modules->map(fn ($m) => [
                    'title' => $m->title,
                    'topics' => $m->topics->map(fn ($t) => ['title' => $t->title, 'slug' => $t->slug])->values(),
                ])->values(),
                'pricingTiers' => $course->pricingTiers->map(fn ($t) => [
                    'id' => $t->id,
                    'name' => $t->name,
                    'price_inr_paise' => $t->price_inr_paise,
                    'price_usd_cents' => $t->price_usd_cents,
                    'description' => $t->description,
                ])->values(),
            ],
        ]);
    }
```

Add to `routes/web.php`, right after the `/courses` route:

```php
Route::get('/courses/{slug}', [CourseController::class, 'show'])
    ->name('courses.show')
    ->where('slug', '[a-z0-9\-]+');
```

`resources/js/Pages/Public/EdTech/CourseDetail.vue`:
```vue
<template>
  <Head>
    <title>{{ course.title }}</title>
    <meta name="description" :content="course.description ?? course.title" />
  </Head>

  <div class="min-h-screen bg-white font-sans">
    <nav class="fixed top-0 left-0 right-0 z-50 backdrop-blur-xl bg-white/80 border-b border-slate-100">
      <div class="max-w-6xl mx-auto px-6 h-14 flex items-center justify-between">
        <a href="/courses" class="text-sm text-slate-500 hover:text-orange-500 transition-colors">← Courses</a>
        <span class="text-sm font-semibold text-slate-800 truncate max-w-xs">{{ course.title }}</span>
      </div>
    </nav>

    <main class="pt-20 pb-16 max-w-4xl mx-auto px-6">
      <h1 class="text-2xl font-bold text-slate-800 mb-2">{{ course.title }}</h1>
      <p v-if="course.subtitle" class="text-sm text-slate-500">{{ course.subtitle }}</p>
      <p v-if="course.description" class="text-sm text-slate-600 leading-relaxed mt-4">{{ course.description }}</p>

      <section v-if="course.pricingTiers.length" class="mt-8 grid gap-4 sm:grid-cols-2">
        <div v-for="tier in course.pricingTiers" :key="tier.id" class="border border-slate-100 rounded-xl p-4">
          <h3 class="text-sm font-semibold text-slate-800">{{ tier.name }}</h3>
          <p class="text-xs text-slate-500 mt-1">{{ tier.description }}</p>
          <p class="text-lg font-bold text-slate-800 mt-2">₹{{ (tier.price_inr_paise / 100).toLocaleString() }}</p>
        </div>
      </section>

      <h2 class="text-lg font-semibold text-slate-800 mt-10 mb-3">Course content</h2>
      <div v-for="module in course.modules" :key="module.title" class="mb-6">
        <h3 class="text-sm font-semibold text-slate-700 mb-2">{{ module.title }}</h3>
        <ol class="divide-y divide-slate-100 border border-slate-100 rounded-xl overflow-hidden">
          <li v-for="topic in module.topics" :key="topic.slug">
            <Link
              :href="`/courses/${course.slug}/topics/${topic.slug}`"
              class="flex items-center justify-between px-4 py-3 text-sm hover:bg-slate-50 transition-colors"
            >
              <span>{{ topic.title }}</span>
              <span class="text-slate-300">›</span>
            </Link>
          </li>
        </ol>
      </div>
    </main>
  </div>
</template>

<script setup>
import { Head, Link } from '@inertiajs/vue3'

defineProps({ course: { type: Object, required: true } })
</script>
```

- [ ] **Step 4: Run test to verify it passes**

```bash
php artisan test --filter=CourseBrowsingTest
```

Expected: PASS, 3/3.

- [ ] **Step 5: Commit**

```bash
git add app/Http/Controllers/CourseController.php resources/js/Pages/Public/EdTech/CourseDetail.vue routes/web.php tests/Feature/CourseBrowsingTest.php
git commit -m "feat: add public course detail page"
```

---

### Task 10: Topic viewer page (with download-policy enforcement)

**Files:**
- Modify: `app/Http/Controllers/CourseController.php`
- Create: `resources/js/Pages/Public/EdTech/TopicViewer.vue`
- Modify: `routes/web.php`
- Modify: `tests/Feature/CourseBrowsingTest.php`

- [ ] **Step 1: Write the failing test**

Append to `tests/Feature/CourseBrowsingTest.php`:

```php
it('shows a topic viewer with materials split by download policy, and hides raw content for view-only materials from the download action', function () {
    $course = Course::factory()->create(['status' => 'ready']);
    $module = \App\Models\Module::create(['course_id' => $course->id, 'title' => 'Fundamentals', 'slug' => 'fundamentals', 'sort_order' => 0]);
    $topic = \App\Models\Topic::create(['module_id' => $module->id, 'title' => 'Intro', 'slug' => 'intro', 'sort_order' => 0]);
    \App\Models\Material::create(['topic_id' => $topic->id, 'type' => 'notes', 'content' => '# Notes body', 'download_policy' => 'downloadable', 'status' => 'ready']);
    \App\Models\Material::create(['topic_id' => $topic->id, 'type' => 'task', 'content' => '# Task body', 'download_policy' => 'view_only', 'status' => 'ready']);
    \App\Models\Material::create(['topic_id' => $topic->id, 'type' => 'video', 'download_policy' => 'view_only', 'status' => 'not_generated']);

    $response = $this->get("/courses/{$course->slug}/topics/{$topic->slug}");

    $response->assertOk();
    $response->assertInertia(fn ($page) => $page
        ->component('Public/EdTech/TopicViewer')
        ->where('topic.title', 'Intro')
        ->where('topic.materials.notes.content', '# Notes body')
        ->where('topic.materials.notes.downloadable', true)
        ->where('topic.materials.task.downloadable', false)
        ->where('topic.materials.video.status', 'not_generated'));
});

it('serves a downloadable material file via an authenticated download action', function () {
    $course = Course::factory()->create(['status' => 'ready']);
    $module = \App\Models\Module::create(['course_id' => $course->id, 'title' => 'F', 'slug' => 'f', 'sort_order' => 0]);
    $topic = \App\Models\Topic::create(['module_id' => $module->id, 'title' => 'T', 'slug' => 't', 'sort_order' => 0]);
    $notes = \App\Models\Material::create(['topic_id' => $topic->id, 'type' => 'notes', 'content' => '# Downloadable', 'download_policy' => 'downloadable', 'status' => 'ready']);
    $task = \App\Models\Material::create(['topic_id' => $topic->id, 'type' => 'task', 'content' => '# Not downloadable', 'download_policy' => 'view_only', 'status' => 'ready']);

    $this->get("/courses/{$course->slug}/topics/{$topic->slug}/materials/{$notes->id}/download")
        ->assertOk()
        ->assertSee('# Downloadable');

    $this->get("/courses/{$course->slug}/topics/{$topic->slug}/materials/{$task->id}/download")
        ->assertForbidden();
});
```

- [ ] **Step 2: Run test to verify it fails**

```bash
php artisan test --filter=CourseBrowsingTest
```

Expected: FAIL — topic route/method don't exist.

- [ ] **Step 3: Write the implementation**

Add to `app/Http/Controllers/CourseController.php`:

```php
    public function topic(string $slug, string $topicSlug): Response
    {
        $course = Course::where('slug', $slug)->where('status', 'ready')->firstOrFail();
        $topic = Topic::whereHas('module', fn ($q) => $q->where('course_id', $course->id))
            ->where('slug', $topicSlug)
            ->with('materials')
            ->firstOrFail();

        return Inertia::render('Public/EdTech/TopicViewer', [
            'course' => ['title' => $course->title, 'slug' => $course->slug],
            'topic' => [
                'title' => $topic->title,
                'slug' => $topic->slug,
                'materials' => $topic->materials->keyBy('type')->map(fn ($m) => [
                    'id' => $m->id,
                    'status' => $m->status,
                    'downloadable' => $m->isDownloadable(),
                    // View-only content is embedded directly (never a fetchable file
                    // URL); downloadable content also embeds here for in-page preview,
                    // plus gets a separate download link via the download action below.
                    'content' => $m->status === 'ready' ? $m->content : null,
                ]),
            ],
        ]);
    }

    public function downloadMaterial(string $slug, string $topicSlug, \App\Models\Material $material)
    {
        abort_unless($material->isDownloadable(), 403);
        abort_unless($material->topic->slug === $topicSlug, 404);

        return response($material->content, 200, [
            'Content-Type' => 'text/plain',
            'Content-Disposition' => "attachment; filename=\"{$material->type}.md\"",
        ]);
    }
```

Add `use App\Models\Topic;` to the controller's imports.

Add to `routes/web.php`, right after the `/courses/{slug}` route:

```php
Route::get('/courses/{slug}/topics/{topicSlug}', [CourseController::class, 'topic'])
    ->name('courses.topic')
    ->where(['slug' => '[a-z0-9\-]+', 'topicSlug' => '[a-z0-9\-]+']);
Route::get('/courses/{slug}/topics/{topicSlug}/materials/{material}/download', [CourseController::class, 'downloadMaterial'])
    ->name('courses.materials.download')
    ->where(['slug' => '[a-z0-9\-]+', 'topicSlug' => '[a-z0-9\-]+']);
```

`resources/js/Pages/Public/EdTech/TopicViewer.vue`:
```vue
<template>
  <Head><title>{{ topic.title }} · {{ course.title }}</title></Head>

  <div class="min-h-screen bg-white font-sans">
    <nav class="fixed top-0 left-0 right-0 z-50 backdrop-blur-xl bg-white/80 border-b border-slate-100">
      <div class="max-w-6xl mx-auto px-6 h-14 flex items-center justify-between">
        <a :href="`/courses/${course.slug}`" class="text-sm text-slate-500 hover:text-orange-500 transition-colors">← {{ course.title }}</a>
        <span class="text-sm font-semibold text-slate-800 truncate max-w-xs">{{ topic.title }}</span>
      </div>
    </nav>

    <main class="pt-20 pb-16 max-w-3xl mx-auto px-6">
      <h1 class="text-xl font-bold text-slate-800 mb-6">{{ topic.title }}</h1>

      <div class="flex gap-1 border-b border-slate-100 mb-6">
        <button
          v-for="tab in tabs"
          :key="tab.key"
          @click="active = tab.key"
          class="px-3 py-2 text-sm border-b-2 transition-colors"
          :class="active === tab.key ? 'border-orange-500 text-slate-800 font-medium' : 'border-transparent text-slate-400 hover:text-slate-600'"
        >
          {{ tab.label }}
        </button>
      </div>

      <div v-if="activeMaterial?.status === 'ready'">
        <a
          v-if="activeMaterial.downloadable"
          :href="`/courses/${course.slug}/topics/${topic.slug}/materials/${activeMaterial.id}/download`"
          class="inline-block mb-4 text-xs text-orange-500 hover:underline"
        >
          Download
        </a>
        <div class="prose prose-sm max-w-none whitespace-pre-wrap">{{ activeMaterial.content }}</div>
      </div>
      <div v-else-if="activeMaterial?.status === 'not_generated'" class="text-sm text-slate-400 py-12 text-center">
        {{ active === 'video' ? 'Video coming soon.' : 'Not available yet.' }}
      </div>
      <div v-else-if="activeMaterial?.status === 'generating'" class="text-sm text-slate-400 py-12 text-center">
        Generating…
      </div>
      <div v-else class="text-sm text-slate-400 py-12 text-center">
        Not available for this topic.
      </div>
    </main>
  </div>
</template>

<script setup>
import { ref, computed } from 'vue'
import { Head } from '@inertiajs/vue3'

const props = defineProps({ course: { type: Object, required: true }, topic: { type: Object, required: true } })

const tabs = [
  { key: 'notes', label: 'Notes' },
  { key: 'task', label: 'Task' },
  { key: 'demo_problem', label: 'Demo (Problem)' },
  { key: 'demo_try_it', label: 'Demo (Try It)' },
  { key: 'demo_solution', label: 'Demo (Solution)' },
  { key: 'slides', label: 'Slides' },
  { key: 'video', label: 'Video' },
]

const active = ref('notes')
const activeMaterial = computed(() => props.topic.materials[active.value] ?? null)
</script>
```

- [ ] **Step 4: Run test to verify it passes**

```bash
php artisan test --filter=CourseBrowsingTest
```

Expected: PASS, 5/5.

- [ ] **Step 5: Commit**

```bash
git add app/Http/Controllers/CourseController.php resources/js/Pages/Public/EdTech/TopicViewer.vue routes/web.php tests/Feature/CourseBrowsingTest.php
git commit -m "feat: add topic viewer page with download-policy enforcement"
```

---

### Task 11: NotificationSender contract + wallet hook

**Files:**
- Create: `app/Notifications/Contracts/NotificationSender.php`
- Create: `app/Notifications/NullNotificationSender.php`
- Create: `app/Providers/NotificationServiceProvider.php`
- Modify: `bootstrap/providers.php`
- Modify: `app/Models/User.php`
- Test: `tests/Unit/WalletNotificationHookTest.php`

- [ ] **Step 1: Write the failing test**

```php
<?php

use App\Models\User;
use App\Notifications\Contracts\NotificationSender;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery\MockInterface;

uses(RefreshDatabase::class);

it('calls NotificationSender exactly once when crediting a wallet', function () {
    $this->mock(NotificationSender::class, function (MockInterface $mock) {
        $mock->shouldReceive('send')->once();
    });

    $user = User::factory()->create(['wallet_balance_paise' => 0]);
    $user->creditWallet(50000, 'topup', 'Test credit');

    // Mockery's shouldReceive('send')->once() self-asserts on tearDown; nothing
    // further to assert here, but keep an explicit assertion for clarity:
    expect(true)->toBeTrue();
});

it('calls NotificationSender exactly once when debiting a wallet', function () {
    $this->mock(NotificationSender::class, function (MockInterface $mock) {
        $mock->shouldReceive('send')->once();
    });

    $user = User::factory()->create(['wallet_balance_paise' => 100000]);
    $user->debitWallet(50000, 'enrollment', 'Test debit');

    expect(true)->toBeTrue();
});

it('does not call NotificationSender when a debit fails for insufficient balance', function () {
    $this->mock(NotificationSender::class, function (MockInterface $mock) {
        $mock->shouldNotReceive('send');
    });

    $user = User::factory()->create(['wallet_balance_paise' => 100]);

    expect(fn () => $user->debitWallet(50000, 'enrollment', 'Test debit'))
        ->toThrow(RuntimeException::class);
});

it('binds NullNotificationSender by default and it does not throw', function () {
    app()->forgetInstance(NotificationSender::class);
    $sender = app(NotificationSender::class);

    expect($sender)->toBeInstanceOf(\App\Notifications\NullNotificationSender::class);
    $sender->send(new stdClass()); // must not throw
    expect(true)->toBeTrue();
});
```

- [ ] **Step 2: Run test to verify it fails**

```bash
php artisan test --filter=WalletNotificationHookTest
```

Expected: FAIL — `NotificationSender` interface doesn't exist.

- [ ] **Step 3: Write the contract, null implementation, and provider**

`app/Notifications/Contracts/NotificationSender.php`:
```php
<?php
namespace App\Notifications\Contracts;

interface NotificationSender
{
    /**
     * Dispatch a notification through whichever channels are currently enabled.
     * Implementations decide channel selection/config; callers just hand over
     * the notification object.
     */
    public function send(object $notification): void;
}
```

`app/Notifications/NullNotificationSender.php`:
```php
<?php
namespace App\Notifications;

use App\Notifications\Contracts\NotificationSender;
use Illuminate\Support\Facades\Log;

/**
 * Default binding until the separate Notification System spec ships a real
 * implementation. Logs at debug level and returns — callers (User::creditWallet(),
 * User::debitWallet()) work identically whether this or a real sender is bound.
 */
class NullNotificationSender implements NotificationSender
{
    public function send(object $notification): void
    {
        Log::debug('NullNotificationSender: notification not sent (no real sender bound yet)', [
            'notification' => get_class($notification),
        ]);
    }
}
```

`app/Providers/NotificationServiceProvider.php`:
```php
<?php
namespace App\Providers;

use App\Notifications\Contracts\NotificationSender;
use App\Notifications\NullNotificationSender;
use Illuminate\Support\ServiceProvider;

class NotificationServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->bind(NotificationSender::class, NullNotificationSender::class);
    }
}
```

Register it — check `bootstrap/providers.php`'s current contents first (`cat bootstrap/providers.php`), then add `App\Providers\NotificationServiceProvider::class` to the returned array, matching the existing entries' style.

- [ ] **Step 4: Create a minimal WalletBalanceChanged notification object and wire the hook**

`app/Notifications/WalletBalanceChanged.php`:
```php
<?php
namespace App\Notifications;

use App\Models\User;
use App\Models\WalletTransaction;

/**
 * Plain data object, not a Laravel Notification subclass yet — the separate
 * Notification System spec decides the real channel/delivery shape. This is
 * just what NotificationSender::send() receives today.
 */
class WalletBalanceChanged
{
    public function __construct(
        public User $user,
        public WalletTransaction $transaction,
    ) {}
}
```

Modify `app/Models/User.php`: add the import `use App\Notifications\Contracts\NotificationSender;` and `use App\Notifications\WalletBalanceChanged;`, then at the end of `creditWallet()` (right before `return $this->wallet_balance_paise;`) add:

```php
        app(NotificationSender::class)->send(new WalletBalanceChanged($this, $this->walletTransactions()->latest()->first()));
```

and at the end of `debitWallet()` (right before `return $fresh->wallet_balance_paise;`) add:

```php
        app(NotificationSender::class)->send(new WalletBalanceChanged($this, $this->walletTransactions()->latest()->first()));
```

- [ ] **Step 5: Run test to verify it passes**

```bash
php artisan test --filter=WalletNotificationHookTest
```

Expected: PASS, 4/4.

- [ ] **Step 6: Add the missing regression coverage for `creditWallet()`/`debitWallet()`'s core behavior**

There is no existing test file for `User::creditWallet()`/`debitWallet()` anywhere in the codebase (confirmed via `grep -rl "creditWallet\|debitWallet" tests/` returning nothing) — Step 5's test only covers the new notification hook, not the balance/ledger math those methods were already relied on for. Add it now, since this task is the first time either method's behavior is verified at all:

```php
it('creditWallet increases the balance and writes a credit transaction row', function () {
    $user = User::factory()->create(['wallet_balance_paise' => 10000]);

    $newBalance = $user->creditWallet(5000, 'topup', 'Test credit', 'Jane', 'jane@example.com');

    expect($newBalance)->toBe(15000)
        ->and($user->fresh()->wallet_balance_paise)->toBe(15000);

    $txn = $user->walletTransactions()->first();
    expect($txn->type)->toBe('credit')
        ->and($txn->amount_paise)->toBe(5000)
        ->and($txn->balance_after_paise)->toBe(15000)
        ->and($txn->category)->toBe('topup');
});

it('debitWallet decreases the balance and writes a debit transaction row', function () {
    $user = User::factory()->create(['wallet_balance_paise' => 10000]);

    $newBalance = $user->debitWallet(3000, 'enrollment', 'Test debit');

    expect($newBalance)->toBe(7000)
        ->and($user->fresh()->wallet_balance_paise)->toBe(7000);

    $txn = $user->walletTransactions()->first();
    expect($txn->type)->toBe('debit')
        ->and($txn->amount_paise)->toBe(3000)
        ->and($txn->balance_after_paise)->toBe(7000)
        ->and($txn->category)->toBe('enrollment');
});
```

Append these to `tests/Unit/WalletNotificationHookTest.php` (same file — it's now genuinely the only test coverage this behavior has, notification hook and core ledger math together).

- [ ] **Step 7: Run the full file to verify everything passes**

```bash
php artisan test --filter=WalletNotificationHookTest
```

Expected: PASS, 6/6.

- [ ] **Step 8: Commit**

```bash
git add app/Notifications/ app/Providers/NotificationServiceProvider.php bootstrap/providers.php app/Models/User.php tests/Unit/WalletNotificationHookTest.php
git commit -m "feat: add NotificationSender contract, hook into wallet credit/debit"
```

---

### Task 12: Enrollment

**Files:**
- Create: `app/Http/Controllers/CourseEnrollmentController.php`
- Modify: `routes/web.php`
- Modify: `resources/js/Pages/Public/EdTech/CourseDetail.vue`
- Test: `tests/Feature/CourseEnrollmentTest.php`

- [ ] **Step 1: Write the failing test**

```php
<?php

use App\Models\Course;
use App\Models\Enrollment;
use App\Models\PricingTier;
use App\Models\User;
use App\Models\WalletTransaction;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('enrolls a user and debits their wallet for the tier price', function () {
    $user = User::factory()->create(['wallet_balance_paise' => 1000000]);
    $course = Course::factory()->create(['status' => 'ready']);
    $tier = PricingTier::create(['course_id' => $course->id, 'name' => 'Pro', 'price_inr_paise' => 599900, 'price_usd_cents' => 14900]);

    $response = $this->actingAs($user)->postJson("/courses/{$course->slug}/enroll", ['pricing_tier_id' => $tier->id]);

    $response->assertOk();
    expect(Enrollment::where('user_id', $user->id)->where('course_id', $course->id)->exists())->toBeTrue()
        ->and($user->fresh()->wallet_balance_paise)->toBe(1000000 - 599900);

    $txn = WalletTransaction::where('recipient_user_id', $user->id)->where('category', 'enrollment')->first();
    expect($txn)->not->toBeNull()->and($txn->amount_paise)->toBe(599900);
});

it('rejects enrollment with insufficient wallet balance, creating no enrollment or transaction', function () {
    $user = User::factory()->create(['wallet_balance_paise' => 100]);
    $course = Course::factory()->create(['status' => 'ready']);
    $tier = PricingTier::create(['course_id' => $course->id, 'name' => 'Pro', 'price_inr_paise' => 599900, 'price_usd_cents' => 14900]);

    $response = $this->actingAs($user)->postJson("/courses/{$course->slug}/enroll", ['pricing_tier_id' => $tier->id]);

    $response->assertStatus(422);
    expect(Enrollment::count())->toBe(0)
        ->and(WalletTransaction::count())->toBe(0)
        ->and($user->fresh()->wallet_balance_paise)->toBe(100);
});

it('rejects enrolling in the same course twice', function () {
    $user = User::factory()->create(['wallet_balance_paise' => 5000000]);
    $course = Course::factory()->create(['status' => 'ready']);
    $tier = PricingTier::create(['course_id' => $course->id, 'name' => 'Pro', 'price_inr_paise' => 599900, 'price_usd_cents' => 14900]);

    $this->actingAs($user)->postJson("/courses/{$course->slug}/enroll", ['pricing_tier_id' => $tier->id])->assertOk();
    $response = $this->actingAs($user)->postJson("/courses/{$course->slug}/enroll", ['pricing_tier_id' => $tier->id]);

    $response->assertStatus(422);
    expect(Enrollment::where('user_id', $user->id)->count())->toBe(1);
});

it('requires authentication to enroll', function () {
    $course = Course::factory()->create(['status' => 'ready']);
    $tier = PricingTier::create(['course_id' => $course->id, 'name' => 'Pro', 'price_inr_paise' => 599900, 'price_usd_cents' => 14900]);

    $this->postJson("/courses/{$course->slug}/enroll", ['pricing_tier_id' => $tier->id])->assertUnauthorized();
});
```

- [ ] **Step 2: Run test to verify it fails**

```bash
php artisan test --filter=CourseEnrollmentTest
```

Expected: FAIL — route/controller don't exist.

- [ ] **Step 3: Write the implementation**

`app/Http/Controllers/CourseEnrollmentController.php`:
```php
<?php
namespace App\Http\Controllers;

use App\Models\Course;
use App\Models\Enrollment;
use App\Models\PricingTier;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use RuntimeException;

class CourseEnrollmentController extends Controller
{
    public function store(Request $request, string $slug): JsonResponse
    {
        $course = Course::where('slug', $slug)->where('status', 'ready')->firstOrFail();
        $data = $request->validate(['pricing_tier_id' => 'required|integer']);

        $tier = PricingTier::where('course_id', $course->id)->findOrFail($data['pricing_tier_id']);
        $user = $request->user();

        if (Enrollment::where('user_id', $user->id)->where('course_id', $course->id)->exists()) {
            return response()->json(['message' => 'Already enrolled in this course.'], 422);
        }

        try {
            $user->debitWallet($tier->price_inr_paise, 'enrollment', "Enrollment: {$course->title} ({$tier->name})");
        } catch (RuntimeException $e) {
            return response()->json(['message' => 'Insufficient wallet balance for this tier.'], 422);
        }

        $enrollment = Enrollment::create([
            'user_id' => $user->id,
            'course_id' => $course->id,
            'pricing_tier_id' => $tier->id,
            'amount_paise_charged' => $tier->price_inr_paise,
            'enrolled_at' => now(),
        ]);

        return response()->json(['id' => $enrollment->id, 'course_slug' => $course->slug]);
    }
}
```

Add to `routes/web.php`, in a new `auth`-only group right after the public course routes (mirrors the existing `Route::middleware(['auth'])->group(...)` pattern used for wallet routes):

```php
use App\Http\Controllers\CourseEnrollmentController;

Route::middleware(['auth'])->group(function () {
    Route::post('/courses/{slug}/enroll', [CourseEnrollmentController::class, 'store'])
        ->name('courses.enroll')
        ->where('slug', '[a-z0-9\-]+');
});
```

Update `resources/js/Pages/Public/EdTech/CourseDetail.vue`'s pricing tier card to add an enroll button — replace the pricing tier `<div>` block with:

```vue
        <div v-for="tier in course.pricingTiers" :key="tier.id" class="border border-slate-100 rounded-xl p-4">
          <h3 class="text-sm font-semibold text-slate-800">{{ tier.name }}</h3>
          <p class="text-xs text-slate-500 mt-1">{{ tier.description }}</p>
          <p class="text-lg font-bold text-slate-800 mt-2">₹{{ (tier.price_inr_paise / 100).toLocaleString() }}</p>
          <button
            @click="enroll(tier.id)"
            :disabled="enrolling"
            class="mt-3 w-full text-sm bg-orange-500 text-white rounded-lg py-2 hover:bg-orange-600 disabled:opacity-50 transition-colors"
          >
            {{ enrolling ? 'Enrolling…' : 'Enroll' }}
          </button>
          <p v-if="enrollError" class="text-xs text-red-500 mt-2">{{ enrollError }}</p>
        </div>
```

Add to the `<script setup>` block:
```js
import { ref } from 'vue'
import axios from 'axios'

const enrolling = ref(false)
const enrollError = ref(null)

const enroll = async (tierId) => {
  enrolling.value = true
  enrollError.value = null
  try {
    await axios.post(`/courses/${props.course.slug}/enroll`, { pricing_tier_id: tierId })
    window.location.reload()
  } catch (err) {
    enrollError.value = err.response?.data?.message ?? 'Enrollment failed.'
  } finally {
    enrolling.value = false
  }
}
```

(Change `defineProps({ course: ... })` to `const props = defineProps({ course: ... })` so `props.course.slug` is available in `enroll()`.)

- [ ] **Step 4: Run test to verify it passes**

```bash
php artisan test --filter=CourseEnrollmentTest
```

Expected: PASS, 4/4.

- [ ] **Step 5: Commit**

```bash
git add app/Http/Controllers/CourseEnrollmentController.php routes/web.php resources/js/Pages/Public/EdTech/CourseDetail.vue tests/Feature/CourseEnrollmentTest.php
git commit -m "feat: add course enrollment charging the existing wallet"
```

---

### Task 13: Admin course management + enrollments UI

**Files:**
- Create: `app/Http/Controllers/Admin/EdTechController.php`
- Create: `resources/js/Pages/Admin/EdTech/Index.vue`
- Create: `resources/js/Pages/Admin/EdTech/Enrollments.vue`
- Modify: `routes/web.php`
- Modify: `resources/js/Layouts/AdminLayout.vue`
- Test: `tests/Feature/AdminEdTechTest.php`

- [ ] **Step 1: Write the failing test**

```php
<?php

use App\Models\Course;
use App\Models\Enrollment;
use App\Models\PricingTier;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;

uses(RefreshDatabase::class);
beforeEach(fn () => $this->seed());

it('lists courses with status and counts on the admin index', function () {
    $admin = User::factory()->create();
    $admin->assignRole('admin');

    $course = Course::factory()->create(['status' => 'ready']);
    $module = \App\Models\Module::create(['course_id' => $course->id, 'title' => 'M', 'slug' => 'm', 'sort_order' => 0]);
    \App\Models\Topic::create(['module_id' => $module->id, 'title' => 'T', 'slug' => 't', 'sort_order' => 0]);

    $response = $this->actingAs($admin)->get('/admin/edtech');

    $response->assertOk();
    $response->assertInertia(fn ($page) => $page
        ->component('Admin/EdTech/Index')
        ->where('courses.data.0.module_count', 1)
        ->where('courses.data.0.topic_count', 1));
});

it('lists enrollments with learner, course, and amount for admins', function () {
    $admin = User::factory()->create();
    $admin->assignRole('admin');
    $learner = User::factory()->create();
    $course = Course::factory()->create(['status' => 'ready', 'title' => 'Intro to HTML']);
    $tier = PricingTier::create(['course_id' => $course->id, 'name' => 'Pro', 'price_inr_paise' => 599900, 'price_usd_cents' => 14900]);
    Enrollment::create(['user_id' => $learner->id, 'course_id' => $course->id, 'pricing_tier_id' => $tier->id, 'amount_paise_charged' => 599900, 'enrolled_at' => now()]);

    $response = $this->actingAs($admin)->get('/admin/edtech/enrollments');

    $response->assertOk();
    $response->assertInertia(fn ($page) => $page
        ->component('Admin/EdTech/Enrollments')
        ->where('enrollments.data.0.course_title', 'Intro to HTML')
        ->where('enrollments.data.0.amount_paise', 599900));
});

it('re-imports a course on demand from the admin index', function () {
    Queue::fake();
    $admin = User::factory()->create();
    $admin->assignRole('admin');
    $course = Course::factory()->create(['status' => 'ready', 'source_path' => '/tmp/doesnotmatter']);

    // Re-import against a path that doesn't exist should fail cleanly, not crash —
    // confirms the endpoint at least routes and calls the service without a 500.
    $response = $this->actingAs($admin)->postJson("/admin/edtech/courses/{$course->id}/reimport");

    expect($response->status())->toBeIn([200, 422]);
});
```

- [ ] **Step 2: Run test to verify it fails**

```bash
php artisan test --filter=AdminEdTechTest
```

Expected: FAIL — routes/controller/pages don't exist.

- [ ] **Step 3: Write the implementation**

`app/Http/Controllers/Admin/EdTechController.php`:
```php
<?php
namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Course;
use App\Models\Enrollment;
use App\Services\CourseImportService;
use Illuminate\Http\JsonResponse;
use Inertia\Inertia;
use Inertia\Response;

class EdTechController extends Controller
{
    public function index(): Response
    {
        return Inertia::render('Admin/EdTech/Index', [
            'courses' => Course::withCount('modules')
                ->with('modules.topics')
                ->latest()
                ->latest('id')
                ->paginate(15)
                ->withQueryString()
                ->through(fn (Course $course) => [
                    'id' => $course->id,
                    'code' => $course->code,
                    'title' => $course->title,
                    'status' => $course->status,
                    'status_reason' => $course->status_reason,
                    'module_count' => $course->modules_count,
                    'topic_count' => $course->modules->sum(fn ($m) => $m->topics->count()),
                ]),
        ]);
    }

    public function enrollments(): Response
    {
        return Inertia::render('Admin/EdTech/Enrollments', [
            'enrollments' => Enrollment::with(['user', 'course', 'pricingTier'])
                ->latest()
                ->paginate(20)
                ->withQueryString()
                ->through(fn (Enrollment $e) => [
                    'learner_name' => $e->user->name,
                    'course_title' => $e->course->title,
                    'tier_name' => $e->pricingTier->name,
                    'amount_paise' => $e->amount_paise_charged,
                    'enrolled_at' => $e->enrolled_at->toDateTimeString(),
                ]),
        ]);
    }

    public function reimport(Course $course, CourseImportService $service): JsonResponse
    {
        if (! is_dir($course->source_path)) {
            return response()->json(['message' => 'Source path no longer exists on disk.'], 422);
        }

        $service->importCourse($course->source_path, $course->code);

        return response()->json(['id' => $course->id, 'status' => $course->fresh()->status]);
    }
}
```

Add to `routes/web.php`, in a new admin group (mirrors the existing `Admin\BookController` route block):

```php
use App\Http\Controllers\Admin\EdTechController;

Route::middleware(['auth', 'role:admin,editor,viewer'])
    ->prefix('admin')
    ->name('admin.')
    ->group(function () {
        Route::get('edtech', [EdTechController::class, 'index'])->name('edtech.index');
        Route::get('edtech/enrollments', [EdTechController::class, 'enrollments'])->name('edtech.enrollments');
        Route::post('edtech/courses/{course}/reimport', [EdTechController::class, 'reimport'])->name('edtech.reimport');
    });
```

`resources/js/Pages/Admin/EdTech/Index.vue`:
```vue
<template>
  <AdminLayout>
    <div class="max-w-5xl mx-auto">
      <h1 class="text-2xl font-bold text-slate-800 mb-6">EdTech Courses</h1>

      <table class="w-full text-sm border border-slate-100 rounded-xl overflow-hidden">
        <thead class="bg-slate-50 text-left text-xs text-slate-500 uppercase">
          <tr>
            <th class="px-4 py-2">Code</th>
            <th class="px-4 py-2">Title</th>
            <th class="px-4 py-2">Status</th>
            <th class="px-4 py-2">Modules</th>
            <th class="px-4 py-2">Topics</th>
            <th class="px-4 py-2"></th>
          </tr>
        </thead>
        <tbody class="divide-y divide-slate-100">
          <tr v-for="course in courses.data" :key="course.id">
            <td class="px-4 py-2 font-mono text-xs">{{ course.code }}</td>
            <td class="px-4 py-2">{{ course.title }}</td>
            <td class="px-4 py-2">
              <span class="px-2 py-0.5 rounded-full text-xs" :class="statusBadge(course.status)">{{ course.status }}</span>
            </td>
            <td class="px-4 py-2">{{ course.module_count }}</td>
            <td class="px-4 py-2">{{ course.topic_count }}</td>
            <td class="px-4 py-2">
              <button @click="reimport(course.id)" class="text-xs text-orange-500 hover:underline">Re-import</button>
            </td>
          </tr>
        </tbody>
      </table>
    </div>
  </AdminLayout>
</template>

<script setup>
import AdminLayout from '@/Layouts/AdminLayout.vue'
import axios from 'axios'
import { router } from '@inertiajs/vue3'

defineProps({ courses: { type: Object, required: true } })

const statusBadge = (status) => ({
  ready: 'bg-green-50 text-green-700',
  pending: 'bg-slate-100 text-slate-600',
  importing: 'bg-blue-50 text-blue-700',
  failed: 'bg-red-50 text-red-700',
}[status] ?? 'bg-slate-100 text-slate-600')

const reimport = async (id) => {
  await axios.post(`/admin/edtech/courses/${id}/reimport`)
  router.reload({ only: ['courses'] })
}
</script>
```

`resources/js/Pages/Admin/EdTech/Enrollments.vue`:
```vue
<template>
  <AdminLayout>
    <div class="max-w-5xl mx-auto">
      <h1 class="text-2xl font-bold text-slate-800 mb-6">Enrollments</h1>

      <table class="w-full text-sm border border-slate-100 rounded-xl overflow-hidden">
        <thead class="bg-slate-50 text-left text-xs text-slate-500 uppercase">
          <tr>
            <th class="px-4 py-2">Learner</th>
            <th class="px-4 py-2">Course</th>
            <th class="px-4 py-2">Tier</th>
            <th class="px-4 py-2">Amount</th>
            <th class="px-4 py-2">Enrolled</th>
          </tr>
        </thead>
        <tbody class="divide-y divide-slate-100">
          <tr v-for="(e, i) in enrollments.data" :key="i">
            <td class="px-4 py-2">{{ e.learner_name }}</td>
            <td class="px-4 py-2">{{ e.course_title }}</td>
            <td class="px-4 py-2">{{ e.tier_name }}</td>
            <td class="px-4 py-2">₹{{ (e.amount_paise / 100).toLocaleString() }}</td>
            <td class="px-4 py-2 text-xs text-slate-500">{{ e.enrolled_at }}</td>
          </tr>
        </tbody>
      </table>
    </div>
  </AdminLayout>
</template>

<script setup>
import AdminLayout from '@/Layouts/AdminLayout.vue'

defineProps({ enrollments: { type: Object, required: true } })
</script>
```

Add a nav entry to `resources/js/Layouts/AdminLayout.vue`'s `allNav` array, right after the `Library Chat` entry:

```js
  { label: 'EdTech',        href: '/admin/edtech',        roles: ['admin','editor','viewer'], icon: 'M12 14l9-5-9-5-9 5 9 5zM12 14l6.16-3.42A12.05 12.05 0 0121 12v4M12 14v7m-4-3.5v-4l4 2.2' },
```

- [ ] **Step 4: Run test to verify it passes**

```bash
php artisan test --filter=AdminEdTechTest
```

Expected: PASS, 3/3.

- [ ] **Step 5: Commit**

```bash
git add app/Http/Controllers/Admin/EdTechController.php resources/js/Pages/Admin/EdTech/ routes/web.php resources/js/Layouts/AdminLayout.vue tests/Feature/AdminEdTechTest.php
git commit -m "feat: add admin course management and enrollments views"
```

---

### Task 14: Full suite verification + manual smoke test

**Files:** none (verification only)

- [ ] **Step 1: Run the full backend test suite**

```bash
php artisan test
```

Expected: all tests pass except the pre-existing, unrelated `ExampleTest` failure (documented in `docs/superpowers/bugs/2026-08-01-rag-migrations-hard-dependency-on-live-postgres.md`'s resolution notes as confirmed unrelated to any session work).

- [ ] **Step 2: Build frontend assets**

```bash
npm run build
```

Expected: builds cleanly, no errors.

- [ ] **Step 3: Import the real course catalog**

```bash
php artisan edtech:import-courses /Users/nakul/Learn/CourseWork/Courses
```

Expected: "Imported: 7, Skipped: 0" (or similar — matches however many rows in `COURSE-INDEX.md` are marked content-complete at the time this runs).

- [ ] **Step 4: Manual smoke test in the browser**

Start the dev server and check, in order:
1. `/courses` — lists the imported courses.
2. `/courses/{slug}` — shows modules/topics/pricing tiers.
3. `/courses/{slug}/topics/{topicSlug}` — Notes tab shows real content with a working download link; Task/Demo tabs show view-only content with no download link; Video tab shows "Video coming soon."
4. Log in, enroll in a course, confirm wallet balance decreases and `/admin/edtech/enrollments` shows the row.
5. `/admin/edtech` — shows imported courses with correct module/topic counts; re-import doesn't error.

- [ ] **Step 5: Commit any fixes found during smoke testing, then final commit**

```bash
git add -A
git commit -m "chore: EdTech platform v1 smoke-test fixes" --allow-empty
```
