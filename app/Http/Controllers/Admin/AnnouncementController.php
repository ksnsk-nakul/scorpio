<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Announcement;
use Illuminate\Http\Request;
use Inertia\Inertia;

class AnnouncementController extends Controller
{
    public function index()
    {
        return Inertia::render('Admin/Announcements/Index', [
            'announcements' => Announcement::latest()->get(),
        ]);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'title'     => 'required|string|max:150',
            'body'      => 'required|string|max:1000',
            'type'      => 'required|in:info,warning,success,ad',
            'display'   => 'required|in:banner,modal',
            'cta_label' => 'nullable|string|max:50',
            'cta_url'   => 'nullable|url|max:255',
            'active'    => 'boolean',
            'starts_at' => 'nullable|date',
            'ends_at'   => 'nullable|date|after_or_equal:starts_at',
        ]);

        Announcement::create($data);
        return back()->with('success', 'Announcement created.');
    }

    public function update(Request $request, Announcement $announcement)
    {
        $data = $request->validate([
            'title'     => 'sometimes|string|max:150',
            'body'      => 'sometimes|string|max:1000',
            'type'      => 'sometimes|in:info,warning,success,ad',
            'display'   => 'sometimes|in:banner,modal',
            'cta_label' => 'nullable|string|max:50',
            'cta_url'   => 'nullable|url|max:255',
            'active'    => 'boolean',
            'starts_at' => 'nullable|date',
            'ends_at'   => 'nullable|date',
        ]);

        $announcement->update($data);
        return back()->with('success', 'Announcement updated.');
    }

    public function destroy(Announcement $announcement)
    {
        $announcement->delete();
        return back()->with('success', 'Announcement deleted.');
    }
}
