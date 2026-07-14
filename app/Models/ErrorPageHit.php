<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ErrorPageHit extends Model
{
    protected $fillable = [
        'status',
        'day',
        'hits',
    ];

    protected function casts(): array
    {
        return [
            'status' => 'integer',
            'day' => 'date',
            'hits' => 'integer',
        ];
    }
}
