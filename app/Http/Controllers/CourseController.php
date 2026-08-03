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
}
