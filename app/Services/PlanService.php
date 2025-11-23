<?php

namespace App\Services;

use App\Models\AccountPlan;
use Illuminate\Support\Str;

class PlanService
{
    /**
     * Get or create account plan for a session
     */
    public function getOrCreatePlan(string $sessionId, ?string $companyName = null): AccountPlan
    {
        return AccountPlan::firstOrCreate(
            ['session_id' => $sessionId],
            [
                'company_name' => $companyName ?? '',
                
                // Initialize all sections as empty
                'description' => '',
                'industry' => '',
                'business_model' => '',
                'value_proposition' => '',
                'headquarters' => '',
                'global_presence' => [],
                'founding_year' => '',
                'founders' => [],
                'employee_count' => '',
                'employee_growth_trend' => '',
                
                'revenue' => '',
                'revenue_growth' => '',
                'funding_rounds' => [],
                'latest_valuation' => '',
                'investors' => [],
                'profitability' => '',
                'ipo_year' => '',
                'stock_ticker' => '',
                
                'product_lines' => [],
                'technology_stack' => [],
                'target_customers' => [],
                'use_cases' => [],
                
                'key_executives' => [],
                'hiring_trends' => [],
                'culture_ratings' => [],
                
                'industry_overview' => '',
                'competitors' => [],
                'unique_differentiators' => [],
                
                'customer_segments' => [],
                'partnerships' => [],
                'pricing_model' => '',
                
                'latest_news' => [],
                'analyst_reports' => [],
                
                'business_pain_points' => [],
                'technical_pain_points' => [],
                
                'sales_opportunities' => [],
                'strategic_opportunities' => [],
                'external_risks' => [],
                'internal_risks' => [],
                
                // Final sections
                'overview' => '',
                'business_landscape' => '',
                'products' => [],
                'leadership_contacts' => [],
                'competitor_analysis' => '',
                'opportunities' => [],
                'recommendations' => [],
                'risks' => [],
                'next_steps' => '',
                
                // Legacy compatibility
                'market_position' => '',
                'financial_summary' => '',
                'key_contacts' => [],
                
                // Metadata
                'research_status' => [],
                'research_sources' => [],
                'last_updated_sections' => [],
            ]
        );
    }

    /**
     * Update a specific section of the plan with evidence
     */
    public function updateSection(string $sessionId, string $section, mixed $content, array $evidence = []): AccountPlan
    {
        $plan = $this->getOrCreatePlan($sessionId);

        \Log::info('PlanService: Updating section', [
            'session' => $sessionId,
            'section' => $section,
            'content_preview' => is_string($content) ? substr($content, 0, 100) : 'array'
        ]);

        // Check if this is a custom section (not in model attributes)
        $modelAttributes = $plan->getAttributes();
        $fillableFields = $plan->getFillable();
        
        // Step workflow sections are always strings (NOT arrays)
        $stepWorkflowSections = [
            'company_overview', 'financial_overview', 'products_services', 
            'competitive_landscape', 'pain_points', 'recommendations', 'executive_summary'
        ];
        
        // Legacy array sections (but not if they're step workflow sections)
        $legacyArraySections = ['products', 'competitors', 'opportunities', 'key_contacts', 'business_pain_points', 'technical_pain_points'];
        
        // Handle array sections (but not step workflow sections)
        if (!in_array($section, $stepWorkflowSections) && in_array($section, $legacyArraySections)) {
            if (is_array($content)) {
                $plan->$section = $content;
            } else {
                // If string provided, convert to array with the content
                $plan->$section = [['content' => $content]];
            }
        } else {
            // Handle string sections (including all step workflow sections)
            $plan->$section = is_string($content) ? $content : json_encode($content);
        }

        // Store evidence if provided
        if (!empty($evidence)) {
            $sources = $plan->research_sources ?? [];
            $sources[$section] = $evidence;
            $plan->research_sources = $sources;
        }
        
        // Track last updated sections
        $lastUpdated = $plan->last_updated_sections ?? [];
        $lastUpdated[$section] = now()->toIso8601String();
        $plan->last_updated_sections = $lastUpdated;

        $plan->save();
        
        \Log::info('PlanService: Section saved', ['section' => $section]);

        return $plan;
    }

