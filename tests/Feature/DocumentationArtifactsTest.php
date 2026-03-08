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
        ->and($readme)->toContain('# 📰 Новостной Портал')
        ->and($readme)->toContain('## ✅ Features')
        ->and($readme)->toContain('## 🚀 Commands Reference')
        ->and($readme)->toContain('## 🔗 API Endpoints')
        ->and($readme)->toContain('## 📁 Project Structure')
        ->and($readme)->toContain('## ⚙️ Configuration')
        ->and($readme)->toContain('php artisan rss:parse --stat')
        ->and($readme)->toContain('http://localhost:8000/api/v1/')
        ->and($readme)->toContain('config/rss.php');
});
