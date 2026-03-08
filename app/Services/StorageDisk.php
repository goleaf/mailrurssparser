<?php

namespace App\Services;

enum StorageDisk: string
{
    case Local = 'local';
    case Public = 'public';
}
