<?php

it('renders a dedicated privacy policy page for the public portal', function () {
    $response = $this->get(route('privacy'));

    $response->assertOk()
        ->assertViewIs('public.info')
        ->assertSeeText('Политика приватности')
        ->assertSeeText('Чтение материалов и аналитика')
        ->assertSeeText('Как работают закладки')
        ->assertSeeText('Поиск и настройки темы');
});
