<?php

it('includes the expected automation targets in the makefile', function () {
    $makefile = file_get_contents(base_path('Makefile'));

    expect($makefile)->not->toBeFalse()
        ->and($makefile)->toContain('.DEFAULT_GOAL := help')
        ->and($makefile)->toContain('help: ## show all available targets with descriptions')
        ->and($makefile)->toContain('dev: ## php artisan serve & npm run dev')
        ->and($makefile)->toContain('parse-dry: ## php artisan rss:parse --dry-run')
        ->and($makefile)->toContain('rss-logs: ## tail -f storage/logs/rss/rss-$(shell date +%Y-%m-%d).log')
        ->and($makefile)->toContain('optimize: ## php artisan optimize && php artisan config:cache && php artisan route:cache');
});

it('documents the project overview, commands, api, structure and configuration', function () {
    $readme = file_get_contents(base_path('README.md'));

    expect($readme)->not->toBeFalse()
        ->and($readme)->toContain('# Mail.ru RSS Parser')
        ->and($readme)->toContain('## Stack')
        ->and($readme)->toContain('## API')
        ->and($readme)->toContain('## RSS Operations')
        ->and($readme)->toContain('## Project Structure')
        ->and($readme)->toContain('## Frontend Notes')
        ->and($readme)->toContain('php artisan rss:parse --all --no-interaction')
        ->and($readme)->toContain('https://mailrurssparser.test/api/v1/articles?per_page=5')
        ->and($readme)->toContain('config/rss.php');
});
