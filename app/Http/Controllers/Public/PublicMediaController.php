<?php

namespace App\Http\Controllers\Public;

use App\Http\Controllers\Controller;
use App\Models\MediaPhoto;
use App\Models\News;

class PublicMediaController extends Controller
{
    public function __invoke()
    {
        $news = News::published()
            ->latest('published_at')
            ->paginate(12, ['*'], 'news_page');

        $photos = MediaPhoto::active()
            ->orderBy('sort_order')
            ->orderByDesc('created_at')
            ->get()
            ->groupBy(fn ($p) => $p->album ?? 'عام');

        return view('public.media', compact('news', 'photos'));
    }
}
