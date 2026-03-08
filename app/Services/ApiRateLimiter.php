<?php

namespace App\Services;

enum ApiRateLimiter: string
{
    case Api = 'api';
    case Search = 'api-search';
    case SearchSuggest = 'api-search-suggest';
    case Rss = 'api-rss';
}
