<?php

namespace App\Models;

use MongoDB\Laravel\Eloquent\Model;

class SearchCache extends Model
{
    protected $connection = 'mongodb';
    protected $collection = 'search_cache';

    protected $fillable = [
        'query',
        'results',
        'expires_at',
    ];

    protected $casts = [
        'results' => 'array',
        'expires_at' => 'datetime',
        'created_at' => 'datetime',
    ];
}