    /**
     * Update company name
     */
    public function updateCompanyName(string $sessionId, string $companyName): AccountPlan
    {
        $plan = $this->getOrCreatePlan($sessionId);
        $plan->company_name = $companyName;
        $plan->save();

        return $plan;
    }

    /**
     * Clear/reset plan for a session (useful when starting fresh research)
     */
    public function clearPlan(string $sessionId): void
    {
        $plan = AccountPlan::where('session_id', $sessionId)->first();
        if ($plan) {
            $plan->delete();
            \Log::info('Cleared existing plan for session', ['session' => $sessionId]);
        }
    }

    /**
     * Get plan by session ID
     */
    public function getPlan(string $sessionId): ?AccountPlan
    {
        return AccountPlan::where('session_id', $sessionId)->first();
    }

    /**
     * Get step workflow sections (for 7-step conversational flow)
     */
    public function getStepSections(string $sessionId): array
    {
        $plan = $this->getPlan($sessionId);

        if (!$plan) {
            \Log::info('PlanService: No plan found for session', ['session' => $sessionId]);
            return [];
        }

        $sections = [
            'company_name' => $plan->company_name,
            'company_overview' => $plan->company_overview ?? null,
            'financial_overview' => $plan->financial_overview ?? null,
            'products_services' => $plan->products_services ?? null,
            'competitive_landscape' => $plan->competitive_landscape ?? null,
            'pain_points' => $plan->pain_points ?? null,
            'recommendations' => $plan->recommendations ?? null,
            'executive_summary' => $plan->executive_summary ?? null,
        ];

        \Log::info('PlanService: getStepSections returning', [
            'session' => $sessionId,
            'company_name' => $sections['company_name'],
            'sections_with_content' => array_keys(array_filter($sections, function($v) { 
                return !is_null($v) && $v !== ''; 
            }))
        ]);

        return $sections;
    }

