@props([
    'article',
    'bookmarkedIds' => [],
    'class' => '',
])

@php
    $isBookmarked = in_array($article->id, $bookmarkedIds, true);
@endphp

<form action="{{ route('bookmarks.toggle', ['article' => $article->id]) }}" class="inline" method="POST">
    @csrf

    <x-mary-button
        :class="trim(($isBookmarked ? 'btn-primary' : 'btn-ghost').' btn-sm '.$class)"
        :icon="$isBookmarked ? 's-bookmark' : 'o-bookmark'"
        :label="$isBookmarked ? 'Сохранено' : 'В закладки'"
        no-wire-navigate
    />
</form>
