# EdTech Platform v1 — Design Spec

**Status:** Approved by user, ready for implementation planning.
**Source content:** `/Users/nakul/Learn/CourseWork/Courses/` (external to this repo — a separate, independently-maintained content authoring project; see "Content source" below).
**Related, deliberately separate spec:** a general-purpose Notification System (repository/resource/service layers, mail/SMS/push/other provider channels) is being brainstormed in a **different session** — see "Notification hook (deferred)" below for how this spec stays decoupled from it.

## Goal

Bring the CourseWork catalog (currently a folder of markdown/HTML content on one machine) into Scorpio as a real, enrollable course platform: ingest course content into the database, let anyone browse it publicly, and let a logged-in user enroll in a course by paying from their existing Scorpio wallet.

## Content source

`Courses/<CODE>-<slug>/` folders, each already structured as:
```
00-course-info/{intro-and-summary.md, pricing-and-format.md, ...}
selfpaced/module-NN-<slug>/topic-NN-<slug>/{notes/notes.md, tasks/task.md, slides/README.md, videos/video.md, demo/{problem,try-it,solution}/}
daily-class/week-NN-<slug>/day-NN-<slug>/...   (same content, different labels — NOT ingested in v1, see Decisions)
```
`Courses/COURSE-INDEX.md` is the master catalog (code, title, slug, status) referenced during import.

Import always **copies content into Scorpio's own database/storage** — it never reads from this external path at request time. That path is a local machine directory and won't exist in any deployed environment (mirrors how `EpubParsingService` copies EPUB content into `storage/app/public/books/` rather than reading the source file per-request).

## Decisions made during brainstorming (binding for this spec)

1. **Target:** built into Scorpio (this repo), not a separate standalone app. Reuses existing auth (`User` + Spatie roles), admin layout, and the `Book→Chapter` architectural pattern.
2. **v1 scope:** ingestion + public browsing + enrollment with a real wallet charge. **Not in v1:** live sessions, live-session request thresholds, cross-course subscriptions, real video generation, the notification system's actual channel delivery.
3. **Track scope:** only the `selfpaced` track is ingested. `daily-class` (Week/Day labels) is the same 20 topics with no v1-relevant behavioral difference — its only real differentiator is scheduled live instruction, which is out of scope. Re-visit once live sessions are built.
4. **Slides:** Gemini-generated on import, one job per topic, output as a `---`-separated markdown slide deck (Marp/reveal.js-style), rendered by a simple in-app prev/next viewer. No new JS dependency — just splitting text on `---`.
5. **Videos:** deferred entirely. A real playable video (TTS + rendered visuals synced to narration) needs tooling that doesn't exist anywhere in this codebase or CourseWork (no TTS provider, no video renderer). The `video` Material is created but stays `not_generated`; the UI shows "Video coming soon." This becomes its own future spec.
6. **Wallet debit/credit:** consolidated into one `WalletService` used by both the existing top-up flow and the new enrollment flow, instead of two copies of the same balance-update-and-log logic.
7. **Notifications on wallet change:** **out of scope for this spec.** See "Notification hook (deferred)" below — `WalletService` calls a narrow, already-defined interface so wiring the real notification system later is a small follow-up change, not a redesign.
8. **Pricing:** only the per-course `Tiers` table in each `pricing-and-format.md` is ingested into `PricingTier`. The `Subscription` section (cross-course, recurring, all-access) is a materially different feature not modeled anywhere in CourseWork's own `data-model.md` either — excluded.

## Data model

