<?php

it('schedules hourly article link enrichment', function () {
    $this->artisan('schedule:list')
        ->expectsOutputToContain('10   * * * *  php artisan rss:enrich-articles --limit=100')
        ->assertExitCode(0);
});
