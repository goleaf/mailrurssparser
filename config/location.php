<?php

use Skywalker\Location\Drivers\HttpHeader;
use Skywalker\Location\Drivers\IpInfo;
use Skywalker\Location\Drivers\MaxMind;
use Skywalker\Location\Position;

return [
    'driver' => HttpHeader::class,
    'fallbacks' => array_values(array_filter([
        env('LOCATION_ENABLE_IPINFO_FALLBACK', false) && filled(env('IPINFO_TOKEN')) ? IpInfo::class : null,
        env('LOCATION_ENABLE_MAXMIND_FALLBACK', file_exists(env('LOCATION_MAXMIND_PATH', database_path('maxmind/GeoLite2-City.mmdb'))))
            ? MaxMind::class
            : null,
    ])),
    'position' => Position::class,
    'cache' => [
        'enabled' => env('LOCATION_CACHE', true),
        'duration' => 86400,
    ],
    'maxmind' => [
        'web' => [
            'enabled' => false,
            'user_id' => '',
            'license_key' => '',
            'options' => [
                'host' => 'geoip.maxmind.com',
            ],
        ],
        'local' => [
            'path' => env('LOCATION_MAXMIND_PATH', database_path('maxmind/GeoLite2-City.mmdb')),
        ],
    ],
    'ipinfo' => [
        'token' => env('IPINFO_TOKEN'),
    ],
    'testing' => [
        'enabled' => false,
        'ip' => env('LOCATION_TESTING_IP', '66.102.0.0'),
    ],
    'bots' => [
        'enabled' => false,
        'list' => [],
        'trusted_domains' => [],
    ],
    'dashboard' => [
        'enabled' => false,
    ],
];
