<?php

it('renders a dedicated privacy policy page for the public portal', function () {
    $component = file_get_contents(resource_path('js/pages/PublicInfoPage.svelte'));

    expect($component)->not->toBeFalse()
        ->and($component)->toContain(
            'Как публичная часть портала обращается с данными.',
            'Что сайт сохраняет прямо в браузере',
            'Какие события чтения попадают в систему',
            'Как работают закладки',
            'Что происходит при подписке на рассылку',
            'Вопросы и ответы',
        );
});
