# 📰 Новостной Портал

![Laravel 12](https://img.shields.io/badge/Laravel-12-FF2D20?logo=laravel&logoColor=white)
![PHP 8.5](https://img.shields.io/badge/PHP-8.5-777BB4?logo=php&logoColor=white)
![Filament 5](https://img.shields.io/badge/Filament-5-F59E0B)
![Mary UI](https://img.shields.io/badge/Mary%20UI-Livewire-0F172A)
![Livewire 4](https://img.shields.io/badge/Livewire-4-FB70A9)
![Tailwind 4](https://img.shields.io/badge/Tailwind-4-06B6D4?logo=tailwindcss&logoColor=white)
![SQLite](https://img.shields.io/badge/SQLite-Database-003B57?logo=sqlite&logoColor=white)

> Screenshot placeholder: add a homepage or admin dashboard screenshot here.

Новостной портал на Laravel 12, Filament 5, Livewire и Blade + Mary UI с RSS-агрегацией из внешних RSS-источников, публичным API, админкой для редакторов и автоматическим парсингом по расписанию.

## ✅ Features

- 8 RSS feeds auto-parsed from configured upstream sources
- Artisan commands: `rss:parse`, `rss:status`, `rss:clean`, `rss:reindex`
- Auto-parse every 15 minutes, plus `main` every 5 minutes
- Duplicate detection by GUID + URL
- Filament 5 admin: articles, categories, tags, feeds, widgets, parse manager
- Full rich editor for article content
- Multi-tag filtering, date range, content types, importance scoring
- Full-text search with Laravel Scout + TNTSearch on SQLite
- Anonymous bookmarks (session-based)
- Social sharing with click tracking for VK, Telegram, WhatsApp, Twitter and more
- Newsletter subscription with email confirmation
- Statistics dashboard: views, articles, feeds, calendar heatmap and category breakdown
- Dark mode with persisted preference
- PWA manifest + service worker + offline page
- SEO: Open Graph, Twitter Cards, JSON-LD, `sitemap.xml`, `robots.txt`
- Breaking news ticker
- Real-time polling for breaking news and view counters
- Reading progress bar on article pages
- Related and similar articles

## 🚀 Commands Reference

### RSS parser

```bash
php artisan rss:parse
php artisan rss:parse --category=politics
php artisan rss:parse --feed=3
php artisan rss:parse --url="https://example.com/rss/sport.xml"
php artisan rss:parse --due
php artisan rss:parse --all
php artisan rss:parse --dry-run
php artisan rss:parse --json
php artisan rss:parse --force
php artisan rss:parse --reparse=10
php artisan rss:parse --stat
```

Options:

- `--category=` filter by category slug
- `--feed=` parse one feed by ID
- `--url=` preview any arbitrary RSS URL without saving to DB
- `--due` parse only feeds whose `next_parse_at` is due
- `--all` parse all active feeds
- `--dry-run` fetch and compare without creating articles
- `--json` print machine-readable output
- `--force` include disabled feeds
- `--reparse=` temporarily limit item count for reprocessing
- `--stat` print additional parse statistics

### RSS status

```bash
php artisan rss:status
php artisan rss:status --category=sport
php artisan rss:status --json
php artisan rss:status --watch
```

### RSS cleanup

```bash
php artisan rss:clean
php artisan rss:clean --days=30
php artisan rss:clean --status=archived
php artisan rss:clean --dry-run
php artisan rss:clean --force
```

### Search reindex

```bash
php artisan rss:reindex
php artisan rss:reindex --chunk=500
php artisan rss:reindex --category=sport
```

### Useful shortcuts

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
```

## 🔗 API Endpoints

Base URL:

```text
http://localhost:8000/api/v1/
```

### Articles

- `GET /articles`
- `GET /articles/featured`
- `GET /articles/breaking`
- `GET /articles/trending`
- `GET /articles/{slug}`
- `GET /articles/{slug}/related`
- `GET /articles/{slug}/similar`
- `GET /category/{slug}/pinned`

Examples:

```bash
curl -s "http://localhost:8000/api/v1/articles?category=sport&per_page=3" | python3 -m json.tool
curl -s "http://localhost:8000/api/v1/articles/breaking" | python3 -m json.tool
curl -s "http://localhost:8000/api/v1/articles/some-article-slug" | python3 -m json.tool
```

### Categories and tags

- `GET /categories`
- `GET /categories/{slug}`
- `GET /categories/{slug}/articles`
- `GET /tags`
- `GET /tags/trending`
- `GET /tags/{slug}`
- `GET /tags/{slug}/articles`

Examples:

```bash
curl -s "http://localhost:8000/api/v1/categories" | python3 -m json.tool
curl -s "http://localhost:8000/api/v1/tags/trending" | python3 -m json.tool
```

### Search

- `GET /search`
- `GET /search/suggest`
- `GET /search/highlights`

Examples:

```bash
curl -s "http://localhost:8000/api/v1/search?q=спорт" | python3 -m json.tool
curl -s "http://localhost:8000/api/v1/search/suggest?q=пол" | python3 -m json.tool
```

### Stats

- `GET /stats/overview`
- `GET /stats/chart`
- `GET /stats/popular`
- `GET /stats/calendar/{year}/{month}`
- `GET /stats/feeds`
- `GET /stats/categories`

Examples:

```bash
curl -s "http://localhost:8000/api/v1/stats/overview" | python3 -m json.tool
curl -s "http://localhost:8000/api/v1/stats/chart?type=views&period=30d" | python3 -m json.tool
curl -s "http://localhost:8000/api/v1/stats/popular?period=week&limit=10" | python3 -m json.tool
```

### Bookmarks, share and newsletter

- `GET /bookmarks`
- `POST /bookmarks/check`
- `POST /bookmarks/{articleId}`
- `POST /share/{articleId}`
- `POST /newsletter/subscribe`
- `GET /newsletter/confirm/{token}`
- `GET /newsletter/unsubscribe/{token}`

### RSS control

- `GET /rss/status`
- `POST /rss/parse`
- `POST /rss/parse/{feedId}`
- `POST /rss/parse/category/{slug}`

Examples:

```bash
curl -s "http://localhost:8000/api/v1/rss/status" | python3 -m json.tool
curl -s -X POST "http://localhost:8000/api/v1/rss/parse/category/sport" | python3 -m json.tool
```

## 📁 Project Structure

```text
app/
  Console/Commands/        RSS commands and maintenance tasks
  Filament/                Admin resources, pages and widgets
  Http/Controllers/Api/    Versioned public API controllers
  Http/Resources/          API resource transformers
  Models/                  Eloquent models
  Services/                RSS parser and caching services
config/
  rss.php                  Feed catalog and parser/article settings
resources/
  js/                      Inertia + Svelte frontend
  views/                   Blade root views and email templates
routes/
  api.php                  Public API routes
  web.php                  Web, SEO and SPA routes
tests/
  Feature/                 Pest feature coverage
```

## ⚙️ Configuration

The main project-specific configuration lives in [config/rss.php](config/rss.php).

### `feeds`

Defines the Mail.ru RSS catalog that should exist in the database:

- `url` full feed URL
- `title` human-readable feed name
- `category_slug` and `category_name` taxonomy mapping
- `category_color` and `category_icon` UI metadata

### `parser`

Controls network and parsing behavior:

- `timeout`
- `connect_timeout`
- `user_agent`
- `max_items_per_feed`
- `duplicate_window_hours`

### `image`

Controls image extraction sources:

- `extract_from_enclosure`
- `extract_from_media_namespace`
- `extract_from_description_img`
- `fallback_placeholder`

### `article`

Controls article generation defaults:

- `auto_short_description`
- `short_description_length`
- `auto_reading_time`
- `words_per_minute`
- `default_status`
- `default_author`

## Local start

```bash
composer install
npm install
cp .env.example .env
php artisan key:generate
php artisan migrate
php artisan db:seed
php artisan rss:parse
npm run build
```

## Public endpoints

- Site: `http://localhost:8000`
- API: `http://localhost:8000/api/v1`
- Sitemap: `http://localhost:8000/sitemap.xml`
- RSS: `http://localhost:8000/rss.xml`
- Robots: `http://localhost:8000/robots.txt`
