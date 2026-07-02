<?php

namespace App\Http\Controllers;

use App\Mail\ContactNotification;
use App\Models\ContactSubmission;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Mail;

class ContactController extends Controller
{
    public function store(Request $request)
    {
        $data = $request->validate([
            'name'      => 'required|string|max:100',
            'email'     => 'required|email|max:150',
            'message'   => 'required|string|max:2000',
            'user_id'   => 'required|exists:users,id',
            'page_slug' => 'nullable|string|max:100',
        ]);

        $submission = ContactSubmission::create($data);

        $owner = User::find($data['user_id']);

        if ($owner?->email) {
            try {
                Mail::to($owner->email)->send(new ContactNotification($submission));
            } catch (\Throwable) {
                // Mail failure must not break the form UX
            }
        }

        return back()->with('contact_success', 'Your message has been sent! I\'ll get back to you soon.');
    }
}
