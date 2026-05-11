<?php

namespace App\Enums;

enum ContributionAction: string
{
    case Create = 'create';
    case Update = 'update';
    case Delete = 'delete';
}
