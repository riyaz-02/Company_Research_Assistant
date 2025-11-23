<?php

namespace App\Models;

use MongoDB\Laravel\Eloquent\Model;

class Conversation extends Model
{
    protected $connection = 'mongodb';
    protected $collection = 'conversations';

    protected $fillable = [
        'session_id',
        'messages',
        'metadata',
    ];

    protected $casts = [
        'messages' => 'array',
        'metadata' => 'array',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];
}

