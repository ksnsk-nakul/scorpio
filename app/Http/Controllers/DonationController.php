<?php

namespace App\Http\Controllers;

use Inertia\Inertia;

class DonationController extends Controller
{
    public function show()
    {
        return Inertia::render('Donate', [
            'meUrl' => config('billing.razorpay.me_url'),
        ]);
    }
}
