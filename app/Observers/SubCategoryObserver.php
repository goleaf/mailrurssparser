<?php

namespace App\Observers;

use App\Models\SubCategory;
use App\Services\ArticleCacheKey;
use Illuminate\Contracts\Cache\Repository;
use Illuminate\Contracts\Events\ShouldHandleEventsAfterCommit;
use Illuminate\Support\Facades\Cache;

class SubCategoryObserver implements ShouldHandleEventsAfterCommit
{
    public function created(SubCategory $subCategory): void
    {
        $this->forgetCategoryCache();
    }

    public function updated(SubCategory $subCategory): void
    {
        $this->forgetCategoryCache();
    }

    public function deleted(SubCategory $subCategory): void
    {
        $this->forgetCategoryCache();
    }

    public function restored(SubCategory $subCategory): void
    {
        $this->forgetCategoryCache();
    }

    public function forceDeleted(SubCategory $subCategory): void
    {
        $this->forgetCategoryCache();
    }

    private function forgetCategoryCache(): void
    {
        $cache = Cache::memo();

        $this->forgetFlexibleCache($cache, ArticleCacheKey::Categories);
    }

    private function forgetFlexibleCache(Repository $cache, ArticleCacheKey|string $key): void
    {
        $cache->forget($key);
        $cache->forget(ArticleCacheKey::flexibleCreated($key));
    }
}
