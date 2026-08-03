<?php

namespace App\Http\Controllers;

use App\Models\Course;
use App\Models\Enrollment;
use App\Models\PricingTier;
use Illuminate\Database\QueryException;
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

        try {
            $enrollment = Enrollment::create([
                'user_id' => $user->id,
                'course_id' => $course->id,
                'pricing_tier_id' => $tier->id,
                'amount_paise_charged' => $tier->price_inr_paise,
                'enrolled_at' => now(),
            ]);
        } catch (QueryException $e) {
            // The exists() check above isn't atomic with the debit — a concurrent
            // request for the same user+course can pass both checks and both debit
            // the wallet before either inserts. The unique(user_id, course_id)
            // constraint catches the second insert; refund the debit it already
            // took so the user isn't charged twice for one enrollment.
            $user->creditWallet($tier->price_inr_paise, 'refund', "Refund: duplicate enrollment attempt for {$course->title}");

            return response()->json(['message' => 'Already enrolled in this course.'], 422);
        }

        return response()->json(['id' => $enrollment->id, 'course_slug' => $course->slug]);
    }
}
