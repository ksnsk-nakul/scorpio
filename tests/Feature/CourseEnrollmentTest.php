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
