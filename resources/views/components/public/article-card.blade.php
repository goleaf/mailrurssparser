@props([
    'article',
    'bookmarkedIds' => [],
    'showExcerpt' => true,
])

@php
    $categoryColor = $article->category?->color ?: '#0284c7';
@endphp

<x-mary-card class="portal-surface h-full rounded-[1.75rem]">
    <div class="flex h-full flex-col gap-5">
        <div class="flex flex-wrap items-center gap-2">
            @if($article->is_breaking)
                <x-mary-badge class="badge-error badge-soft border-0">Срочно</x-mary-badge>
            @endif

            @if($article->is_featured)
                <x-mary-badge class="badge-success badge-soft border-0">Подборка</x-mary-badge>
            @endif

            @if($article->category)
                <a href="{{ route('category.show', ['slug' => $article->category->slug]) }}">
                    <x-mary-badge
                        class="badge-outline border"
                        style="border-color: {{ $categoryColor }}; color: {{ $categoryColor }};"
                    >
                        {{ $article->category->name }}
                    </x-mary-badge>
                </a>
            @endif

            @foreach($article->tags->take(3) as $tag)
                <a href="{{ route('tag.show', ['slug' => $tag->slug]) }}">
                    <x-mary-badge class="badge-ghost border-0">#{{ $tag->name }}</x-mary-badge>
                </a>
            @endforeach
        </div>

        <div class="space-y-3">
            <a class="block text-balance text-2xl font-black leading-tight transition hover:text-sky-600 dark:hover:text-sky-300" href="{{ route('articles.show', ['slug' => $article->slug]) }}">
                {{ $article->title }}
            </a>

            @if($showExcerpt && filled($article->short_description))
                <p class="text-sm leading-7 text-base-content/70">
                    {{ $article->short_description }}
                </p>
            @endif
        </div>

        <div class="mt-auto flex flex-wrap items-center justify-between gap-4 border-t border-base-300/70 pt-4 text-sm text-base-content/60">
            <div class="flex flex-wrap items-center gap-4">
                <span>{{ $article->published_at?->translatedFormat('d M Y, H:i') }}</span>
                <span>{{ $article->reading_time_text }}</span>
                <span>{{ number_format((int) $article->views_count, 0, ',', ' ') }} просмотров</span>
            </div>

            <div class="flex items-center gap-2">
                <x-public.bookmark-button :article="$article" :bookmarked-ids="$bookmarkedIds" />
                <x-mary-button
                    class="btn-primary btn-sm"
                    icon-right="o-arrow-up-right"
                    label="Читать"
                    link="{{ route('articles.show', ['slug' => $article->slug]) }}"
                    no-wire-navigate
                />
            </div>
        </div>
    </div>
</x-mary-card>
