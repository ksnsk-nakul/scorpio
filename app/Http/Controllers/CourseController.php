<?php

namespace App\Http\Controllers;

use App\Models\Course;
use App\Models\Topic;
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
}
