<?php

namespace App\Http\Controllers\api;

use App\Http\Controllers\Controller;
use App\Models\News;
use App\Support\LauncherCache;
use App\Support\LauncherResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class NewsController extends Controller
{
    public function index(Request $request): Response
    {
        return $this->getNews($request);
    }

    public function getNews(Request $request): Response
    {
        $domain = $request->getHost();
        $protocol = $request->isSecure() ? 'https' : 'http';
        $port = $request->getPort();
        $baseURL = $protocol . '://' . $domain . (($port && !in_array($port, [80, 443], true)) ? ":$port" : '');

        return LauncherResponse::json($request, self::getCachedPublishedNews($baseURL));
    }

    public static function getCachedPublishedNews(string $baseURL): array
    {
        $baseURL = rtrim($baseURL, '/');

        return LauncherCache::remember('launcher_news', sha1($baseURL), now()->addMinutes(30), static function () use ($baseURL): array {
            return News::query()
                ->where('is_published', true)
                ->orderByDesc('is_pinned')
                ->orderByDesc('published_at')
                ->orderByDesc('id')
                ->get()
                ->map(static function (News $article) use ($baseURL): array {
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
    }
}
