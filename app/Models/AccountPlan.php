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
        
        // Company Basics
        'description',
        'industry',
        'business_model',
        'value_proposition',
        'headquarters',
        'global_presence',
        'founding_year',
        'founders',
        'employee_count',
        'employee_growth_trend',
        
        // Financial Parameters
        'revenue',
        'revenue_growth',
        'funding_rounds',
        'latest_valuation',
        'investors',
        'profitability',
        'ipo_year',
        'stock_ticker',
        
        // Product & Technology
        'product_lines',
        'technology_stack',
        'target_customers',
        'use_cases',
        
        // Leadership & People
        'key_executives',
        'hiring_trends',
        'culture_ratings',
        
        // Market & Competitors
        'industry_overview',
        'competitors',
        'unique_differentiators',
        
        // Customer & GTM
        'customer_segments',
        'partnerships',
        'pricing_model',
        
        // Recent Events
        'latest_news',
        'analyst_reports',
        
        // Pain Points
        'business_pain_points',
        'technical_pain_points',
        
        // Opportunities & Risks
        'sales_opportunities',
        'strategic_opportunities',
        'external_risks',
        'internal_risks',
        
        // Final Account Plan Sections
        'overview',
        'business_landscape',
        'products',
        'leadership_contacts',
        'competitor_analysis',
        'opportunities',
        'recommendations',
        'risks',
        'next_steps',
        
        // Legacy fields for compatibility
        'market_position',
        'financial_summary',
        'key_contacts',
        
        // Research metadata
        'research_status',
        'research_sources',
        'last_updated_sections',
        
        'updated_at',
    ];

    protected $casts = [
        // Array fields
        'global_presence' => 'array',
        'founders' => 'array',
        'funding_rounds' => 'array',
        'investors' => 'array',
        'product_lines' => 'array',
        'technology_stack' => 'array',
        'target_customers' => 'array',
        'use_cases' => 'array',
        'key_executives' => 'array',
        'hiring_trends' => 'array',
        'culture_ratings' => 'array',
        'competitors' => 'array',
        'unique_differentiators' => 'array',
        'customer_segments' => 'array',
        'partnerships' => 'array',
        'latest_news' => 'array',
        'analyst_reports' => 'array',
        'business_pain_points' => 'array',
        'technical_pain_points' => 'array',
        'sales_opportunities' => 'array',
        'strategic_opportunities' => 'array',
        'external_risks' => 'array',
        'internal_risks' => 'array',
        'products' => 'array',
        'opportunities' => 'array',
        'recommendations' => 'array',
        'risks' => 'array',
        'key_contacts' => 'array',
        'leadership_contacts' => 'array',
        'research_status' => 'array',
        'research_sources' => 'array',
        'last_updated_sections' => 'array',
        
        // Date fields
        'updated_at' => 'datetime',
        'created_at' => 'datetime',
    ];
}