    /**
     * Get all sections of a plan
     */
    public function getPlanSections(string $sessionId): array
    {
        $plan = $this->getPlan($sessionId);

        if (!$plan) {
            return [];
        }

        return [
            'company_name' => $plan->company_name,
            
            // Company Basics
            'company_basics' => [
                'description' => $plan->description ?? '',
                'industry' => $plan->industry ?? '',
                'business_model' => $plan->business_model ?? '',
                'value_proposition' => $plan->value_proposition ?? '',
                'headquarters' => $plan->headquarters ?? '',
                'global_presence' => $plan->global_presence ?? [],
                'founding_year' => $plan->founding_year ?? '',
                'founders' => $plan->founders ?? [],
                'employee_count' => $plan->employee_count ?? '',
                'employee_growth_trend' => $plan->employee_growth_trend ?? '',
            ],
            
            // Financial Information
            'financial_info' => [
                'revenue' => $plan->revenue ?? '',
                'revenue_growth' => $plan->revenue_growth ?? '',
                'funding_rounds' => $plan->funding_rounds ?? [],
                'latest_valuation' => $plan->latest_valuation ?? '',
                'investors' => $plan->investors ?? [],
                'profitability' => $plan->profitability ?? '',
                'ipo_year' => $plan->ipo_year ?? '',
                'stock_ticker' => $plan->stock_ticker ?? '',
            ],
            
            // Product & Technology
            'product_technology' => [
                'product_lines' => $plan->product_lines ?? [],
                'technology_stack' => $plan->technology_stack ?? [],
                'target_customers' => $plan->target_customers ?? [],
                'use_cases' => $plan->use_cases ?? [],
            ],
            
            // Leadership & People
            'leadership_people' => [
                'key_executives' => $plan->key_executives ?? [],
                'hiring_trends' => $plan->hiring_trends ?? [],
                'culture_ratings' => $plan->culture_ratings ?? [],
            ],
            
            // Market Analysis
            'market_analysis' => [
                'industry_overview' => $plan->industry_overview ?? '',
                'competitors' => $plan->competitors ?? [],
                'unique_differentiators' => $plan->unique_differentiators ?? [],
            ],
            
            // Go-to-Market
            'gtm_strategy' => [
                'customer_segments' => $plan->customer_segments ?? [],
                'partnerships' => $plan->partnerships ?? [],
                'pricing_model' => $plan->pricing_model ?? '',
            ],
            
            // Recent Intelligence
            'recent_intelligence' => [
                'latest_news' => $plan->latest_news ?? [],
                'analyst_reports' => $plan->analyst_reports ?? [],
            ],
            
            // Pain Points Analysis
            'pain_points' => [
                'business_pain_points' => $plan->business_pain_points ?? [],
                'technical_pain_points' => $plan->technical_pain_points ?? [],
            ],
            
            // Strategic Assessment
            'strategic_assessment' => [
                'sales_opportunities' => $plan->sales_opportunities ?? [],
                'strategic_opportunities' => $plan->strategic_opportunities ?? [],
                'external_risks' => $plan->external_risks ?? [],
                'internal_risks' => $plan->internal_risks ?? [],
            ],
            
            // Final Account Plan Document
            'account_plan' => [
                'overview' => $plan->overview ?? '',
                'business_landscape' => $plan->business_landscape ?? '',
                'products' => $plan->products ?? [],
                'leadership_contacts' => $plan->leadership_contacts ?? [],
                'competitor_analysis' => $plan->competitor_analysis ?? '',
                'opportunities' => $plan->opportunities ?? [],
                'recommendations' => $plan->recommendations ?? [],
                'risks' => $plan->risks ?? [],
                'next_steps' => $plan->next_steps ?? '',
            ],
            
            // Research Metadata
            'research_metadata' => [
                'research_status' => $plan->research_status ?? [],
                'research_sources' => $plan->research_sources ?? [],
                'last_updated_sections' => $plan->last_updated_sections ?? [],
            ],
            
            // Legacy fields for compatibility
            'market_position' => $plan->market_position ?? '',
            'financial_summary' => $plan->financial_summary ?? '',
            'key_contacts' => $plan->key_contacts ?? [],
        ];
    }

    /**
     * Regenerate a specific section
     */
    public function regenerateSection(string $sessionId, string $section): AccountPlan
    {
        // Clear the section - will be regenerated by agent
        return $this->updateSection($sessionId, $section, '');
    }

    /**
     * Update research status for tracking progress
     */
    public function updateResearchStatus(string $sessionId, string $parameter, string $status, array $sources = []): AccountPlan
    {
        $plan = $this->getOrCreatePlan($sessionId);
        
        $researchStatus = $plan->research_status ?? [];
        $researchStatus[$parameter] = [
            'status' => $status, // 'pending', 'researching', 'completed', 'conflicting'
            'updated_at' => now()->toISOString(),
            'sources' => $sources,
        ];
        
        $plan->research_status = $researchStatus;
        $plan->save();
        
        return $plan;
    }

    /**
     * Mark a section as conflicting and needs user input
     */
    public function markConflicting(string $sessionId, string $parameter, array $conflictingData): AccountPlan
    {
        return $this->updateResearchStatus($sessionId, $parameter, 'conflicting', $conflictingData);
    }

