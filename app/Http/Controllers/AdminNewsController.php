<?php

namespace App\Http\Controllers;

use App\Models\News;
use App\Support\LauncherCache;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class AdminNewsController extends Controller
{
    public function index()
    {
        $articles = News::query()
            ->orderByDesc('is_pinned')
            ->orderByDesc('published_at')
            ->orderByDesc('id')
            ->get();

        return view('admin.news.index', compact('articles'));
    }

    public function create()
    {
        return view('admin.news.create');
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'title' => 'required|string|max:255',
            'slug' => 'nullable|string|max:255',
            'content' => 'required|string',
            'author' => 'nullable|string|max:100',
            'image' => 'nullable|image|mimes:jpeg,png,jpg,gif,webp|max:4096',
            'image_url' => 'nullable|url|max:500',
            'badge' => 'nullable|string|max:100',
            'external_url' => 'nullable|url|max:500',
            'is_pinned' => 'boolean',
            'is_published' => 'boolean',
            'published_at' => 'nullable|date',
        ]);

        $slug = $this->uniqueSlug($validated['slug'] ?? null, $validated['title']);
        $imagePath = $request->hasFile('image')
            ? $request->file('image')->store('news', 'public')
            : null;

        $isPublished = $request->boolean('is_published', true);
        $publishedAt = !empty($validated['published_at'])
            ? $validated['published_at']
            : ($isPublished ? now() : null);

        $article = News::create([
            'title' => $validated['title'],
            'slug' => $slug,
            'content' => $validated['content'],
            'author' => trim((string) ($validated['author'] ?? '')) ?: (Auth::user()?->name ?? 'Admin'),
            'image_path' => $imagePath,
            'image_url' => $validated['image_url'] ?? null,
            'badge' => $validated['badge'] ?? null,
            'external_url' => $validated['external_url'] ?? null,
            'is_pinned' => $request->boolean('is_pinned'),
            'is_published' => $isPublished,
            'published_at' => $publishedAt,
        ]);

        LauncherCache::flushMetadata();

        return redirect()->route('admin.news')->with('success', __('messages.flash.news_created', ['title' => $article->title]));
    }

    public function edit(News $news)
    {
        return view('admin.news.edit', ['article' => $news]);
    }

    public function update(Request $request, News $news)
    {
        $validated = $request->validate([
            'title' => 'required|string|max:255',
            'slug' => ['nullable', 'string', 'max:255', Rule::unique('news', 'slug')->ignore($news->id)],
            'content' => 'required|string',
            'author' => 'nullable|string|max:100',
            'image' => 'nullable|image|mimes:jpeg,png,jpg,gif,webp|max:4096',
            'image_url' => 'nullable|url|max:500',
            'remove_image' => 'boolean',
            'badge' => 'nullable|string|max:100',
            'external_url' => 'nullable|url|max:500',
            'is_pinned' => 'boolean',
            'is_published' => 'boolean',
            'published_at' => 'nullable|date',
        ]);

        $slug = !empty($validated['slug'])
            ? Str::slug($validated['slug'])
            : $news->slug;
        if ($slug !== $news->slug) {
            $slug = $this->uniqueSlug($slug, $validated['title'], $news->id);
        }

        $imagePath = $news->image_path;
        if ($request->boolean('remove_image') && $imagePath) {
            Storage::disk('public')->delete($imagePath);
            $imagePath = null;
        }
        if ($request->hasFile('image')) {
            if ($imagePath) {
                Storage::disk('public')->delete($imagePath);
            }
            $imagePath = $request->file('image')->store('news', 'public');
        }

        $isPublished = $request->boolean('is_published');
        $publishedAt = !empty($validated['published_at'])
            ? $validated['published_at']
            : ($news->published_at ?? ($isPublished ? now() : null));

        $news->update([
            'title' => $validated['title'],
            'slug' => $slug,
            'content' => $validated['content'],
            'author' => trim((string) ($validated['author'] ?? '')) ?: $news->author,
            'image_path' => $imagePath,
            'image_url' => $validated['image_url'] ?? null,
            'badge' => $validated['badge'] ?? null,
            'external_url' => $validated['external_url'] ?? null,
            'is_pinned' => $request->boolean('is_pinned'),
            'is_published' => $isPublished,
            'published_at' => $publishedAt,
        ]);

        LauncherCache::flushMetadata();

        return redirect()->route('admin.news')->with('success', __('messages.flash.news_updated', ['title' => $news->title]));
    }

    public function destroy(News $news)
    {
        $title = $news->title;
        if ($news->image_path) {
            Storage::disk('public')->delete($news->image_path);
        }
        $news->delete();
        LauncherCache::flushMetadata();

        return redirect()->route('admin.news')->with('success', __('messages.flash.news_deleted', ['title' => $title]));
    }

    private function uniqueSlug(?string $customSlug, string $title, ?int $ignoreId = null): string
    {
        $base = Str::slug($customSlug ?: $title) ?: ('news-' . Str::lower(Str::random(6)));
        $slug = $base;
        $counter = 2;

        while (
            News::where('slug', $slug)
                ->when($ignoreId, fn ($q) => $q->where('id', '!=', $ignoreId))
                ->exists()
        ) {
            $slug = "{$base}-{$counter}";
            $counter++;
        }

        return $slug;
    }
}
