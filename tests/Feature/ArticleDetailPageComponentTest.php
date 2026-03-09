<?php

it('does not render the article metadata footer block', function () {
    $component = file_get_contents(
        resource_path('js/pages/ArticleDetailPage.svelte'),
    );

    expect($component)->not->toBeFalse()
        ->and($component)->not->toContain(
            'Метаданные статьи',
            'RSS парсинг',
            'RSS-лента',
        );
});
