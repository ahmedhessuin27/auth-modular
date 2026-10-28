<?php

namespace App\Modules\Auth\Domain\Enums;

enum SocialProvider: string
{
    case Google = 'google';
    case Apple = 'apple';
}
