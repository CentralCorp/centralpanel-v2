<?php

namespace App\Http\Controllers\api;

use App\Http\Controllers\Controller;
use App\Models\News;
use App\Support\LauncherCache;
use Illuminate\Http\JsonResponse;

class NewsController extends Controller
{
    public function getNews(): JsonResponse
    {
        $domain = request()->getHost();
        $protocol = request()->isSecure() ? 'https' : 'http';
        $port = request()->getPort();
        $baseURL = $protocol . '://' . $domain . (($port && !in_array($port, [80, 443], true)) ? ":$port" : '');

        $items = LauncherCache::remember('launcher_news', sha1($baseURL), now()->addMinutes(5), function () use ($baseURL) {
            return News::query()
                ->where('is_published', true)
                ->orderByDesc('is_pinned')
                ->orderByDesc('published_at')
                ->orderByDesc('id')
                ->get()
                ->map(function (News $article) use ($baseURL) {
                    $image = null;
                    if ($article->image_path) {
                        $image = $baseURL . '/storage/' . ltrim($article->image_path, '/');
                    } elseif ($article->image_url) {
                        $image = $article->image_url;
                    }

                    return [
                        'id' => (int) $article->id,
                        'title' => $article->title,
                        'content' => $article->content,
                        'author' => $article->author,
                        'publish_date' => ($article->published_at ?? $article->created_at)?->toIso8601String(),
                        'image' => $image,
                        'badge' => $article->badge,
                        'is_pinned' => (bool) $article->is_pinned,
                        'external_url' => $article->external_url ?: null,
                    ];
                })
                ->values()
                ->all();
        });

        return response()->json($items, 200, [], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
    }
}
