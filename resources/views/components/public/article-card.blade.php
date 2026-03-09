@props([
    'article',
    'bookmarkedIds' => [],
    'compact' => false,
    'showExcerpt' => true,
])

@php
    $categoryColor = $article->category?->color ?: '#0284c7';
    $visibleTags = $article->tags->take($compact ? 2 : 3);
    $hasCompactImage = $compact && filled($article->image_url);
    $cardRadiusClass = $compact ? 'rounded-[1.5rem]' : 'rounded-[1.75rem]';
    $contentGapClass = $compact ? 'gap-4' : 'gap-5';
    $headerGapClass = $compact ? 'gap-1.5' : 'gap-2';
    $bodySpacingClass = $compact ? 'space-y-2.5' : 'space-y-3';
    $titleClass = $compact
        ? 'text-balance text-xl font-black leading-7 transition hover:text-sky-600 dark:hover:text-sky-300 sm:text-[1.35rem]'
        : 'text-balance text-2xl font-black leading-tight transition hover:text-sky-600 dark:hover:text-sky-300';
    $excerptClass = $compact
        ? 'line-clamp-3 text-sm leading-6 text-base-content/70'
        : 'text-sm leading-7 text-base-content/70';
    $footerClass = $compact
        ? 'mt-auto flex flex-col gap-3 border-t border-base-300/70 pt-3 text-[0.8125rem] text-base-content/60 sm:flex-row sm:items-end sm:justify-between'
        : 'mt-auto flex flex-col gap-4 border-t border-base-300/70 pt-4 text-sm text-base-content/60 lg:flex-row lg:items-center lg:justify-between';
    $metaClass = $compact ? 'flex flex-wrap gap-2' : 'flex flex-wrap gap-2.5';
    $metaChipClass = $compact
        ? 'inline-flex items-center gap-2 rounded-full bg-base-200/70 px-2.5 py-1 ring-1 ring-base-300/60'
        : 'inline-flex items-center gap-2.5 rounded-full bg-base-200/70 px-3 py-1.5 ring-1 ring-base-300/60';
    $metaLabelClass = $compact
        ? 'text-[0.62rem] font-semibold uppercase tracking-[0.16em] text-base-content/45'
        : 'text-[0.65rem] font-semibold uppercase tracking-[0.18em] text-base-content/45';
    $metaValueClass = 'whitespace-nowrap font-medium text-base-content/75';
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

        <div class="{{ $hasCompactImage ? 'flex flex-col gap-4 sm:flex-row sm:items-start' : $bodySpacingClass }}">
            @if($hasCompactImage)
                <a
                    class="block w-full overflow-hidden rounded-[1.25rem] bg-base-200/70 ring-1 ring-base-300/60 sm:h-28 sm:w-32 sm:shrink-0 lg:h-32 lg:w-36"
                    href="{{ route('articles.show', ['slug' => $article->slug]) }}"
                >
                    <img
                        src="{{ $article->image_url }}"
                        alt="{{ $article->title }}"
                        class="aspect-[4/3] h-full w-full object-cover object-center"
                        loading="lazy"
                    >
                </a>
            @endif

            <div class="{{ $hasCompactImage ? 'min-w-0 flex-1 space-y-2.5' : $bodySpacingClass }}">
                <a class="block {{ $titleClass }}" href="{{ route('articles.show', ['slug' => $article->slug]) }}">
                    {{ $article->title }}
                </a>

                @if($showExcerpt && filled($article->short_description))
                    <p class="{{ $excerptClass }}">
                        {{ $article->short_description }}
                    </p>
                @endif
            </div>
        </div>

        <div class="{{ $footerClass }}">
            <div class="{{ $metaClass }}">
                <span class="{{ $metaChipClass }}">
                    <span class="{{ $metaLabelClass }}">Дата</span>
                    <span class="{{ $metaValueClass }}">{{ $article->published_at?->translatedFormat('d M Y, H:i') }}</span>
                </span>

                <span class="{{ $metaChipClass }}">
                    <span class="{{ $metaLabelClass }}">Чтение</span>
                    <span class="{{ $metaValueClass }}">{{ $article->reading_time_text }}</span>
                </span>

                <span class="{{ $metaChipClass }}">
                    <span class="{{ $metaLabelClass }}">Просмотры</span>
                    <span class="{{ $metaValueClass }}">{{ number_format((int) $article->views_count, 0, ',', ' ') }}</span>
                </span>
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
