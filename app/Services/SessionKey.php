<?php

namespace App\Services;

enum SessionKey: string
{
    case Status = 'status';
    case LoginId = 'login.id';
}
