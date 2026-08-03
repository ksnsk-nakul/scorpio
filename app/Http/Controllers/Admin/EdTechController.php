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
                    'id' => $e->id,
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
