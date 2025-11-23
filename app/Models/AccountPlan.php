<?php

namespace App\Models;

use MongoDB\Laravel\Eloquent\Model;

class AccountPlan extends Model
{
    protected $connection = 'mongodb';
    protected $collection = 'account_plans';

    protected $fillable = [
        'session_id',
        'company_name',
        'overview',
        'products',
        'competitors',
        'opportunities',
        'recommendations',
        'market_position',
        'financial_summary',
        'key_contacts',
        'updated_at',
    ];

    protected $casts = [
        'products' => 'array',
        'competitors' => 'array',
        'opportunities' => 'array',
        'recommendations' => 'array',
        'key_contacts' => 'array',
        'updated_at' => 'datetime',
        'created_at' => 'datetime',
    ];
}