    /**
     * Get comprehensive search queries for a company
     */
    public function getComprehensiveSearchQueries(string $companyName): array
    {
        return [
            // Company Basics
            'company_overview' => [$companyName . ' overview', $companyName . ' company description'],
            'industry' => [$companyName . ' industry domain', $companyName . ' business sector'],
            'products' => [$companyName . ' products platforms', $companyName . ' main offerings'],
            'business_model' => [$companyName . ' business model', $companyName . ' revenue model'],
            'headquarters' => [$companyName . ' headquarters location', $companyName . ' offices global presence'],
            'founding' => [$companyName . ' founding year founders', $companyName . ' company history'],
            'employees' => [$companyName . ' employee count', $companyName . ' company size workforce'],
            
            // Financial
            'revenue' => [$companyName . ' revenue 2024', $companyName . ' annual revenue growth'],
            'funding' => [$companyName . ' funding history', $companyName . ' investment rounds valuation'],
            'investors' => [$companyName . ' investors', $companyName . ' venture capital funding'],
            'profitability' => [$companyName . ' profitability', $companyName . ' net income margins'],
            'public_private' => [$companyName . ' IPO', $companyName . ' stock ticker public private'],
            
            // Product & Technology
            'tech_stack' => [$companyName . ' tech stack', $companyName . ' technology architecture'],
            'ai_ml' => [$companyName . ' AI ML', $companyName . ' artificial intelligence machine learning'],
            'cloud' => [$companyName . ' cloud providers', $companyName . ' AWS Azure GCP'],
            'integrations' => [$companyName . ' integrations', $companyName . ' API partnerships'],
            
            // Leadership
            'executives' => [$companyName . ' CEO CTO leadership', $companyName . ' executive team management'],
            'hiring' => [$companyName . ' hiring trends', $companyName . ' job openings recruitment'],
            'layoffs' => [$companyName . ' layoffs', $companyName . ' workforce reduction'],
            'culture' => [$companyName . ' glassdoor', $companyName . ' company culture ratings'],
            
            // Market & Competition
            'competitors' => [$companyName . ' competitors', $companyName . ' competitive analysis'],
            'market' => [$companyName . ' market size TAM', $companyName . ' industry analysis'],
            'positioning' => [$companyName . ' market position', $companyName . ' competitive advantages'],
            
            // Customer & GTM
            'customers' => [$companyName . ' customers', $companyName . ' target market segments'],
            'partnerships' => [$companyName . ' partnerships', $companyName . ' strategic alliances'],
            'pricing' => [$companyName . ' pricing', $companyName . ' subscription model costs'],
            
            // Recent Events
            'news' => [$companyName . ' latest news', $companyName . ' recent announcements'],
            'acquisitions' => [$companyName . ' acquisitions', $companyName . ' mergers partnerships'],
            'product_launches' => [$companyName . ' new product launch', $companyName . ' product updates'],
            'analyst_reports' => [$companyName . ' Gartner', $companyName . ' Forrester G2 analyst reports'],
            
            // Pain Points & Opportunities
            'challenges' => [$companyName . ' challenges', $companyName . ' business problems'],
            'growth' => [$companyName . ' growth opportunities', $companyName . ' expansion plans'],
            'risks' => [$companyName . ' risks', $companyName . ' market threats challenges'],
        ];
    }

    /**
     * Get research progress summary
     */
    public function getResearchProgress(string $sessionId): array
    {
        $plan = $this->getPlan($sessionId);
        if (!$plan) {
            return ['total' => 0, 'completed' => 0, 'pending' => 0, 'conflicting' => 0];
        }
        
        $status = $plan->research_status ?? [];
        $total = count($this->getComprehensiveSearchQueries($plan->company_name ?? ''));
        
        $completed = count(array_filter($status, fn($s) => $s['status'] === 'completed'));
        $pending = count(array_filter($status, fn($s) => $s['status'] === 'pending'));
        $conflicting = count(array_filter($status, fn($s) => $s['status'] === 'conflicting'));
        
        return [
            'total' => $total,
            'completed' => $completed,
            'pending' => $pending,
            'conflicting' => $conflicting,
            'percentage' => $total > 0 ? round(($completed / $total) * 100) : 0,
        ];
    }

