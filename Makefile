.DEFAULT_GOAL := help

.PHONY: help dev build parse parse-dry parse-all status clean reindex fresh tinker logs rss-logs routes schedule test-api stats seed optimize

help: ## show all available targets with descriptions
	@awk 'BEGIN {FS = ": ## "}; /^[a-zA-Z0-9_.-]+: ## / {printf "\033[36m%-12s\033[0m %s\n", $$1, $$2}' $(MAKEFILE_LIST)

dev: ## php artisan serve & npm run dev
	php artisan serve & npm run dev

build: ## npm run build && php artisan optimize
	npm run build && php artisan optimize

parse: ## php artisan rss:parse
	php artisan rss:parse

parse-dry: ## php artisan rss:parse --dry-run
	php artisan rss:parse --dry-run

parse-all: ## php artisan rss:parse --all
	php artisan rss:parse --all

status: ## php artisan rss:status
	php artisan rss:status

clean: ## php artisan rss:clean --days=30 --force
	php artisan rss:clean --days=30 --force

reindex: ## php artisan rss:reindex
	php artisan rss:reindex

fresh: ## php artisan migrate:fresh --seed && php artisan rss:parse
	php artisan migrate:fresh --seed && php artisan rss:parse

tinker: ## php artisan tinker
	php artisan tinker

logs: ## tail -f storage/logs/laravel.log
	tail -f storage/logs/laravel.log

rss-logs: ## tail -f storage/logs/rss/rss-$(shell date +%Y-%m-%d).log
	tail -f storage/logs/rss/rss-$(shell date +%Y-%m-%d).log

routes: ## php artisan route:list --path=api/v1
	php artisan route:list --path=api/v1

schedule: ## php artisan schedule:list
	php artisan schedule:list

test-api: ## curl -s http://localhost:8000/api/v1/articles | python3 -m json.tool | head -40
	curl -s http://localhost:8000/api/v1/articles | python3 -m json.tool | head -40

stats: ## curl -s http://localhost:8000/api/v1/stats/overview | python3 -m json.tool
	curl -s http://localhost:8000/api/v1/stats/overview | python3 -m json.tool

seed: ## php artisan db:seed
	php artisan db:seed

optimize: ## php artisan optimize && php artisan config:cache && php artisan route:cache
	php artisan optimize && php artisan config:cache && php artisan route:cache
