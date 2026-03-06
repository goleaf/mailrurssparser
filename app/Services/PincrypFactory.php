<?php

namespace App\Services;

use Attla\Pincryp\Factory as BaseFactory;

class PincrypFactory extends BaseFactory
{
    public function md5(string $value, string $secret = ''): string
    {
        return md5($value.$secret);
    }

    public function generateKey(int $length = 32): string
    {
        if ($length <= 0) {
            return '';
        }

        $bytes = (int) ceil($length / 2);

        return substr(bin2hex(random_bytes($bytes)), 0, $length);
    }
}