    /**
     * Generate the final structured account plan
     */
    public function generateFinalAccountPlan(string $sessionId): array
    {
        $plan = $this->getPlan($sessionId);
        if (!$plan) {
            return [];
        }
        
        return [
            'title' => 'Account Plan: ' . ($plan->company_name ?? 'Unknown Company'),
            'generated_at' => now()->toISOString(),
            'sections' => [
                '1. Company Overview' => $this->buildCompanyOverview($plan),
                '2. Business Landscape' => $this->buildBusinessLandscape($plan),
                '3. Products & Technology Stack' => $this->buildProductsTechnology($plan),
                '4. Leadership & Key Contacts' => $this->buildLeadershipContacts($plan),
                '5. Competitor Analysis' => $this->buildCompetitorAnalysis($plan),
                '6. Opportunities' => $this->buildOpportunities($plan),
                '7. Pain Points' => $this->buildPainPoints($plan),
                '8. Recommendations' => $this->buildRecommendations($plan),
                '9. Risks' => $this->buildRisks($plan),
                '10. Summary / Next Steps' => $this->buildNextSteps($plan),
            ]
        ];
    }

    private function buildCompanyOverview($plan): array
    {
        return [
            'description' => $plan->description ?? '',
            'industry' => $plan->industry ?? '',
            'headquarters' => $plan->headquarters ?? '',
            'founding_year' => $plan->founding_year ?? '',
            'employee_count' => $plan->employee_count ?? '',
            'business_model' => $plan->business_model ?? '',
            'value_proposition' => $plan->value_proposition ?? '',
        ];
    }

    private function buildBusinessLandscape($plan): array
    {
        return [
            'industry_overview' => $plan->industry_overview ?? '',
            'market_position' => $plan->market_position ?? '',
            'revenue' => $plan->revenue ?? '',
            'revenue_growth' => $plan->revenue_growth ?? '',
            'funding_status' => $plan->latest_valuation ?? '',
            'customer_segments' => $plan->customer_segments ?? [],
        ];
    }

    private function buildProductsTechnology($plan): array
    {
        return [
            'product_lines' => $plan->product_lines ?? [],
            'technology_stack' => $plan->technology_stack ?? [],
            'use_cases' => $plan->use_cases ?? [],
            'pricing_model' => $plan->pricing_model ?? '',
        ];
    }

    private function buildLeadershipContacts($plan): array
    {
        return [
            'key_executives' => $plan->key_executives ?? [],
            'culture_ratings' => $plan->culture_ratings ?? [],
            'hiring_trends' => $plan->hiring_trends ?? [],
        ];
    }

    private function buildCompetitorAnalysis($plan): array
    {
        return [
            'competitors' => $plan->competitors ?? [],
            'unique_differentiators' => $plan->unique_differentiators ?? [],
            'competitive_position' => $plan->competitor_analysis ?? '',
        ];
    }

    private function buildOpportunities($plan): array
    {
        return [
            'sales_opportunities' => $plan->sales_opportunities ?? [],
            'strategic_opportunities' => $plan->strategic_opportunities ?? [],
            'partnerships' => $plan->partnerships ?? [],
        ];
    }

    private function buildPainPoints($plan): array
    {
        return [
            'business_challenges' => $plan->business_pain_points ?? [],
            'technical_challenges' => $plan->technical_pain_points ?? [],
        ];
    }

    private function buildRecommendations($plan): array
    {
        return [
            'recommendations' => $plan->recommendations ?? [],
            'action_items' => [],
        ];
    }

    private function buildRisks($plan): array
    {
        return [
            'external_risks' => $plan->external_risks ?? [],
            'internal_risks' => $plan->internal_risks ?? [],
        ];
    }

    private function buildNextSteps($plan): array
    {
        return [
            'next_steps' => $plan->next_steps ?? '',
            'key_actions' => [],
            'timeline' => '',
        ];
    }
}

