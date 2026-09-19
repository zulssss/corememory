<?php

declare(strict_types=1);

namespace App\Http\Controllers\Public;

use App\Enums\ProjectCategory;
use App\Http\Controllers\Controller;
use App\Models\Project;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;

class WorkController extends Controller
{
    /** The portfolio index, filterable by category. */
    public function index(Request $request): View
    {
        // tryFrom rather than from: an unknown ?category= in the URL should
        // show everything, not throw a 500.
        $category = ProjectCategory::tryFrom((string) $request->query('category'));

        $projects = Project::query()
            ->published()
            ->category($category)
            ->ordered()
            ->with('media')
            ->paginate(12)
            ->withQueryString();

        return view('pages.work.index', [
            'projects' => $projects,
            'activeCategory' => $category,
        ]);
    }

    /** One wedding story. */
    public function show(Project $project): View
    {
        abort_unless($project->published_at !== null && $project->published_at->isPast(), 404);

        $project->load('media', 'testimonials');

        return view('pages.work.show', [
            'project' => $project,
            'gallery' => $project->getMedia('gallery'),
            'next' => $project->nextProject(),
            'previous' => $project->previousProject(),
        ]);
    }
}
