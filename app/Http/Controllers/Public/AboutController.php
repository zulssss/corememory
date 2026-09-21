<?php

declare(strict_types=1);

namespace App\Http\Controllers\Public;

use App\Http\Controllers\Controller;
use App\Models\Project;
use Illuminate\Contracts\View\View;

class AboutController extends Controller
{
    public function __invoke(): View
    {
        return view('pages.about', [
            // A few real frames so the page isn't all text.
            'projects' => Project::query()
                ->published()
                ->ordered()
                ->with('media')
                ->limit(3)
                ->get(),
        ]);
    }
}
