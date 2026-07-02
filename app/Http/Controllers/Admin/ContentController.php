<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AboutSection;
use App\Models\ExperienceItem;
use App\Models\Skill;
use App\Support\ProfanityGuard;
use Illuminate\Http\Request;
use Inertia\Inertia;

class ContentController extends Controller
{
    public function index(Request $request)
    {
        $user = $request->user();

        return Inertia::render('Admin/Content/Index', [
            'skills'      => Skill::where('user_id', $user->id)->orderBy('sort_order')->orderBy('name')->get(),
            'about'       => AboutSection::where('user_id', $user->id)->first(),
            'experiences' => ExperienceItem::where('user_id', $user->id)->orderBy('sort_order')->get(),
            'isDemo'      => app()->environment('demo'),
            'skillLimit'  => $user->planLimit('skills'),
        ]);
    }

    // ── Skills ──────────────────────────────────────────────────────────────

    public function storeSkill(Request $request)
    {
        $user = $request->user();
        $data = $request->validate([
            'name' => 'required|string|max:60',
            'icon' => 'required|string|max:10',
        ]);

        if (ProfanityGuard::fails($data['name'])) {
            return back()->withErrors(['name' => 'Skill name contains inappropriate content.']);
        }

        $exists = Skill::where('user_id', $user->id)
            ->whereRaw('LOWER(name) = ?', [strtolower($data['name'])])
            ->exists();
        if ($exists) {
            return back()->withErrors(['name' => 'You already have a skill with this name.']);
        }

        $count = Skill::where('user_id', $user->id)->count();
        if (! $user->withinLimit('skills', $count)) {
            $limit = $user->planLimit('skills');
            return back()->withErrors(['name' => "Your plan allows up to {$limit} skills. Upgrade to add more."]);
        }

        $max = Skill::where('user_id', $user->id)->max('sort_order') ?? -1;
        Skill::create(array_merge($data, ['user_id' => $user->id, 'sort_order' => $max + 1]));

        return back()->with('success', 'Skill added.');
    }

    public function updateSkill(Request $request, Skill $skill)
    {
        $this->authorize('update', $skill);
        abort_if(app()->environment('demo') && $skill->is_seeded && ! $request->user()->hasRole('admin'), 403, 'Cannot edit seeded content in demo mode.');

        $data = $request->validate([
            'name' => 'required|string|max:60',
            'icon' => 'required|string|max:10',
        ]);

        if (ProfanityGuard::fails($data['name'])) {
            return back()->withErrors(['name' => 'Skill name contains inappropriate content.']);
        }

        $exists = Skill::where('user_id', $request->user()->id)
            ->whereRaw('LOWER(name) = ?', [strtolower($data['name'])])
            ->where('id', '!=', $skill->id)
            ->exists();
        if ($exists) {
            return back()->withErrors(['name' => 'You already have a skill with this name.']);
        }

        $skill->update($data);
        return back()->with('success', 'Skill updated.');
    }

    public function destroySkill(Request $request, Skill $skill)
    {
        $this->authorize('delete', $skill);
        abort_if(app()->environment('demo') && $skill->is_seeded && ! $request->user()->hasRole('admin'), 403, 'Cannot delete seeded content in demo mode.');
        $skill->delete();
        return back()->with('success', 'Skill removed.');
    }

    public function reorderSkills(Request $request)
    {
        $request->validate(['order' => 'required|array', 'order.*' => 'integer']);
        $user = $request->user();
        foreach ($request->order as $i => $id) {
            Skill::where('id', $id)->where('user_id', $user->id)->update(['sort_order' => $i]);
        }
        return response()->noContent();
    }

    // ── About ────────────────────────────────────────────────────────────────

    public function updateAbout(Request $request)
    {
        $user = $request->user();
        $data = $request->validate([
            'bio'      => 'nullable|string|max:3000',
            'overview' => 'nullable|array|max:10',
            'overview.*' => 'string|max:200',
        ]);

        $about = AboutSection::firstOrNew(['user_id' => $user->id]);
        abort_if(app()->environment('demo') && $about->exists && $about->is_seeded && ! $user->hasRole('admin'), 403, 'Cannot edit seeded content in demo mode.');

        $about->fill(array_merge($data, ['user_id' => $user->id]));
        $about->save();

        return back()->with('success', 'About updated.');
    }

    // ── Experience ───────────────────────────────────────────────────────────

    public function storeExperience(Request $request)
    {
        $user = $request->user();
        $data = $request->validate([
            'period'      => 'required|string|max:30',
            'title'       => 'required|string|max:120',
            'company'     => 'nullable|string|max:120',
            'description' => 'nullable|string|max:1000',
        ]);

        if (ProfanityGuard::fails($data['title'] . ' ' . ($data['description'] ?? ''))) {
            return back()->withErrors(['title' => 'Content contains inappropriate language.']);
        }

        $max = ExperienceItem::where('user_id', $user->id)->max('sort_order') ?? -1;
        ExperienceItem::create(array_merge($data, ['user_id' => $user->id, 'sort_order' => $max + 1]));

        return back()->with('success', 'Experience added.');
    }

    public function updateExperience(Request $request, ExperienceItem $experience)
    {
        $this->authorize('update', $experience);
        abort_if(app()->environment('demo') && $experience->is_seeded && ! $request->user()->hasRole('admin'), 403, 'Cannot edit seeded content in demo mode.');

        $data = $request->validate([
            'period'      => 'required|string|max:30',
            'title'       => 'required|string|max:120',
            'company'     => 'nullable|string|max:120',
            'description' => 'nullable|string|max:1000',
        ]);

        if (ProfanityGuard::fails($data['title'] . ' ' . ($data['description'] ?? ''))) {
            return back()->withErrors(['title' => 'Content contains inappropriate language.']);
        }

        $experience->update($data);
        return back()->with('success', 'Experience updated.');
    }

    public function destroyExperience(Request $request, ExperienceItem $experience)
    {
        $this->authorize('delete', $experience);
        abort_if(app()->environment('demo') && $experience->is_seeded && ! $request->user()->hasRole('admin'), 403, 'Cannot delete seeded content in demo mode.');
        $experience->delete();
        return back()->with('success', 'Experience removed.');
    }

    public function reorderExperience(Request $request)
    {
        $request->validate(['order' => 'required|array', 'order.*' => 'integer']);
        $user = $request->user();
        foreach ($request->order as $i => $id) {
            ExperienceItem::where('id', $id)->where('user_id', $user->id)->update(['sort_order' => $i]);
        }
        return response()->noContent();
    }
}
