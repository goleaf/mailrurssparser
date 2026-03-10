# Mail.ru RSS Parser

![Laravel 12](https://img.shields.io/badge/Laravel-12-FF2D20?logo=laravel&logoColor=white)
![PHP 8.5](https://img.shields.io/badge/PHP-8.5-777BB4?logo=php&logoColor=white)
![Filament 5](https://img.shields.io/badge/Filament-5-F59E0B)
![Livewire 4](https://img.shields.io/badge/Livewire-4-FB70A9)
![Mary UI](https://img.shields.io/badge/Mary%20UI-Blade-0F172A)
![Tailwind 4](https://img.shields.io/badge/Tailwind-4-06B6D4?logo=tailwindcss&logoColor=white)
![SQLite](https://img.shields.io/badge/SQLite-Database-003B57?logo=sqlite&logoColor=white)

`mailrurssparser` is a Laravel 12 news portal that imports Mail.ru RSS feeds, stores articles locally, exposes a versioned public API, and provides a Filament admin panel for editorial work and parser operations.

The public site is rendered with Blade, Tailwind CSS 4, and Mary UI. The admin panel runs on Filament 5 with Livewire 4.

## Stack

- PHP 8.5
- Laravel 12
- Filament 5
- Livewire 4
- Blade + Mary UI
- Tailwind CSS 4
- SQLite
- Laravel Scout + TNTSearch

## What It Does

- Imports and normalizes configured Mail.ru RSS feeds
- Stores articles, categories, sub-categories, tags, metrics, bookmarks, and parse logs
- Serves a public news site with category, tag, article, search, bookmark, and stats pages
- Exposes `/api/v1/...` endpoints for articles, categories, tags, search, stats, bookmarks, newsletter, and RSS controls
- Provides a Filament admin panel for content operations, feed management, and parse history
- Supports scheduled parsing, cleanup, reindexing, and article enrichment commands

## Local Development

This project is configured for Laravel Herd. The default local site is:

```text
https://mailrurssparser.test
```

### First-time setup

```bash
composer install
cp .env.example .env
touch database/database.sqlite
php artisan key:generate
php artisan migrate --no-interaction
npm install
npm run build
```

If you want real content after setup, run:

```bash
php artisan rss:parse --all --no-interaction
```

### Daily workflow

```bash
make dev
```

Useful alternatives:

```bash
npm run dev
php artisan serve
php artisan pail
```

## Public Routes

Server-rendered public pages live in `routes/web.php` and `resources/views/public`.

- `/`
- `/category/{slug}`
- `/tag/{slug}`
- `/articles/{slug}`
- `/search`
- `/bookmarks`
- `/stats`
- `/about`
- `/contact`
- `/privacy`

SEO and utility routes:

- `/sitemap.xml`
- `/rss.xml`
- `/robots.txt`
- `/offline.html`

## Admin Panel

The Filament admin panel is mounted at:

```text
/admin
```

Current admin characteristics:

- light-only theme
- full-width layouts
- resource tables with search and sort support
- custom RSS operations pages for feed management and parse history

## API

Base path:

```text
/api/v1
```

Main endpoint groups:

- `articles`
- `categories`
- `tags`
- `search`
- `stats`
- `bookmarks`
- `share`
- `newsletter`
- `rss`

Examples:

```bash
curl -s "https://mailrurssparser.test/api/v1/articles?per_page=5"
curl -s "https://mailrurssparser.test/api/v1/categories"
curl -s "https://mailrurssparser.test/api/v1/tags/trending"
curl -s "https://mailrurssparser.test/api/v1/search?q=экономика"
curl -s "https://mailrurssparser.test/api/v1/stats/overview"
```

## RSS Operations

Available parser and maintenance commands:

```bash
php artisan rss:parse
php artisan rss:status
php artisan rss:clean
php artisan rss:reindex
php artisan rss:enrich-articles
```

Common parser examples:

```bash
php artisan rss:parse --all --stat --no-interaction
php artisan rss:parse --category=economics --no-interaction
php artisan rss:parse --feed=3 --no-interaction
php artisan rss:parse --dry-run --no-interaction
php artisan rss:status --json --no-interaction
php artisan rss:clean --days=30 --force --no-interaction
```

`config/rss.php` contains the upstream feed catalog and parser/article defaults.

## Make Targets

```bash
make help
make dev
make build
make parse
make parse-dry
make parse-all
make status
make clean
make reindex
make fresh
make routes
make schedule
make optimize
```

## Frontend Notes

The public frontend no longer uses Svelte. Current frontend responsibilities are split like this:

- `resources/views/public` for public pages
- `resources/views/components/public` for reusable Blade UI
- `resources/views/layouts/app.blade.php` for the shared shell
- `resources/css/app.css` for Tailwind v4 theme tokens and shared styling
- `resources/js` for small TypeScript helpers and generated route helpers

## Project Structure

```text
app/
  Console/Commands/          RSS parsing and maintenance commands
  Filament/                  Admin resources, pages, widgets, support classes
  Http/Controllers/Api/      Public API controllers
  Http/Controllers/          Public site and admin operation controllers
  Http/Resources/            API resources
  Models/                    Eloquent models
  Services/                  Parser, metrics, and support services
config/
  rss.php                    Feed catalog and parser settings
resources/
  css/                       Shared Tailwind styles
  js/                        TypeScript helpers and generated routes
  views/                     Blade layouts, public pages, admin pages, emails
routes/
  api.php                    Versioned API routes
  web.php                    Public, SEO, and admin operation routes
tests/
  Feature/                   Pest feature coverage
```

## Quality Checks

```bash
vendor/bin/pint --dirty --format agent
php artisan test --compact
npm run lint:check
npm run types:check
npm test
npm run build
```

## Notes

- The default database driver is SQLite.
- Locale defaults are Russian.
- Public bookmarks work without a user account.
- Filament authentication is used for admin access.
