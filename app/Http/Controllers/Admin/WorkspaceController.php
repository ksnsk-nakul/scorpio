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
            'workspaces' => Workspace::withCount('projects')->orderBy('name')->get(),
        ]);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'name'        => 'required|string|max:255',
            'description' => 'nullable|string',
        ]);
        Workspace::create($data);
        return redirect('/admin/workspaces')->with('success', 'Workspace created.');
    }

    public function update(Request $request, Workspace $workspace)
    {
        $workspace->update($request->validate([
            'name'        => 'required|string|max:255',
            'description' => 'nullable|string',
        ]));
        return redirect('/admin/workspaces')->with('success', 'Workspace updated.');
    }

    public function destroy(Workspace $workspace)
    {
        $workspace->delete();
        return redirect('/admin/workspaces')->with('success', 'Workspace deleted.');
    }
}
