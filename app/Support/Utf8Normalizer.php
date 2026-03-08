<?php

namespace App\Support;

final class Utf8Normalizer
{
    public static function normalize(mixed $value): mixed
    {
        if (is_array($value)) {
            $normalized = [];

            foreach ($value as $key => $item) {
                $normalized[$key] = self::normalize($item);
            }

            return $normalized;
        }

        if (is_string($value)) {
            return self::normalizeString($value);
        }

        return $value;
    }

    public static function normalizeString(?string $value): ?string
    {
        if ($value === null || $value === '') {
            return $value;
        }

        if (mb_check_encoding($value, 'UTF-8')) {
            return $value;
        }

        if (function_exists('iconv')) {
            $normalized = @iconv('UTF-8', 'UTF-8//IGNORE', $value);

            if ($normalized !== false) {
                return $normalized;
            }
        }

        return mb_convert_encoding($value, 'UTF-8', 'UTF-8');
    }
}
