<?php

namespace App\Http\Controllers\Public;

use App\Http\Controllers\Controller;
use App\Models\News;

class PublicNewsController extends Controller
{
    public function index()
    {
        $news = News::published()
            ->latest('published_at')
            ->paginate(12);

        return view('public.news.index', compact('news'));
    }

    public function show(News $news)
    {
        abort_if(
            $news->published_at === null || $news->published_at->isFuture(),
            404
        );

        return view('public.news.show', compact('news'));
    }
}
