<?php

namespace App\Http\Controllers;

use App\Models\Course;
use App\Models\Enrollment;
use App\Models\PricingTier;
use Illuminate\Database\QueryException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
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
            $enrollment = DB::transaction(function () use ($user, $course, $tier) {
                $user->debitWallet($tier->price_inr_paise, 'enrollment', "Enrollment: {$course->title} ({$tier->name})");

                return Enrollment::create([
                    'user_id' => $user->id,
                    'course_id' => $course->id,
                    'pricing_tier_id' => $tier->id,
                    'amount_paise_charged' => $tier->price_inr_paise,
                    'enrolled_at' => now(),
                ]);
            });
        } catch (RuntimeException $e) {
            return response()->json(['message' => 'Insufficient wallet balance for this tier.'], 422);
        } catch (QueryException $e) {
            // The exists() check above isn't atomic with the debit — a concurrent
            // request for the same user+course can pass it before either inserts.
            // The unique(user_id, course_id) constraint then makes Enrollment::create()
            // throw, which rolls back the whole transaction (debit + wallet_transactions
            // row + this insert) together — no manual refund needed, the user was never
            // actually charged for the losing request.
            return response()->json(['message' => 'Already enrolled in this course.'], 422);
        }

        return response()->json(['id' => $enrollment->id, 'course_slug' => $course->slug]);
    }
}
