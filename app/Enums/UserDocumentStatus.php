<?php

namespace App\Enums;

enum UserDocumentStatus: string
{
    case Active = 'active';
    case Deleted = 'deleted';
    case Rejected = 'rejected';
}
