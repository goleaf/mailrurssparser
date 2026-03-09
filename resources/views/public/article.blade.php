@extends('layouts.app')

@section('body')
    <div class="mx-auto max-w-screen-2xl px-4 py-6 sm:px-6 lg:px-10">
        <div class="grid gap-8 xl:grid-cols-[minmax(0,1fr)_340px]">
            <article class="space-y-8">
                <div class="portal-hero">
                    <x-mary-breadcrumbs
                        :items="array_values(array_filter([
                            ['label' => 'Главная', 'link' => route('home')],
                            $article->category ? ['label' => $article->category->name, 'link' => route('category.show', ['slug' => $article->category->slug])] : null,
                            ['label' => \Illuminate\Support\Str::limit($article->title, 48)],
                        ]))"
                        :no-wire-navigate="true"
                    />

                    <div class="mt-5 flex flex-wrap gap-2">
                        @if($article->is_breaking)
                            <x-mary-badge class="badge-error badge-soft border-0">Срочно</x-mary-badge>
                        @endif

                        @if($article->category)
                            <a href="{{ route('category.show', ['slug' => $article->category->slug]) }}">
                                <x-mary-badge class="badge-outline border" style="border-color: {{ $article->category->color ?: '#0284c7' }}; color: {{ $article->category->color ?: '#0284c7' }};">
                                    {{ $article->category->name }}
                                </x-mary-badge>
                            </a>
                        @endif

                        @foreach($article->tags as $tag)
                            <a href="{{ route('tag.show', ['slug' => $tag->slug]) }}">
                                <x-mary-badge class="badge-ghost border-0">#{{ $tag->name }}</x-mary-badge>
                            </a>
                        @endforeach
                    </div>

                    <div class="mt-5 space-y-4">
                        <h1 class="max-w-5xl text-balance text-4xl font-black leading-tight sm:text-5xl">
                            {{ $article->title }}
                        </h1>

                        @if(filled($article->short_description))
                            <p class="max-w-4xl text-lg leading-8 text-base-content/75">
                                {{ $article->short_description }}
                            </p>
                        @endif
                    </div>

                    <div class="mt-6 flex flex-wrap items-center gap-4 text-sm text-base-content/60">
                        <span>{{ $article->published_at?->translatedFormat('d M Y, H:i') }}</span>
                        <span>{{ $article->reading_time_text }}</span>
                        <span>{{ number_format((int) $article->views_count, 0, ',', ' ') }} просмотров</span>
                        @if(filled($article->source_name))
                            <span>Источник: {{ $article->source_name }}</span>
                        @endif
                    </div>

                    <div class="mt-6 flex flex-wrap gap-3">
                        <x-public.bookmark-button :article="$article" :bookmarked-ids="$bookmarkedArticleIds" class="btn-primary" />

                        @if(filled($article->source_url))
                            <x-mary-button
                                class="btn-ghost"
                                icon-right="o-arrow-up-right"
                                label="Оригинал источника"
                                link="{{ $article->source_url }}"
                                external
                                no-wire-navigate
                            />
                        @endif
                    </div>
                </div>

                @if(filled($article->image_url))
                    <div class="overflow-hidden rounded-[2rem] border border-base-300/70">
                        <img alt="{{ $article->title }}" class="h-auto max-h-[32rem] w-full object-cover" src="{{ $article->image_url }}">
                    </div>
                @endif

                <div class="portal-surface rounded-[2rem] px-6 py-8 sm:px-8">
                    <div class="portal-prose">
                        {!! $article->content !!}
                    </div>
                </div>

                @if($moreFromCategory->isNotEmpty())
                    <section class="space-y-4">
                        <x-mary-header subtitle="Свежие материалы из той же рубрики." title="Ещё по теме рубрики" />
                        <div class="grid gap-5 md:grid-cols-2">
                            @foreach($moreFromCategory as $item)
                                <x-public.article-card :article="$item" :bookmarked-ids="$bookmarkedArticleIds" />
                            @endforeach
                        </div>
                    </section>
                @endif
            </article>

            <aside class="space-y-6">
                @if($relatedArticles->isNotEmpty())
                    <x-mary-card class="portal-surface rounded-[1.75rem]" shadow>
                        <x-slot:title>Связанные материалы</x-slot:title>
                        <x-slot:subtitle>Публикации с похожим контекстом и тегами</x-slot:subtitle>

                        <div class="space-y-4">
                            @foreach($relatedArticles as $relatedArticle)
                                <a class="block rounded-2xl border border-base-300/70 p-4 hover:border-sky-300 hover:bg-sky-50/60 dark:hover:bg-sky-950/20" href="{{ route('articles.show', ['slug' => $relatedArticle->slug]) }}">
                                    <div class="text-sm text-base-content/60">{{ $relatedArticle->published_at?->translatedFormat('d M Y, H:i') }}</div>
                                    <div class="mt-2 text-base font-bold leading-7">{{ $relatedArticle->title }}</div>
                                </a>
                            @endforeach
                        </div>
                    </x-mary-card>
                @endif

                @if($similarArticles->isNotEmpty())
                    <x-mary-card class="portal-surface rounded-[1.75rem]" shadow>
                        <x-slot:title>Похожие статьи</x-slot:title>
                        <x-slot:subtitle>Материалы из смежной повестки</x-slot:subtitle>

                        <div class="space-y-4">
                            @foreach($similarArticles as $similarArticle)
                                <a class="block rounded-2xl border border-base-300/70 p-4 hover:border-sky-300 hover:bg-sky-50/60 dark:hover:bg-sky-950/20" href="{{ route('articles.show', ['slug' => $similarArticle->slug]) }}">
                                    <div class="text-sm text-base-content/60">{{ $similarArticle->published_at?->translatedFormat('d M Y, H:i') }}</div>
                                    <div class="mt-2 text-base font-bold leading-7">{{ $similarArticle->title }}</div>
                                </a>
                            @endforeach
                        </div>
                    </x-mary-card>
                @endif

                <x-mary-card class="portal-surface rounded-[1.75rem]" shadow>
                    <x-slot:title>Популярно сейчас</x-slot:title>
                    <x-slot:subtitle>Материалы с максимальной вовлечённостью</x-slot:subtitle>

                    <div class="space-y-4">
                        @foreach($popularArticles as $popularArticle)
                            <a class="block rounded-2xl border border-base-300/70 p-4 hover:border-sky-300 hover:bg-sky-50/60 dark:hover:bg-sky-950/20" href="{{ route('articles.show', ['slug' => $popularArticle->slug]) }}">
                                <div class="text-sm text-base-content/60">{{ number_format((int) $popularArticle->views_count, 0, ',', ' ') }} просмотров</div>
                                <div class="mt-2 text-base font-bold leading-7">{{ $popularArticle->title }}</div>
                            </a>
                        @endforeach
                    </div>
                </x-mary-card>
            </aside>
        </div>
    </div>
@endsection