```
Course
  id, code (unique, e.g. "C001-HTML-101"), title, slug (unique), subtitle,
  description, status (pending|importing|ready|failed), status_reason,
  source_path (which Courses/<dir> this came from), imported_at, timestamps

  hasMany Module (ordered by sort_order)
  hasMany PricingTier

Module
  id, course_id, title, slug, sort_order, timestamps
  belongsTo Course
  hasMany Topic (ordered by sort_order)

Topic
  id, module_id, title, slug, sort_order, timestamps
  belongsTo Module
  hasMany Material

Material
  id, topic_id,
  type (enum: notes|task|slides|demo_problem|demo_try_it|demo_solution|video),
  content (longtext — markdown for notes/task/slides, HTML for the 3 demo files),
  download_policy (enum: downloadable|view_only),
  status (enum: ready|generating|not_generated|failed — only meaningful for
    slides/video; everything else is `ready` the moment it's imported),
  timestamps
  belongsTo Topic

PricingTier
  id, course_id, name, price_inr_paise, price_usd_cents, description, timestamps
  belongsTo Course
  hasMany Enrollment

Enrollment
  id, user_id, course_id, pricing_tier_id, amount_paise_charged, enrolled_at, timestamps
  belongsTo User, Course, PricingTier
```

`download_policy` per type (from CourseWork's `content-access-policy.md`, already decided there — not re-litigated here):

| type | download_policy |
|---|---|
| `notes` | downloadable |
| `demo_problem` | downloadable |
| `slides` | view_only |
| `videos` | view_only (moot until videos exist) |
| `tasks` | view_only |
| `demo_try_it` | view_only |
| `demo_solution` | view_only |

## Ingestion

`php artisan edtech:import-courses {path=Courses}` — same shape as the existing `library:seed-directory`:

1. Read `COURSE-INDEX.md` for the code→title→slug→status table; skip rows not marked "Generated, content complete."
2. Per course: read `00-course-info/intro-and-summary.md` (→ `Course.description`) and `pricing-and-format.md`'s `## Tiers` table (→ `PricingTier` rows — parse the markdown table directly, no LLM involved, it's already structured data; `₹5,999` / `$149` strings are parsed into `price_inr_paise` (599900) / `price_usd_cents` (14900), matching the existing wallet system's paise-based integer convention).
3. Walk `selfpaced/module-*/topic-*/`, creating `Module`/`Topic` rows from folder slugs and each folder's `README.md` title line.
4. Per topic, create `Material` rows by copying file contents verbatim: `notes/notes.md`→`notes`, `tasks/task.md`→`task`, `demo/problem/*`→`demo_problem`, `demo/try-it/*`→`demo_try_it`, `demo/solution/*`→`demo_solution`. Create (but don't populate) a `slides` row (`status = generating`, dispatches `GenerateTopicSlidesJob`) and a `video` row (`status = not_generated`, nothing dispatched).
5. Re-running import for an already-imported course (matched by `code`) updates existing rows in place rather than duplicating — same idempotency approach as `Book`'s retry flow.
6. `Course.status` moves `pending → importing → ready` (or `failed`, with `status_reason` set, on any error — mirrors `Book`).

## Slide generation

`GenerateTopicSlidesJob(Topic $topic)`, dispatched once per topic at the end of import — same isolation pattern as `IndexBookChunksJob` (per-topic failure doesn't fail the batch; failures are logged and leave that topic's `slides` Material at `status = failed`, retriable independently).

Prompt (new, since none exists in CourseWork — only the video prompt does):
```
Read this topic's notes below. Summarize it into a markdown slide deck for
a beginner-friendly coding course. Rules:
- One slide per major concept or section heading in the notes, in the same order.
- Each slide: a short title line, then 3-6 bullet points, plain language.
- Include short code snippets from the notes verbatim where they illustrate
  a concept — don't invent new examples.
- Separate slides with a line containing exactly "---".
- Output ONLY the slide deck, no commentary before or after.

Notes:
{{notes.md content}}
```
Calls the existing `GeminiClient::generate()` — no new client code needed, this is a text-generation call identical in shape to `ChatService`'s usage.

## Public routes & UI

Mirrors the library exactly:

| Route | Purpose |
|---|---|
| `GET /courses` | Paginated course list (15/page) |
| `GET /courses/{slug}` | Course detail: description, module/topic TOC, pricing tiers, enroll button |
| `GET /courses/{slug}/topics/{sort_order}` | Topic viewer: tabs for Notes / Task / Demo (problem, try-it, solution) / Slides / Video |

Topic viewer enforcement of `download_policy`: `downloadable` materials get a download link (served through an authenticated download action, not a public storage URL); everything else renders inline from the Inertia response — no separately fetchable file URL exists for view-only content, same as how chapter content is embedded today.

`video` tab shows a "Video coming soon" empty state whenever `status = not_generated`.

## Admin UI

- `Admin/EdTech/Index.vue` — list of imported courses (status, module/topic counts, re-import trigger). Read-only over content; there's no admin-authored-content flow since the source is the fixed `Courses/` folder, not uploads.
- `Admin/EdTech/Enrollments.vue` — list of enrollments (learner, course, tier, amount charged, date) for revenue visibility.

## Enrollment + wallet

**`app/Services/WalletService.php`** (new — consolidates logic currently inlined in `WalletTopUpController`):
```php
public function credit(User $user, int $amountPaise, string $category, ?array $meta = null): WalletTransaction
public function debit(User $user, int $amountPaise, string $category, ?array $meta = null): WalletTransaction
```
Both wrap the existing `DB::transaction` + balance-update + `WalletTransaction::create` pattern. `debit()` throws a domain exception (`InsufficientWalletBalanceException`) if `amount > current balance` — caught by the controller and turned into a clean validation error, same shape as existing wallet error handling. `wallet_transactions.category` enum gains `'enrollment'` alongside the existing `topup|subscription|refund|adjustment`.

`WalletTopUpController` is refactored to call `WalletService::credit()` instead of its inline logic (pure refactor, no behavior change — covered by its existing tests before and after).

**`CourseEnrollmentController::store(Course $course, PricingTier $tier)`:**
1. `WalletService::debit($request->user(), $tier->price_inr_paise, 'enrollment', ['course_id' => $course->id, 'tier_id' => $tier->id])`.
2. Create `Enrollment` row.
3. Insufficient balance → `back()->withErrors(['balance' => '...'])`, no partial state (the `WalletTransaction` is never written if the debit is rejected — enforced inside the same DB transaction).

### Notification hook (deferred)

`WalletService::credit()`/`debit()` each call one line at the end:
```php
app(NotificationSender::class)->send(new WalletBalanceChanged($user, $transaction));
```
`NotificationSender` is a **contract (interface) defined in this spec, implemented in the separate Notification System spec**:
```php
interface NotificationSender
{
    public function send(object $notification): void;
}
```
For this spec, the bound implementation is a no-op (`NullNotificationSender`, logs at `debug` level and returns) registered in a service provider. This means:
- `WalletService` and `CourseEnrollmentController` are fully testable and functional today without the notification system existing yet.
- When the Notification System spec ships, swapping the service-container binding from `NullNotificationSender` to the real implementation is the entire integration cost — no changes to `WalletService` itself.

## Testing

Following this session's established pattern (fixture builder + fakes, no dependency on the real `Courses/` folder or real Gemini calls):

- `tests/support/CourseFixtureBuilder.php` — builds a minimal fake course folder tree (parallel to `EpubFixtureBuilder`).
- `EdTechImportTest` — asserts Course/Module/Topic/Material/PricingTier rows created correctly from a fixture; re-import updates in place; skips `daily-class`.
- `GenerateTopicSlidesJobTest` — `Http::fake()` for the Gemini call; asserts slide content stored, `status` transitions, per-topic failure isolation.
- `CourseBrowsingTest` — public routes render, `download_policy` enforced (downloadable materials expose a download link, view-only ones don't expose a raw file URL).
- `WalletServiceTest` — credit/debit balance math, insufficient-balance exception, transaction row written, `NotificationSender::send()` called exactly once per operation (asserted via a mock — proves the hook fires without depending on real delivery).
- `WalletTopUpControllerTest` — existing tests continue passing after the refactor to use `WalletService::credit()`.
- `CourseEnrollmentTest` — happy path creates `Enrollment` + debits wallet; insufficient balance creates neither.

## Out of scope (explicitly, not oversights)

- `daily-class` track ingestion (revisit with live sessions)
- Live session request/scheduling/threshold logic
- Cross-course subscriptions
- Real video generation (TTS + rendering) — separate future spec
- Real notification delivery (SMS/push/mail sending) — separate spec, being brainstormed in a different session; this spec only defines the `NotificationSender` contract `WalletService` calls
