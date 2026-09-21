<?php

declare(strict_types=1);

namespace App\Http\Controllers\Public;

use App\Http\Controllers\Controller;
use App\Models\Post;
use Illuminate\Contracts\View\View;

class JournalController extends Controller
{
    public function index(): View
    {
        return view('pages.journal.index', [
            'posts' => Post::query()
                ->published()
                ->with('media')          // cover images, or one query per card
                ->latest('published_at')
                ->paginate(9),
        ]);
    }

    public function show(Post $post): View
    {
        abort_unless($post->published_at !== null && $post->published_at->isPast(), 404);

        $post->load('media');

        return view('pages.journal.show', [
            'post' => $post,
            'more' => Post::query()
                ->published()
                ->whereKeyNot($post->getKey())
                ->with('media')
                ->latest('published_at')
                ->limit(2)
                ->get(),
        ]);
    }
}
