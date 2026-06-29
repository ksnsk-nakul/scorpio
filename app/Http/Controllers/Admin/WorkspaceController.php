<?php
namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Workspace;
use Illuminate\Http\Request;
use Inertia\Inertia;

class WorkspaceController extends Controller
{
    public function index()
    {
        return Inertia::render('Admin/Workspaces/Index', [
            'workspaces' => auth()->user()->workspaces()->withCount('projects')->orderBy('name')->get(),
        ]);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'name'        => 'required|string|max:255',
            'description' => 'nullable|string',
        ]);
        $data['user_id'] = auth()->id();
        Workspace::create($data);
        return redirect('/admin/workspaces')->with('success', 'Workspace created.');
    }

    public function update(Request $request, Workspace $workspace)
    {
        abort_if($workspace->user_id !== auth()->id(), 403);

        $workspace->update($request->validate([
            'name'        => 'required|string|max:255',
            'description' => 'nullable|string',
        ]));
        return redirect('/admin/workspaces')->with('success', 'Workspace updated.');
    }

    public function destroy(Workspace $workspace)
    {
        abort_if($workspace->user_id !== auth()->id(), 403);

        $workspace->delete();
        return redirect('/admin/workspaces')->with('success', 'Workspace deleted.');
    }
}
