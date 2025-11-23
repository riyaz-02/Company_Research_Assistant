<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class ConversationalAgentService
{
    private ResearchService $researchService;
    private PlanService $planService;
    private MemoryService $memoryService;
    private string $llmApiKey;
    
    // Research step definitions
    const STEPS = [
        'company_basics' => 1,
        'financial' => 2,
        'products_tech' => 3,
        'competitors' => 4,
        'pain_points' => 5,
        'recommendations' => 6,
        'final_plan' => 7
    ];

    public function __construct(
        ResearchService $researchService,
        PlanService $planService,
        MemoryService $memoryService
    ) {
        $this->researchService = $researchService;
        $this->planService = $planService;
        $this->memoryService = $memoryService;
        $this->llmApiKey = env('LLM_API_KEY', '');
    }

    /**
     * Process user message in conversational flow
     */
    public function processMessage(string $sessionId, string $userMessage): array
    {
        // Add user message to memory
        $this->memoryService->addMessage($sessionId, 'user', $userMessage);
        
        // Get session state
        $state = $this->getSessionState($sessionId);
        
        // Determine if this is initial greeting
        if (empty($state['company_name']) && !$this->containsCompanyIntent($userMessage)) {
            return $this->handleInitialGreeting();
        }
        
        // Extract company name if not set
        if (empty($state['company_name'])) {
            $companyName = $this->extractCompanyName($userMessage);
            if ($companyName) {
                $this->setSessionState($sessionId, 'company_name', $companyName);
                return $this->confirmCompany($companyName);
            } else {
                return $this->askForCompanyName();
            }
        }
        
        // Handle button responses
        if ($this->isButtonResponse($userMessage)) {
            return $this->handleButtonAction($sessionId, $userMessage, $state);
        }
        
        // Continue research workflow
        return $this->continueResearchWorkflow($sessionId, $state);
    }

    /**
     * Initial greeting with sample questions
     */
    private function handleInitialGreeting(): array
    {
        return [[
            'type' => 'greeting',
            'content' => 'Welcome to Company Research Assistant! I can help you create comprehensive account plans with detailed research.',
            'sample_questions' => [
                'Research Microsoft comprehensively',
                'Create account plan for Salesforce',
                'Analyze Google competitors and market position',
                'Generate detailed research for Amazon'
            ]
        ]];
    }

    /**
     * Confirm company name
     */
    private function confirmCompany(string $companyName): array
    {
        return [[
            'type' => 'ask_user',
            'content' => "I'll research **{$companyName}**. Is this correct?",
            'buttons' => ['Yes', 'No, let me clarify']
        ]];
    }

    /**
     * Ask for company name
     */
    private function askForCompanyName(): array
    {
        return [[
            'type' => 'ask_user',
            'content' => 'Which company would you like me to research?',
            'buttons' => []
        ]];
    }

    /**
     * Continue research workflow based on current step
     */
    private function continueResearchWorkflow(string $sessionId, array $state): array
    {
        $currentStep = $state['current_step'] ?? 'company_basics';
        $companyName = $state['company_name'];
        
        switch ($currentStep) {
            case 'company_basics':
                return $this->performCompanyBasicsResearch($sessionId, $companyName);
            
            case 'financial':
                return $this->performFinancialResearch($sessionId, $companyName);
            
            case 'products_tech':
                return $this->performProductsTechResearch($sessionId, $companyName);
            
            case 'competitors':
                return $this->performCompetitorsResearch($sessionId, $companyName);
            
            case 'pain_points':
                return $this->performPainPointsAnalysis($sessionId, $companyName);
            
            case 'recommendations':
                return $this->generateRecommendations($sessionId, $companyName);
            
            case 'final_plan':
                return $this->generateFinalPlan($sessionId, $companyName);
            
            default:
                return $this->performCompanyBasicsResearch($sessionId, $companyName);
        }
    }

    /**
     * Step 1: Company Basics Research
     */
    private function performCompanyBasicsResearch(string $sessionId, string $companyName): array
    {
        $responses = [];
        
        // Show progress
        $responses[] = [
            'type' => 'progress',
            'content' => 'Researching company basics...'
        ];
        
        // Fetch from SerpAPI
        $queries = [
            "{$companyName} company overview",
            "{$companyName} founding year founders",
            "{$companyName} headquarters employees"
        ];
        
        $results = [];
        foreach ($queries as $query) {
            $searchResults = $this->researchService->search($query);
            $results = array_merge($results, $searchResults);
        }
        
        // Extract structured data
        $basics = $this->extractCompanyBasics($results, $companyName);
        
        // Store in plan
        $this->planService->updateSection($sessionId, 'company_basics', $basics);
        
        // Present findings
        $responses[] = [
            'type' => 'update_plan',
            'section' => 'company_basics',
            'content' => $this->formatCompanyBasics($basics)
        ];
        
        // Ask user for next action
        $responses[] = [
            'type' => 'ask_user',
            'content' => 'Would you like to retrieve detailed company information, or proceed to financial research?',
            'buttons' => ['Deep Research', 'Next Step']
        ];
        
        return $responses;
    }

    /**
     * Step 2: Financial Research
     */
    private function performFinancialResearch(string $sessionId, string $companyName): array
    {
        $responses = [];
        
        $responses[] = [
            'type' => 'progress',
            'content' => 'Analyzing financial parameters...'
        ];
        
        $queries = [
            "{$companyName} revenue annual report",
            "{$companyName} funding valuation",
            "{$companyName} financial performance"
        ];
        
        $results = [];
        foreach ($queries as $query) {
            $searchResults = $this->researchService->search($query);
            $results = array_merge($results, $searchResults);
        }
        
        // Extract financial data
        $financial = $this->extractFinancialData($results, $companyName);
        
        // Detect conflicts
        $conflicts = $this->detectConflicts($financial);
        
        if (!empty($conflicts)) {
            return $this->handleConflicts($conflicts, $financial);
        }
        
        // Store in plan
        $this->planService->updateSection($sessionId, 'financial_info', $financial);
        
        $responses[] = [
            'type' => 'update_plan',
            'section' => 'financial_info',
            'content' => $this->formatFinancialData($financial)
        ];
        
        $responses[] = [
            'type' => 'ask_user',
            'content' => 'Would you like detailed financial analysis, or proceed to products & technology?',
            'buttons' => ['Deep Research', 'Next Step']
        ];
        
        // Update step
        $this->setSessionState($sessionId, 'current_step', 'products_tech');
        
        return $responses;
    }

    /**
     * Step 3: Products & Technology
     */
    private function performProductsTechResearch(string $sessionId, string $companyName): array
    {
        $responses[] = [
            'type' => 'progress',
            'content' => 'Researching products and technology...'
        ];
        
        $queries = [
            "{$companyName} products services",
            "{$companyName} technology stack",
            "{$companyName} features use cases"
        ];
        
        $results = [];
        foreach ($queries as $query) {
            $results = array_merge($results, $this->researchService->search($query));
        }
        
        $products = $this->extractProductsData($results, $companyName);
        $this->planService->updateSection($sessionId, 'product_technology', $products);
        
        $responses[] = [
            'type' => 'update_plan',
            'section' => 'product_technology',
            'content' => $this->formatProductsData($products)
        ];
        
        $responses[] = [
            'type' => 'ask_user',
            'content' => 'Deepen this research or move to competitor analysis?',
            'buttons' => ['Deep Research', 'Next Step']
        ];
        
        $this->setSessionState($sessionId, 'current_step', 'competitors');
        return $responses;
    }

    /**
     * Step 4: Competitors Research
     */
    private function performCompetitorsResearch(string $sessionId, string $companyName): array
    {
        $responses[] = [
            'type' => 'progress',
            'content' => 'Analyzing competitive landscape...'
        ];
        
        $queries = [
            "{$companyName} competitors",
            "{$companyName} vs competitors market share"
        ];
        
        $results = [];
        foreach ($queries as $query) {
            $results = array_merge($results, $this->researchService->search($query));
        }
        
        $competitors = $this->extractCompetitorsData($results, $companyName);
        $this->planService->updateSection($sessionId, 'market_analysis', $competitors);
        
        $responses[] = [
            'type' => 'update_plan',
            'section' => 'market_analysis',
            'content' => $this->formatCompetitorsData($competitors)
        ];
        
        $responses[] = [
            'type' => 'ask_user',
            'content' => 'Deeper competitor research or proceed to pain points analysis?',
            'buttons' => ['Deep Research', 'Next Step']
        ];
        
        $this->setSessionState($sessionId, 'current_step', 'pain_points');
        return $responses;
    }

    /**
     * Step 5: Pain Points Analysis (using Gemini)
     */
    private function performPainPointsAnalysis(string $sessionId, string $companyName): array
    {
        $responses[] = [
            'type' => 'progress',
            'content' => 'Analyzing pain points and opportunities...'
        ];
        
        // Get all previously gathered data
        $plan = $this->planService->getPlan($sessionId);
        
        // Use Gemini to infer pain points
        $painPoints = $this->inferPainPoints($companyName, $plan);
        
        $this->planService->updateSection($sessionId, 'pain_points', $painPoints);
        
        $responses[] = [
            'type' => 'update_plan',
            'section' => 'pain_points',
            'content' => $this->formatPainPoints($painPoints)
        ];
        
        $responses[] = [
            'type' => 'ask_user',
            'content' => 'Deeper analysis or proceed to recommendations?',
            'buttons' => ['Deep Research', 'Next Step']
        ];
        
        $this->setSessionState($sessionId, 'current_step', 'recommendations');
        return $responses;
    }

    /**
     * Step 6: Generate Recommendations
     */
    private function generateRecommendations(string $sessionId, string $companyName): array
    {
        $responses[] = [
            'type' => 'progress',
            'content' => 'Compiling strategic recommendations...'
        ];
        
        $plan = $this->planService->getPlan($sessionId);
        $recommendations = $this->synthesizeRecommendations($companyName, $plan);
        
        $this->planService->updateSection($sessionId, 'strategic_assessment', $recommendations);
        
        $responses[] = [
            'type' => 'update_plan',
            'section' => 'strategic_assessment',
            'content' => $this->formatRecommendations($recommendations)
        ];
        
        $responses[] = [
            'type' => 'ask_user',
            'content' => 'Ready to generate the final account plan?',
            'buttons' => ['Generate Final Plan', 'Review Steps']
        ];
        
        $this->setSessionState($sessionId, 'current_step', 'final_plan');
        return $responses;
    }

    /**
     * Step 7: Generate Final Account Plan
     */
    private function generateFinalPlan(string $sessionId, string $companyName): array
    {
        $responses[] = [
            'type' => 'progress',
            'content' => 'Generating comprehensive account plan...'
        ];
        
        $plan = $this->planService->getPlan($sessionId);
        $finalPlan = $this->synthesizeFinalPlan($companyName, $plan);
        
        $responses[] = [
            'type' => 'finish',
            'content' => $finalPlan,
            'buttons' => ['Download PDF', 'Regenerate Section', 'Start New Research']
        ];
        
        return $responses;
    }

    /**
     * Detect conflicts in data
     */
    private function detectConflicts(array $data): array
    {
        $conflicts = [];
        
        foreach ($data as $key => $values) {
            if (!is_array($values) || count($values) < 2) {
                continue;
            }
            
            // Numeric conflict detection (3% threshold)
            if (is_numeric($values[0])) {
                $min = min($values);
                $max = max($values);
                $variance = (($max - $min) / $min) * 100;
                
                if ($variance > 3) {
                    $conflicts[$key] = [
                        'type' => 'numeric',
                        'values' => $values,
                        'variance' => round($variance, 2)
                    ];
                }
            } else {
                // Non-numeric conflict
                $unique = array_unique($values);
                if (count($unique) > 1) {
                    $conflicts[$key] = [
                        'type' => 'non_numeric',
                        'values' => $unique
                    ];
                }
            }
        }
        
        return $conflicts;
    }

    /**
     * Handle conflicts with user clarification
     */
    private function handleConflicts(array $conflicts, array $data): array
    {
        $responses = [];
        
        foreach ($conflicts as $key => $conflict) {
            $displayKey = ucwords(str_replace('_', ' ', $key));
            
            if ($conflict['type'] === 'numeric') {
                $responses[] = [
                    'type' => 'ask_user',
                    'content' => "Found conflicting values for {$displayKey}: " . implode(', ', $conflict['values']) . " (variance: {$conflict['variance']}%)",
                    'buttons' => [
                        'Use Official Data',
                        'Use Higher Value',
                        'Use Lower Value',
                        'Conduct Deeper Research'
                    ],
                    'conflict_key' => $key
                ];
            } else {
                $responses[] = [
                    'type' => 'ask_user',
                    'content' => "Found different values for {$displayKey}: " . implode(' vs ', $conflict['values']),
                    'buttons' => array_merge(
                        array_map(fn($v) => "Use: {$v}", $conflict['values']),
                        ['Conduct Deeper Research']
                    ),
                    'conflict_key' => $key
                ];
            }
        }
        
        return $responses;
    }

    /**
     * Handle button action responses
     */
    private function handleButtonAction(string $sessionId, string $action, array $state): array
    {
        $action = strtolower(trim($action));
        
        if ($action === 'yes') {
            // Confirmed company, start research
            $this->setSessionState($sessionId, 'current_step', 'company_basics');
            return $this->continueResearchWorkflow($sessionId, $state);
        }
        
        if ($action === 'no, let me clarify') {
            $this->setSessionState($sessionId, 'company_name', '');
            return $this->askForCompanyName();
        }
        
        if ($action === 'next step') {
            // Move to next research step
            return $this->continueResearchWorkflow($sessionId, $state);
        }
        
        if ($action === 'deep research') {
            // Perform deeper research on current step
            return $this->performDeepResearch($sessionId, $state);
        }
        
        if (str_contains($action, 'use official data')) {
            return $this->resolveConflictWithOfficial($sessionId, $state);
        }
        
        // Handle other button actions...
        return $this->continueResearchWorkflow($sessionId, $state);
    }

    // Utility methods
    private function getSessionState(string $sessionId): array
    {
        return cache()->get("session_state_{$sessionId}", []);
    }

    private function setSessionState(string $sessionId, string $key, $value): void
    {
        $state = $this->getSessionState($sessionId);
        $state[$key] = $value;
        cache()->put("session_state_{$sessionId}", $state, now()->addHours(24));
    }

    private function isButtonResponse(string $message): bool
    {
        $buttonKeywords = ['yes', 'no', 'deep research', 'next step', 'use official', 'use higher', 'use lower', 'conduct deeper'];
        $lower = strtolower($message);
        
        foreach ($buttonKeywords as $keyword) {
            if (str_contains($lower, $keyword)) {
                return true;
            }
        }
        
        return false;
    }

    private function containsCompanyIntent(string $message): bool
    {
        $keywords = ['research', 'analyze', 'create', 'generate', 'account plan', 'company'];
        $lower = strtolower($message);
        
        foreach ($keywords as $keyword) {
            if (str_contains($lower, $keyword)) {
                return true;
            }
        }
        
        return false;
    }

    private function extractCompanyName(string $message): ?string
    {
        // Use Gemini to extract company name
        $prompt = "Extract only the company name from this message. Return just the company name, nothing else: {$message}";
        return $this->callGemini($prompt);
    }

    // Placeholder methods for data extraction and formatting
    private function extractCompanyBasics(array $results, string $companyName): array { return []; }
    private function extractFinancialData(array $results, string $companyName): array { return []; }
    private function extractProductsData(array $results, string $companyName): array { return []; }
    private function extractCompetitorsData(array $results, string $companyName): array { return []; }
    
    private function formatCompanyBasics(array $data): string { return json_encode($data); }
    private function formatFinancialData(array $data): string { return json_encode($data); }
    private function formatProductsData(array $data): string { return json_encode($data); }
    private function formatCompetitorsData(array $data): string { return json_encode($data); }
    private function formatPainPoints(array $data): string { return json_encode($data); }
    private function formatRecommendations(array $data): string { return json_encode($data); }
    
    private function inferPainPoints(string $companyName, array $plan): array { return []; }
    private function synthesizeRecommendations(string $companyName, array $plan): array { return []; }
    private function synthesizeFinalPlan(string $companyName, array $plan): string { return ''; }
    private function performDeepResearch(string $sessionId, array $state): array { return []; }
    private function resolveConflictWithOfficial(string $sessionId, array $state): array { return []; }
    
    private function callGemini(string $prompt): ?string
    {
        try {
            $response = Http::withHeaders([
                'Content-Type' => 'application/json',
            ])->post("https://generativelanguage.googleapis.com/v1beta/models/gemini-2.0-flash-exp:generateContent?key={$this->llmApiKey}", [
                'contents' => [[
                    'parts' => [['text' => $prompt]]
                ]]
            ]);

            if ($response->successful()) {
                $data = $response->json();
                return $data['candidates'][0]['content']['parts'][0]['text'] ?? null;
            }
        } catch (\Exception $e) {
            Log::error('Gemini API error: ' . $e->getMessage());
        }
        
        return null;
    }
}
