<?php

namespace App\Enums;

enum UserRole: string
{
    case Visitor = 'visitor';
    case Contributor = 'contributor';
    case Validator = 'validator';
    case Admin = 'admin';
}
