@props([
    'article',
    'bookmarkedIds' => [],
    'compact' => false,
    'showExcerpt' => true,
])

@php
    $categoryColor = $article->category?->color ?: '#0284c7';
    $visibleTags = $article->tags->take($compact ? 2 : 3);
    $cardRadiusClass = $compact ? 'rounded-[1.5rem]' : 'rounded-[1.75rem]';
    $contentGapClass = $compact ? 'gap-4' : 'gap-5';
    $headerGapClass = $compact ? 'gap-1.5' : 'gap-2';
    $bodySpacingClass = $compact ? 'space-y-2.5' : 'space-y-3';
    $titleClass = $compact
        ? 'text-balance text-xl font-black leading-7 transition hover:text-sky-600 dark:hover:text-sky-300 sm:text-[1.35rem]'
        : 'text-balance text-2xl font-black leading-tight transition hover:text-sky-600 dark:hover:text-sky-300';
    $excerptClass = $compact
        ? 'line-clamp-2 text-sm leading-6 text-base-content/65'
        : 'text-sm leading-7 text-base-content/70';
    $footerClass = $compact
        ? 'mt-auto flex flex-wrap items-center justify-between gap-3 border-t border-base-300/70 pt-3 text-[0.8125rem] text-base-content/60'
        : 'mt-auto flex flex-wrap items-center justify-between gap-4 border-t border-base-300/70 pt-4 text-sm text-base-content/60';
    $metaClass = $compact ? 'flex flex-wrap items-center gap-x-3 gap-y-1.5' : 'flex flex-wrap items-center gap-4';
    $actionClass = $compact ? 'flex items-center gap-1.5' : 'flex items-center gap-2';
    $readLabel = $compact ? 'Открыть' : 'Читать';
@endphp

<x-mary-card class="portal-surface h-full {{ $cardRadiusClass }}">
    <div class="flex h-full flex-col {{ $contentGapClass }}">
        <div class="flex flex-wrap items-center {{ $headerGapClass }}">
            @if($article->is_breaking)
                <x-mary-badge class="badge-error badge-soft border-0 {{ $compact ? 'badge-sm' : '' }}">Срочно</x-mary-badge>
            @endif

            @if($article->is_featured)
                <x-mary-badge class="badge-success badge-soft border-0 {{ $compact ? 'badge-sm' : '' }}">Подборка</x-mary-badge>
            @endif

            @if($article->category)
                <a href="{{ route('category.show', ['slug' => $article->category->slug]) }}">
                    <x-mary-badge
                        class="badge-outline border {{ $compact ? 'badge-sm' : '' }}"
                        style="border-color: {{ $categoryColor }}; color: {{ $categoryColor }};"
                    >
                        {{ $article->category->name }}
                    </x-mary-badge>
                </a>
            @endif

            @foreach($visibleTags as $tag)
                <a href="{{ route('tag.show', ['slug' => $tag->slug]) }}">
                    <x-mary-badge class="badge-ghost border-0 {{ $compact ? 'badge-sm' : '' }}">#{{ $tag->name }}</x-mary-badge>
                </a>
            @endforeach
        </div>

        <div class="{{ $bodySpacingClass }}">
            <a class="block {{ $titleClass }}" href="{{ route('articles.show', ['slug' => $article->slug]) }}">
                {{ $article->title }}
            </a>

            @if($showExcerpt && filled($article->short_description))
                <p class="{{ $excerptClass }}">
                    {{ $article->short_description }}
                </p>
            @endif
        </div>

        <div class="{{ $footerClass }}">
            <div class="{{ $metaClass }}">
                <span>{{ $article->published_at?->translatedFormat('d M Y, H:i') }}</span>
                <span>{{ $article->reading_time_text }}</span>
                <span>{{ number_format((int) $article->views_count, 0, ',', ' ') }} просмотров</span>
            </div>

            <div class="{{ $actionClass }}">
                <x-public.bookmark-button :article="$article" :bookmarked-ids="$bookmarkedIds" />
                <x-mary-button
                    class="btn-primary btn-sm"
                    icon-right="o-arrow-up-right"
                    label="{{ $readLabel }}"
                    link="{{ route('articles.show', ['slug' => $article->slug]) }}"
                    no-wire-navigate
                />
            </div>
        </div>
    </div>
</x-mary-card>
