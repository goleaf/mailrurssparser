<?php

return [
    /**
     * Entropy length to generate unique results.
     *
     * @var int
     */
    'entropy' => 4,

    /**
     * Alphabet base seed to create a unique dictionary.
     *
     * @var int|string|null
     */
    'seed' => null,

    /**
     * URL-safe alphabet override (avoid "_" which is used as a DataToken delimiter).
     *
     * @var string
     */
    'alphabet' => '0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ-~',

    /**
     * Encryption secret key.
     *
     * @var string|null
     */
    'key' => env('APP_KEY'),
];
