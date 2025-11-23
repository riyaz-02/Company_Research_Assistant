<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;

class AgentService
{
    private ResearchService $researchService;
    private PlanService $planService;
    private MemoryService $memoryService;
    private string $llmProvider;
    private string $llmApiKey;
    private string $llmEndpoint;
    private int $maxIterations = 3; // Further reduced to avoid timeouts

    public function __construct(
        ResearchService $researchService,
        PlanService $planService,
        MemoryService $memoryService
    ) {
        $this->researchService = $researchService;
        $this->planService = $planService;
        $this->memoryService = $memoryService;
        
        $this->llmProvider = env('LLM_PROVIDER', 'openai'); // openai, anthropic, gemini, etc.
        $this->llmApiKey = env('LLM_API_KEY', '');
        $this->llmEndpoint = env('LLM_ENDPOINT', '');
    }

    /**
     * Process user message and run agent loop
     */
    public function processMessage(string $sessionId, string $userMessage): array
    {
        // Add user message to memory
        $this->memoryService->addMessage($sessionId, 'user', $userMessage);
        
        // Check if this is step-by-step mode
        $state = $this->getSessionState($sessionId);
        
        Log::info('Processing message', [
            'step_mode' => $state['step_mode'],
            'is_research' => $this->isResearchRequest($userMessage),
            'message' => $userMessage
        ]);
        
        // If not in step mode and this is a research request, enter step mode
        if (!$state['step_mode'] && $this->isResearchRequest($userMessage)) {
            Log::info('Starting step-by-step research');
            return $this->startStepByStepResearch($sessionId, $userMessage);
        }
        
        // If in step mode, process step-by-step response
        if ($state['step_mode']) {
            Log::info('Processing step response', ['current_step' => $state['current_step']]);
            return $this->processStepResponse($sessionId, $userMessage, $state);
        }
        
        // Try to extract company name from user message
        $this->extractAndSetCompanyName($sessionId, $userMessage);

        $responses = [];
        $iteration = 0;
        $messages = [];
        
        // Initialize progress tracking
        $startTime = microtime(true);
        $this->initializeResearchProgress($sessionId, $userMessage);

        // Get conversation history with tool messages
        $history = $this->memoryService->getRecentMessages($sessionId, 20);
        $plan = $this->planService->getPlan($sessionId);

        // Build initial messages array
        $messages = $this->buildMessages($sessionId, $userMessage, $history, $plan);

        // Run agent loop
        while ($iteration < $this->maxIterations) {
            $iteration++;

            // Get LLM response with action
            $llmResponse = $this->callLLMWithMessages($messages);
            
            // Handle rate limiting
            if ($llmResponse && isset($llmResponse['action']) && $llmResponse['action'] === 'ask_user' && 
                isset($llmResponse['question']) && str_contains($llmResponse['question'], 'rate limit')) {
                $responses[] = [
                    'type' => 'message',
                    'content' => $llmResponse['question'] ?? 'Rate limit reached. Please wait a moment.',
                    'action' => 'ask_user',
                ];
                break;
            }

            // Log the raw LLM response for debugging
            Log::info('LLM Response iteration ' . $iteration, ['response' => $llmResponse]);
            
            // Validate JSON response
            if (!$llmResponse || !isset($llmResponse['action'])) {
                // Try to extract JSON and retry once
                if ($iteration === 1) {
                    $llmResponse = $this->retryWithExtraction($messages);
                    Log::info('Retry extraction result', ['response' => $llmResponse]);
                }
                
                if (!$llmResponse || !isset($llmResponse['action'])) {
                    Log::error('No valid LLM response after retries', ['iteration' => $iteration, 'raw_response' => $llmResponse]);
                    
                    // If we have search results but no LLM response, try to process them automatically
                    if ($this->hasRecentSearchResults($messages)) {
                        $autoProcessed = $this->autoProcessSearchResults($sessionId, $messages);
                        if ($autoProcessed) {
                            $responses = array_merge($responses, $autoProcessed);
                            break;
                        }
                    }
                    
                    if ($iteration === 1) {
                        $responses[] = [
                            'type' => 'message',
                            'content' => 'I apologize, but I\'m having trouble connecting to the AI service. Please check that your LLM API key is correctly configured.',
                            'action' => 'error',
                        ];
                    }
                    break;
                }
            }

            $action = $llmResponse['action'];
            
            // Handle each action type
            switch ($action) {
                case 'search':
                    $query = $llmResponse['query'] ?? '';
                    if ($query) {
                        // Show search start with timestamp
                        $responses[] = [
                            'type' => 'search_start',
                            'content' => "Searching: {$query}",
                            'query' => $query,
                            'timestamp' => date('H:i:s'),
                            'iteration' => $iteration,
                        ];
                        
                        $searchStart = microtime(true);
                        $results = $this->researchService->search($query);
                        $searchDuration = round((microtime(true) - $searchStart) * 1000); // milliseconds
                        
                        // Add tool message with search results
                        $messages[] = [
                            'role' => 'tool',
                            'content' => json_encode([
                                'action' => 'search',
                                'query' => $query,
                                'results' => $results,
                                'count' => count($results),
                                'duration_ms' => $searchDuration,
                            ]),
                        ];
                        
                        // Store in memory
                        $this->memoryService->addMessage($sessionId, 'tool', json_encode(['action' => 'search', 'query' => $query, 'results_count' => count($results)]));
                        
                        // Show search completion with results
                        $resultCount = count($results);
                        $responses[] = [
                            'type' => 'search_complete',
                            'content' => "Found {$resultCount} results in {$searchDuration}ms",
                            'results_count' => $resultCount,
                            'duration' => $searchDuration,
                            'query' => $query,
                        ];
                    }
                    break;

                case 'update_plan':
                    // Support both old 'section' format and new 'parameter' format
                    $parameter = $llmResponse['parameter'] ?? $llmResponse['section'] ?? '';
                    $content = $llmResponse['content'] ?? '';
                    $evidence = $llmResponse['evidence'] ?? [];
                    
                    if ($parameter && $content !== null) {
                        $updated = $this->planService->updateSection($sessionId, $parameter, $content, $evidence);
                        
                        // Update research status
                        $this->planService->updateResearchStatus($sessionId, $parameter, 'completed', $evidence);
                        
                        // Get current progress
                        $progress = $this->planService->getResearchProgress($sessionId);
                        
                        // Add tool message
                        $messages[] = [
                            'role' => 'tool',
                            'content' => json_encode([
                                'action' => 'update_plan',
                                'parameter' => $parameter,
                                'status' => 'updated',
                                'progress' => $progress,
                            ]),
                        ];
                        
                        // Store in memory
                        $this->memoryService->addMessage($sessionId, 'tool', json_encode(['action' => 'update_plan', 'parameter' => $parameter]));
                        
                        // Update plan in response with detailed info
                        $responses[] = [
                            'type' => 'parameter_updated',
                            'parameter' => $parameter,
                            'content' => $content,
                            'evidence_count' => count($evidence),
                            'progress' => $progress,
                            'timestamp' => date('H:i:s'),
                        ];
                    }
                    break;

                case 'progress_update':
                    $status = $llmResponse['status'] ?? '';
                    if ($status) {
                        // Get current research progress
                        $progress = $this->planService->getResearchProgress($sessionId);
                        $elapsedTime = round(microtime(true) - $startTime);
                        
                        // Store progress message
                        $this->memoryService->addMessage($sessionId, 'assistant', $status, ['action' => 'progress_update']);
                        
                        // Return detailed progress update
                        $responses[] = [
                            'type' => 'detailed_progress',
                            'content' => "Progress: " . $status,
                            'progress' => $progress,
                            'elapsed_time' => $elapsedTime,
                            'current_phase' => $this->getCurrentResearchPhase($progress),
                            'next_actions' => $this->getNextResearchActions($sessionId),
                            'timestamp' => date('H:i:s'),
                        ];
                    }
                    break;

                case 'generate_final_plan':
                    // Generate the comprehensive account plan document
                    $finalPlan = $this->planService->generateFinalAccountPlan($sessionId);
                    
                    // Store the generated plan
                    $this->planService->updateSection($sessionId, 'final_account_plan', $finalPlan);
                    
                    // Add tool message
                    $messages[] = [
                        'role' => 'tool',
                        'content' => json_encode([
                            'action' => 'generate_final_plan',
                            'status' => 'completed',
                            'sections_count' => count($finalPlan['sections'] ?? []),
                        ]),
                    ];
                    
                    // Store in memory
                    $this->memoryService->addMessage($sessionId, 'tool', json_encode(['action' => 'generate_final_plan', 'status' => 'completed']));
                    
                    // Return final plan
                    $responses[] = [
                        'type' => 'final_plan',
                        'content' => '📋 Generated comprehensive account plan with ' . count($finalPlan['sections'] ?? []) . ' sections',
                        'plan' => $finalPlan,
                    ];
                    break;

                case 'ask_user':
                    $question = $llmResponse['question'] ?? '';
                    if ($question) {
                        // Store assistant message
                        $this->memoryService->addMessage($sessionId, 'assistant', $question, ['action' => 'ask_user']);
                        
                        // Add to messages for context
                        $messages[] = [
                            'role' => 'assistant',
                            'content' => $question,
                        ];
                        
                        // Return question to user
                        $responses[] = [
                            'type' => 'message',
                            'content' => "Question: " . $question,
                            'action' => 'ask_user',
                        ];
                        
                        // Break loop to wait for user response
                        break;
                    }
                    break;

                case 'finish':
                    $content = $llmResponse['content'] ?? $llmResponse['message'] ?? 'Research completed.';
                    
                    // Get final research progress
                    $progress = $this->planService->getResearchProgress($sessionId);
                    
                    // Store final message
                    $this->memoryService->addMessage($sessionId, 'assistant', $content, ['action' => 'finish']);
                    
                    // Return final message
                    $responses[] = [
                        'type' => 'message',
                        'content' => "Completed: " . $content,
                        'action' => 'finish',
                        'progress' => $progress,
                    ];
                    
                    // Break loop
                    break 2;

                default:
                    Log::warning('Unknown action: ' . $action);
                    break;
            }

            // Add a small delay to avoid rate limiting
            if ($iteration < $this->maxIterations && $action !== 'finish' && $action !== 'ask_user') {
                usleep(200000); // 0.2 second delay for faster execution
            }
        }

        // Ensure we always return at least one response
        if (empty($responses)) {
            $responses[] = [
                'type' => 'message',
                'content' => 'I received your message but couldn\'t generate a response. Please try again.',
                'action' => 'error',
            ];
        }

        return [
            'responses' => $responses,
            'plan' => $this->planService->getPlanSections($sessionId),
        ];
    }

    /**
     * Build messages array for LLM with tool outputs
     */
    private function buildMessages(string $sessionId, string $userMessage, array $history, $plan): array
    {
        $messages = [];
        
        // Add system prompt
        $messages[] = [
            'role' => 'system',
            'content' => $this->getSystemPrompt(),
        ];
        
        // Add plan context if available
        if ($plan) {
            $planInfo = "Current Account Plan Status:\n";
            $planInfo .= "Company: " . ($plan->company_name ?? 'Not set') . "\n";
            $sections = ['overview', 'products', 'competitors', 'opportunities', 'recommendations', 'market_position', 'financial_summary', 'key_contacts'];
            foreach ($sections as $section) {
                $value = $plan->$section ?? null;
                if ($value && (is_string($value) ? trim($value) : (is_array($value) ? count($value) > 0 : false))) {
                    $planInfo .= ucfirst($section) . ": " . (is_array($value) ? count($value) . " items" : "Set") . "\n";
                }
            }
            $messages[] = [
                'role' => 'system',
                'content' => $planInfo,
            ];
        }
        
        // Add conversation history with tool messages
        foreach ($history as $msg) {
            $role = $msg['role'] ?? 'user';
            $content = $msg['content'] ?? '';
            
            // Convert tool messages
            if ($role === 'tool') {
                $messages[] = [
                    'role' => 'tool',
                    'content' => $content,
                ];
            } elseif ($role === 'assistant') {
                $messages[] = [
                    'role' => 'assistant',
                    'content' => $content,
                ];
            } elseif ($role === 'user') {
                $messages[] = [
                    'role' => 'user',
                    'content' => $content,
                ];
            }
        }
        
        // Add current user message
        $messages[] = [
            'role' => 'user',
            'content' => $userMessage,
        ];
        
        return $messages;
    }

    /**
     * Build initial context for LLM (deprecated - use buildMessages)
     */
    private function buildContext(string $sessionId, string $userMessage, array $history, $plan): array
    {
        $systemPrompt = $this->getSystemPrompt();
        
        $conversationHistory = '';
        if (!empty($history)) {
            foreach (array_slice($history, -5) as $msg) {
                $role = $msg['role'] ?? 'user';
                $content = $msg['content'] ?? '';
                $conversationHistory .= "{$role}: {$content}\n";
            }
        }

        $planContext = '';
        if ($plan) {
            $planContext = "Current Account Plan:\n";
            $planContext .= "Company: " . ($plan->company_name ?? 'Not set') . "\n";
            $planContext .= "Overview: " . ($plan->overview ?? 'Not set') . "\n";
        }

        return [
            'system_prompt' => $systemPrompt,
            'conversation_history' => $conversationHistory,
            'plan_context' => $planContext,
            'user_message' => $userMessage,
            'session_id' => $sessionId,
        ];
    }

    /**
     * Get system prompt for the agent
     */
    private function getSystemPrompt(): string
    {
        return "You are ResearchAgent, an autonomous company research assistant specialized in comprehensive account planning.

CRITICAL RULES:
- ALWAYS respond with a valid JSON object. NO plain text responses unless action is 'finish'.
- NEVER repeat the same sentence multiple times.
- NEVER start every message with the same phrase.
- Vary your tone slightly but remain professional.
- Keep responses concise and professional.
- Follow the systematic research methodology outlined below.

REQUIRED JSON FORMAT:
{
    \"action\": \"search\" | \"update_plan\" | \"ask_user\" | \"progress_update\" | \"generate_final_plan\" | \"finish\",
    \"query\": \"\",  // Required for 'search' action
    \"parameter\": \"\",  // Required for 'update_plan' action
    \"content\": \"\",  // Required for 'update_plan' action
    \"evidence\": [],  // Optional: array of evidence sources
    \"question\": \"\",  // Required for 'ask_user' action
    \"status\": \"\"  // Required for 'progress_update' action
}

COMPREHENSIVE RESEARCH PARAMETERS:
You must systematically research ALL of these parameters:

1. COMPANY BASICS:
- description, industry, business_model, value_proposition
- headquarters, global_presence, founding_year, founders
- employee_count, employee_growth_trend

2. FINANCIAL PARAMETERS:
- revenue, revenue_growth, funding_rounds, latest_valuation
- investors, profitability, ipo_year, stock_ticker

3. PRODUCT & TECHNOLOGY:
- product_lines, technology_stack, target_customers, use_cases

4. LEADERSHIP & PEOPLE:
- key_executives, hiring_trends, culture_ratings

5. MARKET & COMPETITORS:
- industry_overview, competitors, unique_differentiators

6. CUSTOMER & GTM:
- customer_segments, partnerships, pricing_model

7. RECENT EVENTS:
- latest_news, analyst_reports

8. PAIN POINTS:
- business_pain_points, technical_pain_points

9. OPPORTUNITIES & RISKS:
- sales_opportunities, strategic_opportunities
- external_risks, internal_risks

ALLOWED ACTIONS:
1. \"search\": Perform targeted web search
   - Use strategic queries like: \"[company] revenue 2024\", \"[company] competitors\", \"[company] CEO leadership\"
   - Example: {\"action\": \"search\", \"query\": \"EightFold AI revenue growth funding 2024\"}

2. \"update_plan\": Update a specific research parameter
   - Use parameter names from the list above
   - Example: {\"action\": \"update_plan\", \"parameter\": \"revenue\", \"content\": \"$100M ARR as of 2024\", \"evidence\": [\"source1\", \"source2\"]}

3. \"progress_update\": Inform user of research progress
   - Example: {\"action\": \"progress_update\", \"status\": \"Completed company basics research (5/10 parameters). Now researching financial information...\"}

4. \"ask_user\": Ask clarifying questions or report conflicts
   - Example: {\"action\": \"ask_user\", \"question\": \"I found conflicting revenue data: $80M vs $120M for 2024. Should I dig deeper or which source should I prioritize?\"}

5. \"generate_final_plan\": Generate the structured account plan document
   - Use after completing all research parameters
   - Example: {\"action\": \"generate_final_plan\"}

6. \"finish\": Complete the research process
   - Example: {\"action\": \"finish\", \"content\": \"Account plan completed with 39/39 parameters researched.\"}

RESEARCH METHODOLOGY:
1. PRIORITIZE HIGH-IMPACT RESEARCH: Start with company basics, financials, and key differentiators
2. Use efficient multi-parameter searches: \"[company] overview revenue funding competitors\"
3. IMMEDIATELY PROCESS SEARCH RESULTS: After each search, analyze results and update plan parameters
4. Update 3-5 parameters per iteration to maximize efficiency
5. Provide progress updates after each major research phase
6. Report conflicts immediately and continue with alternative data
7. Generate final account plan after collecting core data
8. Focus on actionable insights over exhaustive research

EFFICIENCY RULES:
- Combine related searches: \"company overview AND revenue AND competitors\" 
- ALWAYS follow search with update_plan actions for found data
- Update multiple parameters from single search results
- Skip parameters with no readily available data
- Prioritize business-critical information (revenue, competitors, differentiators)

CRITICAL WORKFLOW:
1. search -> IMMEDIATELY -> update_plan (extract data from search results)
2. progress_update -> search -> update_plan -> repeat
3. Never search without processing the results

CONFLICT DETECTION:
- When you find conflicting information, immediately use 'ask_user' action
- Be specific about the conflict: \"Source A says X, Source B says Y\"
- Ask user which to prioritize or if you should research further

SEARCH QUERY STRATEGY:
Use these proven query patterns:
- \"[company] overview description industry\"
- \"[company] revenue 2024 financial performance\"
- \"[company] funding rounds investors valuation\"
- \"[company] CEO CTO leadership team\"
- \"[company] competitors competitive analysis\"
- \"[company] latest news recent developments\"
- \"[company] hiring layoffs workforce trends\"
- \"[company] product offerings technology stack\"

YOUR GOAL: Create a comprehensive, evidence-based account plan covering all 39+ research parameters.";
    }

    /**
     * Call LLM API
     */
    private function callLLM(array $context): ?array
    {
        if (!$this->llmApiKey) {
            Log::error('LLM API key not configured');
            return [
                'action' => 'ask_user',
                'message' => 'LLM API key is not configured. Please set LLM_API_KEY in .env file.',
                'thought' => 'Configuration error',
            ];
        }

        $messages = [
            [
                'role' => 'system',
                'content' => $context['system_prompt'],
            ],
        ];

        // Add conversation history
        if (!empty($context['conversation_history'])) {
            $historyLines = explode("\n", trim($context['conversation_history']));
            foreach ($historyLines as $line) {
                if (empty(trim($line))) continue;
                if (str_starts_with($line, 'user:')) {
                    $messages[] = ['role' => 'user', 'content' => trim(substr($line, 5))];
                } elseif (str_starts_with($line, 'assistant:')) {
                    $messages[] = ['role' => 'assistant', 'content' => trim(substr($line, 10))];
                }
            }
        }

        // Add plan context
        if (!empty($context['plan_context'])) {
            $messages[] = [
                'role' => 'system',
                'content' => $context['plan_context'],
            ];
        }

        // Add current user message
        $messages[] = [
            'role' => 'user',
            'content' => $context['user_message'],
        ];

        try {
            if ($this->llmProvider === 'openai' || empty($this->llmProvider)) {
                return $this->callOpenAI($messages);
            } elseif ($this->llmProvider === 'anthropic') {
                return $this->callAnthropic($messages);
            } elseif ($this->llmProvider === 'gemini') {
                return $this->callGemini($messages);
            } else {
                // Custom endpoint
                return $this->callCustomEndpoint($messages);
            }
        } catch (\Exception $e) {
            Log::error('LLM call failed: ' . $e->getMessage());
            return [
                'action' => 'ask_user',
                'message' => 'I encountered an error. Could you please rephrase your request?',
                'thought' => 'Error occurred',
            ];
        }
    }

    /**
     * Call OpenAI API
     */
    private function callOpenAI(array $messages): ?array
    {
        $endpoint = $this->llmEndpoint ?: 'https://api.openai.com/v1/chat/completions';
        
        $response = Http::withHeaders([
            'Authorization' => 'Bearer ' . $this->llmApiKey,
            'Content-Type' => 'application/json',
        ])->post($endpoint, [
            'model' => env('LLM_MODEL', 'gpt-4o-mini'),
            'messages' => $messages,
            'temperature' => 0.7,
            'response_format' => ['type' => 'json_object'],
        ]);

        if ($response->successful()) {
            $data = $response->json();
            $content = $data['choices'][0]['message']['content'] ?? '';
            $parsed = json_decode($content, true);
            
            if ($parsed && isset($parsed['action'])) {
                return $this->validateAction($parsed);
            }
        }

        return null;
    }

    /**
     * Call Anthropic API
     */
    private function callAnthropic(array $messages): ?array
    {
        $endpoint = $this->llmEndpoint ?: 'https://api.anthropic.com/v1/messages';
        
        // Convert messages format for Anthropic
        $anthropicMessages = [];
        foreach ($messages as $msg) {
            if ($msg['role'] !== 'system') {
                $anthropicMessages[] = $msg;
            }
        }

        $systemMessage = '';
        foreach ($messages as $msg) {
            if ($msg['role'] === 'system') {
                $systemMessage .= $msg['content'] . "\n";
            }
        }

        $response = Http::withHeaders([
            'x-api-key' => $this->llmApiKey,
            'anthropic-version' => '2023-06-01',
            'Content-Type' => 'application/json',
        ])->post($endpoint, [
            'model' => env('LLM_MODEL', 'claude-3-5-sonnet-20241022'),
            'messages' => $anthropicMessages,
            'system' => $systemMessage,
            'max_tokens' => 4096,
        ]);

        if ($response->successful()) {
            $data = $response->json();
            $content = $data['content'][0]['text'] ?? '';
            $parsed = json_decode($content, true);
            
            if ($parsed && isset($parsed['action'])) {
                return $this->validateAction($parsed);
            }
        }

        return null;
    }

    /**
     * Call Google Gemini API with tool message support
     */
    private function callGemini(array $messages): ?array
    {
        $model = env('LLM_MODEL', 'gemini-2.5-flash');
        // Try v1beta first, fallback to v1 if model not found
        $endpoint = "https://generativelanguage.googleapis.com/v1beta/models/{$model}:generateContent?key=" . $this->llmApiKey;
        
        // Convert messages format for Gemini
        // Gemini uses a different format - it expects contents array
        $contents = [];
        $systemInstruction = '';
        
        foreach ($messages as $msg) {
            $role = $msg['role'] ?? 'user';
            $content = $msg['content'] ?? '';
            
            if ($role === 'system') {
                $systemInstruction .= $content . "\n";
            } elseif ($role === 'tool') {
                // Tool messages are added as user messages with tool prefix
                $contents[] = [
                    'role' => 'user',
                    'parts' => [['text' => "Tool output: {$content}"]]
                ];
            } else {
                // Gemini uses 'user' and 'model' roles
                $geminiRole = $role === 'assistant' ? 'model' : 'user';
                $contents[] = [
                    'role' => $geminiRole,
                    'parts' => [['text' => $content]]
                ];
            }
        }
        
        // If no contents, add a placeholder
        if (empty($contents)) {
            $contents[] = [
                'role' => 'user',
                'parts' => [['text' => 'Hello']]
            ];
        }
        
        $payload = [
            'contents' => $contents,
        ];
        
        // Add system instruction if available (v1beta supports this)
        if (!empty($systemInstruction)) {
            $payload['systemInstruction'] = [
                'parts' => [['text' => trim($systemInstruction)]]
            ];
        }
        
        // Generation config for v1beta - request JSON format
        $payload['generationConfig'] = [
            'temperature' => 0.7,
            'responseMimeType' => 'application/json',
        ];
        
        Log::info('Gemini API Call', [
            'endpoint' => $endpoint,
            'payload_size' => strlen(json_encode($payload)),
            'contents_count' => count($contents),
        ]);

        $response = Http::withHeaders([
            'Content-Type' => 'application/json',
        ])->post($endpoint, $payload);

        Log::info('Gemini API Response', [
            'status' => $response->status(),
            'successful' => $response->successful(),
            'body_length' => strlen($response->body()),
        ]);

        // If v1beta fails with 404 or 400, try v1 API (without systemInstruction and responseMimeType)
        if (!$response->successful() && ($response->status() === 404 || $response->status() === 400)) {
            Log::info('v1beta API failed, trying v1 API', [
                'error' => $response->body(),
            ]);
            $endpoint = "https://generativelanguage.googleapis.com/v1/models/{$model}:generateContent?key=" . $this->llmApiKey;
            
            // Rebuild payload for v1 (without unsupported fields)
            $v1Payload = [
                'contents' => $contents,
            ];
            
            // Add system instruction as first user message for v1
            if (!empty($systemInstruction)) {
                array_unshift($v1Payload['contents'], [
                    'role' => 'user',
                    'parts' => [['text' => trim($systemInstruction)]]
                ]);
            }
            
            // Simple generation config for v1
            $v1Payload['generationConfig'] = [
                'temperature' => 0.7,
            ];
            
            $response = Http::withHeaders([
                'Content-Type' => 'application/json',
            ])->post($endpoint, $v1Payload);
        }

        if ($response->successful()) {
            $data = $response->json();
            
            Log::info('Gemini Response Data', [
                'has_candidates' => isset($data['candidates']),
                'candidates_count' => isset($data['candidates']) ? count($data['candidates']) : 0,
            ]);
            
            // Extract text from Gemini response
            $text = '';
            if (isset($data['candidates'][0]['content']['parts'][0]['text'])) {
                $text = $data['candidates'][0]['content']['parts'][0]['text'];
            }
            
            Log::info('Gemini Extracted Text', [
                'text_length' => strlen($text),
                'text_preview' => substr($text, 0, 200),
            ]);
            
            if ($text) {
                // Clean the text - remove markdown code blocks if present
                $text = preg_replace('/```json\s*/', '', $text);
                $text = preg_replace('/```\s*/', '', $text);
                $text = trim($text);
                
                // Try to parse as JSON
                $parsed = json_decode($text, true);
                
                if ($parsed && isset($parsed['action'])) {
                    return $this->validateAction($parsed);
                }
                
                // If not JSON, try to extract JSON from text using a more robust regex
                if (preg_match('/\{[^{}]*(?:\{[^{}]*\}[^{}]*)*"action"[^{}]*(?:\{[^{}]*\}[^{}]*)*\}/s', $text, $matches)) {
                    $parsed = json_decode($matches[0], true);
                    if ($parsed && isset($parsed['action'])) {
                        return $this->validateAction($parsed);
                    }
                }
                
                // Last resort: try to find any JSON object with action
                if (preg_match('/\{.*?"action".*?\}/s', $text, $matches)) {
                    $parsed = json_decode($matches[0], true);
                    if ($parsed && isset($parsed['action'])) {
                        return $this->validateAction($parsed);
                    }
                }
                
                // If we still can't parse, log the response for debugging
                Log::warning('Gemini response could not be parsed as JSON: ' . substr($text, 0, 200));
            }
        } else {
            $errorBody = $response->body();
            Log::error('Gemini API error: ' . $errorBody);
            
            // Try to extract error message for user
            $errorData = json_decode($errorBody, true);
            if (isset($errorData['error']['message'])) {
                $errorMessage = $errorData['error']['message'];
                Log::error('Gemini API error message: ' . $errorMessage);
                
                // Handle rate limiting (429)
                if ($response->status() === 429 || str_contains($errorMessage, 'quota') || str_contains($errorMessage, 'Quota exceeded')) {
                    // Return a user-friendly message about rate limits
                    return [
                        'action' => 'ask_user',
                        'question' => 'The API rate limit has been reached (10 requests per minute on free tier). Please wait about a minute before trying again.',
                    ];
                }
            }
        }

        return null;
    }

    /**
     * Call custom endpoint
     */
    private function callCustomEndpoint(array $messages): ?array
    {
        $response = Http::withHeaders([
            'Authorization' => 'Bearer ' . $this->llmApiKey,
            'Content-Type' => 'application/json',
        ])->post($this->llmEndpoint, [
            'messages' => $messages,
        ]);

        if ($response->successful()) {
            $data = $response->json();
            return $data;
        }

        return null;
    }

    /**
     * Validate action JSON structure
     */
    private function validateAction(array $action): ?array
    {
        $allowedActions = ['search', 'update_plan', 'ask_user', 'finish'];
        
        if (!isset($action['action']) || !in_array($action['action'], $allowedActions)) {
            Log::warning('Invalid action: ' . ($action['action'] ?? 'missing'));
            return null;
        }
        
        $validated = ['action' => $action['action']];
        
        // Validate based on action type
        switch ($action['action']) {
            case 'search':
                if (empty($action['query'])) {
                    Log::warning('Search action missing query');
                    return null;
                }
                $validated['query'] = $action['query'];
                break;
                
            case 'update_plan':
                if (empty($action['section']) || !isset($action['content'])) {
                    Log::warning('Update_plan action missing section or content');
                    return null;
                }
                $validated['section'] = $action['section'];
                $validated['content'] = $action['content'];
                $validated['evidence'] = $action['evidence'] ?? [];
                break;
                
            case 'ask_user':
                if (empty($action['question'])) {
                    Log::warning('Ask_user action missing question');
                    return null;
                }
                $validated['question'] = $action['question'];
                break;
                
            case 'finish':
                $validated['content'] = $action['content'] ?? $action['message'] ?? 'Research completed.';
                break;
        }
        
        return $validated;
    }

    /**
     * Execute action based on LLM response (deprecated - actions now handled in processMessage)
     */
    private function executeAction(string $sessionId, string $action, array $llmResponse): array
    {
        $params = $llmResponse['params'] ?? [];

        switch ($action) {
            case 'search':
                $query = $params['query'] ?? '';
                if ($query) {
                    $results = $this->researchService->search($query);
                    return [
                        'success' => true,
                        'results' => $results,
                        'count' => count($results),
                    ];
                }
                return ['success' => false, 'error' => 'No search query provided'];

            case 'fetch':
                $url = $params['url'] ?? '';
                if ($url) {
                    $content = $this->researchService->fetchArticle($url);
                    return [
                        'success' => true,
                        'content' => $content,
                    ];
                }
                return ['success' => false, 'error' => 'No URL provided'];

            case 'update_plan':
                $section = $params['section'] ?? '';
                $content = $params['content'] ?? '';
                if ($section && $content !== null) {
                    $this->planService->updateSection($sessionId, $section, $content);
                    return [
                        'success' => true,
                        'section' => $section,
                    ];
                }
                return ['success' => false, 'error' => 'Section or content missing'];

            case 'ask_user':
                return [
                    'success' => true,
                    'question' => $params['question'] ?? 'Could you provide more information?',
                ];

            case 'finish':
                return [
                    'success' => true,
                    'completed' => true,
                ];

            default:
                return ['success' => false, 'error' => 'Unknown action'];
        }
    }

    /**
     * Update context with action result
     */
    private function updateContext(array $context, string $action, array $actionResult): array
    {
        $update = "\nAction executed: {$action}\n";
        
        if ($action === 'search' && isset($actionResult['results'])) {
            $update .= "Found " . $actionResult['count'] . " search results:\n";
            foreach (array_slice($actionResult['results'], 0, 3) as $result) {
                $update .= "- " . ($result['title'] ?? '') . ": " . ($result['snippet'] ?? '') . "\n";
            }
        } elseif ($action === 'update_plan' && isset($actionResult['section'])) {
            $update .= "Updated plan section: " . $actionResult['section'] . "\n";
        }

        $context['action_results'] = ($context['action_results'] ?? '') . $update;
        
        return $context;
    }

    /**
     * Extract company name from user message and update plan
     */
    private function extractAndSetCompanyName(string $sessionId, string $userMessage): void
    {
        // Simple extraction - look for common patterns
        $patterns = [
            '/research\s+(?:the\s+)?(?:company\s+)?([A-Z][a-zA-Z\s&]+?)(?:\s+company|\s+inc|\s+corp|$)/i',
            '/(?:company|about|for)\s+([A-Z][a-zA-Z\s&]+?)(?:\s+company|\s+inc|\s+corp|$)/i',
            '/^([A-Z][a-zA-Z\s&]+?)(?:\s+company|\s+inc|\s+corp|$)/i',
        ];
        
        foreach ($patterns as $pattern) {
            if (preg_match($pattern, $userMessage, $matches)) {
                $companyName = trim($matches[1]);
                if (strlen($companyName) > 2 && strlen($companyName) < 100) {
                    $plan = $this->planService->getPlan($sessionId);
                    if (!$plan || empty($plan->company_name)) {
                        $this->planService->updateCompanyName($sessionId, $companyName);
                    }
                    break;
                }
            }
        }
    }

    /**
     * Call LLM with pre-built messages array
     */
    private function callLLMWithMessages(array $messages): ?array
    {
        Log::info('LLM Call Debug', [
            'provider' => $this->llmProvider,
            'api_key_set' => !empty($this->llmApiKey),
            'api_key_length' => strlen($this->llmApiKey),
            'message_count' => count($messages),
        ]);
        
        if (!$this->llmApiKey) {
            Log::error('LLM API key not configured');
            return [
                'action' => 'ask_user',
                'message' => 'LLM API key is not configured. Please set LLM_API_KEY in .env file.',
                'thought' => 'Configuration error',
            ];
        }

        try {
            $result = null;
            
            if ($this->llmProvider === 'openai' || empty($this->llmProvider)) {
                Log::info('Calling OpenAI API');
                $result = $this->callOpenAI($messages);
            } elseif ($this->llmProvider === 'anthropic') {
                Log::info('Calling Anthropic API');
                $result = $this->callAnthropic($messages);
            } elseif ($this->llmProvider === 'gemini') {
                Log::info('Calling Gemini API');
                $result = $this->callGemini($messages);
            } else {
                Log::info('Calling Custom Endpoint', ['endpoint' => $this->llmEndpoint]);
                $result = $this->callCustomEndpoint($messages);
            }
            
            Log::info('LLM Response Debug', [
                'provider' => $this->llmProvider,
                'result_type' => gettype($result),
                'result_keys' => is_array($result) ? array_keys($result) : 'not_array',
                'has_action' => is_array($result) && isset($result['action']),
            ]);
            
            return $result;
            
        } catch (\Exception $e) {
            Log::error('LLM call failed', [
                'provider' => $this->llmProvider,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
            return [
                'action' => 'ask_user',
                'message' => 'I encountered an error connecting to the AI service. Error: ' . $e->getMessage(),
                'thought' => 'Error occurred',
            ];
        }
    }

    /**
     * Retry LLM call with JSON extraction
     */
    private function retryWithExtraction(array $messages): ?array
    {
        // Try to call the LLM again
        $response = $this->callLLMWithMessages($messages);
        
        if (!$response) {
            return null;
        }
        
        // If the response is not properly formatted, try to extract JSON
        if (!isset($response['action'])) {
            // If the response has a message, try to extract JSON from it
            if (isset($response['message'])) {
                $jsonStart = strpos($response['message'], '{');
                $jsonEnd = strrpos($response['message'], '}');
                
                if ($jsonStart !== false && $jsonEnd !== false && $jsonEnd > $jsonStart) {
                    $jsonStr = substr($response['message'], $jsonStart, $jsonEnd - $jsonStart + 1);
                    $extracted = json_decode($jsonStr, true);
                    
                    if ($extracted && isset($extracted['action'])) {
                        return $extracted;
                    }
                }
            }
        }
        
        return $response;
    }
    
    /**
     * Initialize research progress tracking
     */
    private function initializeResearchProgress(string $sessionId, string $userMessage): void
    {
        $plan = $this->planService->getOrCreatePlan($sessionId);
        
        // Extract company name if not set
        if (empty($plan->company_name)) {
            $this->extractAndSetCompanyName($sessionId, $userMessage);
            $plan = $this->planService->getPlan($sessionId);
        }
        
        // Initialize research status for all parameters if not already done
        $researchStatus = $plan->research_status ?? [];
        if (empty($researchStatus)) {
            $allQueries = $this->planService->getComprehensiveSearchQueries($plan->company_name ?? 'Unknown');
            foreach ($allQueries as $category => $queries) {
                $this->planService->updateResearchStatus($sessionId, $category, 'pending', []);
            }
        }
    }
    
    /**
     * Get current research phase based on progress
     */
    private function getCurrentResearchPhase(array $progress): string
    {
        $percentage = $progress['percentage'] ?? 0;
        
        if ($percentage < 20) {
            return 'Company Basics Research';
        } elseif ($percentage < 40) {
            return 'Financial & Product Research';
        } elseif ($percentage < 60) {
            return 'Leadership & Market Analysis';
        } elseif ($percentage < 80) {
            return 'Recent Intelligence & Pain Points';
        } elseif ($percentage < 100) {
            return 'Strategic Assessment & Opportunities';
        } else {
            return 'Account Plan Generation';
        }
    }
    
    /**
     * Get next research actions to provide transparency
     */
    private function getNextResearchActions(string $sessionId): array
    {
        $plan = $this->planService->getPlan($sessionId);
        if (!$plan) {
            return ['Start company research'];
        }
        
        $researchStatus = $plan->research_status ?? [];
        $pendingActions = [];
        
        foreach ($researchStatus as $parameter => $status) {
            if ($status['status'] === 'pending' && count($pendingActions) < 3) {
                $pendingActions[] = ucfirst(str_replace('_', ' ', $parameter)) . ' research';
            }
        }
        
        if (empty($pendingActions)) {
            return ['Generate final account plan'];
        }
        
        return $pendingActions;
    }
    
    /**
     * Check if there are recent search results in messages
     */
    private function hasRecentSearchResults(array $messages): bool
    {
        foreach (array_reverse($messages) as $message) {
            if ($message['role'] === 'tool') {
                $content = json_decode($message['content'], true);
                if ($content && $content['action'] === 'search' && !empty($content['results'])) {
                    return true;
                }
            }
        }
        return false;
    }
    
    /**
     * Auto-process search results when LLM fails
     */
    private function autoProcessSearchResults(string $sessionId, array $messages): array
    {
        $responses = [];
        
        // Find the most recent search results
        foreach (array_reverse($messages) as $message) {
            if ($message['role'] === 'tool') {
                $content = json_decode($message['content'], true);
                if ($content && $content['action'] === 'search' && !empty($content['results'])) {
                    // Extract basic information from search results
                    $results = $content['results'];
                    $query = $content['query'];
                    
                    // Try to extract company information from search results
                    $extractedInfo = $this->extractCompanyInfoFromResults($results, $query);
                    
                    if (!empty($extractedInfo)) {
                        foreach ($extractedInfo as $parameter => $data) {
                            $this->planService->updateSection($sessionId, $parameter, $data['content'], $data['sources']);
                            $this->planService->updateResearchStatus($sessionId, $parameter, 'completed', $data['sources']);
                            
                            $responses[] = [
                                'type' => 'parameter_updated',
                                'parameter' => $parameter,
                                'content' => $data['content'],
                                'evidence_count' => count($data['sources']),
                                'timestamp' => date('H:i:s'),
                            ];
                        }
                        
                        $responses[] = [
                            'type' => 'message',
                            'content' => 'I extracted information from the search results and updated the account plan.',
                        ];
                    }
                    break;
                }
            }
        }
        
        return $responses;
    }
    
    /**
     * Extract company information from search results
     */
    private function extractCompanyInfoFromResults(array $results, string $query): array
    {
        $extractedInfo = [];
        
        // Simple extraction based on search query and results
        if (stripos($query, 'overview') !== false || stripos($query, 'description') !== false) {
            $description = '';
            $sources = [];
            
            foreach (array_slice($results, 0, 3) as $result) { // Use first 3 results
                if (isset($result['snippet'])) {
                    $description .= $result['snippet'] . ' ';
                    if (isset($result['link'])) {
                        $sources[] = $result['link'];
                    }
                }
            }
            
            if (!empty($description)) {
                $extractedInfo['description'] = [
                    'content' => trim($description),
                    'sources' => $sources
                ];
            }
        }
        
        // Look for financial information
        if (stripos($query, 'revenue') !== false || stripos($query, 'funding') !== false) {
            $financial = '';
            $sources = [];
            
            foreach ($results as $result) {
                if (isset($result['snippet'])) {
                    $snippet = $result['snippet'];
                    if (preg_match('/\$[\d,]+[MBK]?|\d+\s*(million|billion|thousand)/i', $snippet)) {
                        $financial .= $snippet . ' ';
                        if (isset($result['link'])) {
                            $sources[] = $result['link'];
                        }
                    }
                }
            }
            
            if (!empty($financial)) {
                $extractedInfo['revenue'] = [
                    'content' => trim($financial),
                    'sources' => $sources
                ];
            }
        }
        
        return $extractedInfo;
    }
    
    // ===============================
    // STEP-BY-STEP WORKFLOW METHODS
    // ===============================
    
    private function getSessionState(string $sessionId): array
    {
        return Cache::get("agent_state_{$sessionId}", [
            'step_mode' => false,
            'current_step' => null,
            'completed_steps' => [],
            'pending_conflicts' => [],
            'company_name' => null,
            'step_data' => []
        ]);
    }
    
    private function saveSessionState(string $sessionId, array $state): void
    {
        Cache::put("agent_state_{$sessionId}", $state, now()->addHours(2));
    }
    
    private function isResearchRequest(string $message): bool
    {
        $researchKeywords = ['research', 'analyze', 'account plan', 'company', 'investigate', 'explore'];
        $lowerMessage = strtolower($message);
        
        foreach ($researchKeywords as $keyword) {
            if (str_contains($lowerMessage, $keyword)) {
                return true;
            }
        }
        
        return false;
    }
    
    private function startStepByStepResearch(string $sessionId, string $userMessage): array
    {
        // Extract company name
        $companyName = $this->extractCompanyName($userMessage);
        
        if (!$companyName) {
            return [[
                'type' => 'ask_user',
                'content' => 'Which company would you like me to research?',
                'buttons' => []
            ]];
        }
        
        // Initialize state
        $state = [
            'step_mode' => true,
            'current_step' => 'company_basics',
            'completed_steps' => [],
            'pending_conflicts' => [],
            'company_name' => $companyName,
            'step_data' => []
        ];
        $this->saveSessionState($sessionId, $state);
        
        Log::info('Starting research for company', ['company' => $companyName]);
        
        // Start first step
        $stepResponses = $this->performStep($sessionId, 'company_basics', $state);
        
        Log::info('Step responses', ['count' => count($stepResponses), 'responses' => $stepResponses]);
        
        return [[
            'type' => 'message',
            'content' => "Great! I'll research {$companyName} step by step. Let's start with the basics."
        ], [
            'type' => 'progress',
            'message' => 'Step 1/7: Researching company basics...'
        ], ...$stepResponses];
    }
    
    private function processStepResponse(string $sessionId, string $userMessage, array $state): array
    {
        $lowerMessage = strtolower(trim($userMessage));
        
        // Handle button responses
        if (in_array($lowerMessage, ['yes', 'no', 'next step', 'deep research'])) {
            return $this->handleStepDecision($sessionId, $lowerMessage, $state);
        }
        
        // Handle conflict resolution
        if (isset($state['pending_conflicts']) && !empty($state['pending_conflicts'])) {
            return $this->handleConflictResolution($sessionId, $userMessage, $state);
        }
        
        // Default: treat as clarification or additional input
        return [[
            'type' => 'message',
            'content' => 'I need a clear response. Please use the buttons provided or clarify your request.'
        ]];
    }
    
    private function performStep(string $sessionId, string $stepName, array $state): array
    {
        $stepConfig = $this->getStepConfig($stepName);
        
        // Show progress
        $responses = [];
        
        // Perform search if needed
        if ($stepConfig['use_search']) {
            $searchQuery = str_replace('{company}', $state['company_name'], $stepConfig['search_query']);
            $searchResults = $this->researchService->search($searchQuery);
            
            // Extract and format data
            $data = $this->extractStepData($searchResults, $stepName);
            $state['step_data'][$stepName] = $data;
        } else {
            // Use Gemini for analysis
            $prompt = str_replace('{company}', $state['company_name'], $stepConfig['prompt']);
            $context = $this->buildContextForStep($state);
            $data = $this->analyzeWithGemini($prompt, $context);
            $state['step_data'][$stepName] = $data;
        }
        
        // Check for conflicts
        $conflicts = $this->detectConflicts($state['step_data'][$stepName], $stepName, $state);
        
        if (!empty($conflicts)) {
            $state['pending_conflicts'] = $conflicts;
            $this->saveSessionState($sessionId, $state);
            
            return [[
                'type' => 'ask_user',
                'content' => "I found some conflicting information:\n\n" . $this->formatConflicts($conflicts),
                'buttons' => $this->createConflictButtons($conflicts)
            ]];
        }
        
        // Update plan
        $this->updatePlanSection($sessionId, $stepName, $state['step_data'][$stepName]);
        
        // Ask for confirmation
        $responses[] = [
            'type' => 'update_plan',
            'section' => $stepConfig['section'],
            'content' => $this->formatStepData($state['step_data'][$stepName], $stepName)
        ];
        
        $responses[] = [
            'type' => 'ask_user',
            'content' => $stepConfig['confirmation_message'],
            'buttons' => [
                ['text' => 'Yes, Continue', 'value' => 'yes'],
                ['text' => 'Deep Research', 'value' => 'deep research'],
                ['text' => 'Next Step', 'value' => 'next step']
            ]
        ];
        
        $this->saveSessionState($sessionId, $state);
        
        return $responses;
    }
    
    private function handleStepDecision(string $sessionId, string $decision, array $state): array
    {
        $currentStep = $state['current_step'];
        
        if ($decision === 'yes') {
            // Mark step complete and move to next
            $state['completed_steps'][] = $currentStep;
            $nextStep = $this->getNextStep($currentStep);
            
            if (!$nextStep) {
                // All steps complete
                $state['step_mode'] = false;
                $this->saveSessionState($sessionId, $state);
                
                return [[
                    'type' => 'finish',
                    'content' => "Research complete! I've generated a comprehensive account plan for {$state['company_name']}."
                ]];
            }
            
            $state['current_step'] = $nextStep;
            $this->saveSessionState($sessionId, $state);
            
            $stepNum = count($state['completed_steps']) + 1;
            return [[
                'type' => 'progress',
                'message' => "Step {$stepNum}/7: " . $this->getStepConfig($nextStep)['progress_message']
            ], ...$this->performStep($sessionId, $nextStep, $state)];
            
        } elseif ($decision === 'deep research') {
            // Perform deeper analysis
            return [[
                'type' => 'progress',
                'message' => 'Performing deep research...'
            ], ...$this->performDeepResearch($sessionId, $currentStep, $state)];
            
        } elseif ($decision === 'next step') {
            // Skip to next step
            $state['completed_steps'][] = $currentStep;
            $nextStep = $this->getNextStep($currentStep);
            
            if (!$nextStep) {
                $state['step_mode'] = false;
                $this->saveSessionState($sessionId, $state);
                
                return [[
                    'type' => 'finish',
                    'content' => "Research complete!"
                ]];
            }
            
            $state['current_step'] = $nextStep;
            $this->saveSessionState($sessionId, $state);
            
            $stepNum = count($state['completed_steps']) + 1;
            return [[
                'type' => 'progress',
                'message' => "Step {$stepNum}/7: " . $this->getStepConfig($nextStep)['progress_message']
            ], ...$this->performStep($sessionId, $nextStep, $state)];
        }
        
        return [[
            'type' => 'message',
            'content' => 'Please choose an option using the buttons.'
        ]];
    }
    
    private function getStepConfig(string $stepName): array
    {
        $steps = [
            'company_basics' => [
                'section' => 'company_overview',
                'use_search' => true,
                'search_query' => '{company} company overview headquarters employees',
                'prompt' => '',
                'progress_message' => 'Researching company basics...',
                'confirmation_message' => 'Here\'s what I found about the company basics. Should I continue to financial research?'
            ],
            'financial' => [
                'section' => 'financial_overview',
                'use_search' => true,
                'search_query' => '{company} revenue funding valuation financial performance',
                'prompt' => '',
                'progress_message' => 'Analyzing financial data...',
                'confirmation_message' => 'I\'ve gathered the financial information. Ready to explore products and technology?'
            ],
            'products_tech' => [
                'section' => 'products_services',
                'use_search' => true,
                'search_query' => '{company} products services technology stack',
                'prompt' => '',
                'progress_message' => 'Researching products and technology...',
                'confirmation_message' => 'Products and tech stack mapped. Should I analyze competitors next?'
            ],
            'competitors' => [
                'section' => 'competitive_landscape',
                'use_search' => true,
                'search_query' => '{company} competitors alternatives comparison',
                'prompt' => '',
                'progress_message' => 'Identifying competitors...',
                'confirmation_message' => 'Competitive landscape analyzed. Ready to identify pain points?'
            ],
            'pain_points' => [
                'section' => 'pain_points',
                'use_search' => false,
                'search_query' => '',
                'prompt' => 'Based on the research data for {company}, identify key pain points, challenges, and opportunities.',
                'progress_message' => 'Analyzing pain points...',
                'confirmation_message' => 'Pain points identified. Should I generate recommendations?'
            ],
            'recommendations' => [
                'section' => 'recommendations',
                'use_search' => false,
                'search_query' => '',
                'prompt' => 'Generate strategic recommendations for engaging {company} based on all research data.',
                'progress_message' => 'Generating recommendations...',
                'confirmation_message' => 'Recommendations ready. Create the final account plan?'
            ],
            'final_plan' => [
                'section' => 'executive_summary',
                'use_search' => false,
                'search_query' => '',
                'prompt' => 'Create executive summary and final account plan for {company}.',
                'progress_message' => 'Creating final account plan...',
                'confirmation_message' => 'Final account plan complete!'
            ]
        ];
        
        return $steps[$stepName] ?? [];
    }
    
    private function getNextStep(string $currentStep): ?string
    {
        $sequence = ['company_basics', 'financial', 'products_tech', 'competitors', 'pain_points', 'recommendations', 'final_plan'];
        $currentIndex = array_search($currentStep, $sequence);
        
        if ($currentIndex === false || $currentIndex >= count($sequence) - 1) {
            return null;
        }
        
        return $sequence[$currentIndex + 1];
    }
    
    private function extractStepData(array $searchResults, string $stepName): array
    {
        // Use existing extractCompanyInfoFromResults method
        $query = match($stepName) {
            'company_basics' => 'company overview headquarters employees',
            'financial' => 'revenue funding valuation',
            'products_tech' => 'products services technology',
            'competitors' => 'competitors alternatives',
            default => 'information'
        };
        
        return $this->extractCompanyInfoFromResults($searchResults, $query);
    }
    
    private function buildContextForStep(array $state): string
    {
        $context = "Company: {$state['company_name']}\n\n";
        $context .= "Previous Research:\n";
        
        foreach ($state['step_data'] as $step => $data) {
            $context .= "\n" . ucfirst(str_replace('_', ' ', $step)) . ":\n";
            $context .= json_encode($data, JSON_PRETTY_PRINT) . "\n";
        }
        
        return $context;
    }
    
    private function analyzeWithGemini(string $prompt, string $context): array
    {
        $fullPrompt = "{$prompt}\n\nContext:\n{$context}";
        
        $response = Http::timeout(60)->post($this->llmEndpoint, [
            'contents' => [
                [
                    'parts' => [
                        ['text' => $fullPrompt]
                    ]
                ]
            ],
            'generationConfig' => [
                'temperature' => 0.7,
                'maxOutputTokens' => 2048
            ]
        ]);
        
        if ($response->successful()) {
            $result = $response->json();
            $text = $result['candidates'][0]['content']['parts'][0]['text'] ?? '';
            
            return [
                'content' => $text,
                'sources' => ['AI Analysis']
            ];
        }
        
        return [
            'content' => 'Analysis unavailable',
            'sources' => []
        ];
    }
    
    private function detectConflicts(array $data, string $stepName, array $state): array
    {
        $conflicts = [];
        
        // Compare numeric values (3% threshold)
        foreach ($data as $key => $value) {
            // Skip arrays and objects
            if (is_array($value) || is_object($value)) {
                // If it's an array with 'content', extract that
                if (is_array($value) && isset($value['content'])) {
                    $value = $value['content'];
                } else {
                    continue;
                }
            }
            
            // Convert to string for pattern matching
            $valueStr = (string)$value;
            
            if (is_numeric($valueStr) || preg_match('/\$?[\d,]+\.?\d*[MBK]?/', $valueStr)) {
                // Extract numeric value
                $currentValue = $this->parseNumericValue($valueStr);
                
                // Check previous steps for similar keys
                foreach ($state['step_data'] as $prevStep => $prevData) {
                    if ($prevStep !== $stepName && isset($prevData[$key])) {
                        $prevValueRaw = $prevData[$key];
                        
                        // Handle array values in previous data
                        if (is_array($prevValueRaw) && isset($prevValueRaw['content'])) {
                            $prevValueRaw = $prevValueRaw['content'];
                        }
                        
                        $prevValue = $this->parseNumericValue($prevValueRaw);
                        
                        if ($prevValue > 0 && abs($currentValue - $prevValue) / $prevValue > 0.03) {
                            $conflicts[] = [
                                'field' => $key,
                                'current' => $value,
                                'previous' => $prevData[$key],
                                'step' => $prevStep
                            ];
                        }
                    }
                }
            }
        }
        
        return $conflicts;
    }
    
    private function parseNumericValue($value): float
    {
        // Handle null or array values
        if (is_null($value) || is_array($value) || is_object($value)) {
            return 0;
        }
        
        // Convert to string
        $value = (string)$value;
        
        if (is_numeric($value)) {
            return (float)$value;
        }
        
        // Extract numeric value from string
        $value = str_replace(['$', ','], '', $value);
        
        if (preg_match('/([\d.]+)\s*([MBK])?/i', $value, $matches)) {
            $num = (float)$matches[1];
            $multiplier = 1;
            
            if (isset($matches[2])) {
                $multiplier = match(strtoupper($matches[2])) {
                    'K' => 1000,
                    'M' => 1000000,
                    'B' => 1000000000,
                    default => 1
                };
            }
            
            return $num * $multiplier;
        }
        
        return 0;
    }
    
    private function formatConflicts(array $conflicts): string
    {
        $formatted = '';
        
        foreach ($conflicts as $i => $conflict) {
            $formatted .= ($i + 1) . ". {$conflict['field']}:\n";
            $formatted .= "   Current: {$conflict['current']}\n";
            $formatted .= "   Previous ({$conflict['step']}): {$conflict['previous']}\n\n";
        }
        
        $formatted .= "Which values should I use?";
        
        return $formatted;
    }
    
    private function createConflictButtons(array $conflicts): array
    {
        $buttons = [];
        
        foreach ($conflicts as $i => $conflict) {
            $buttons[] = [
                'text' => "Use Current ({$conflict['current']})",
                'value' => "conflict_{$i}_current"
            ];
            $buttons[] = [
                'text' => "Use Previous ({$conflict['previous']})",
                'value' => "conflict_{$i}_previous"
            ];
        }
        
        return $buttons;
    }
    
    private function handleConflictResolution(string $sessionId, string $userMessage, array $state): array
    {
        // Parse conflict decision
        if (preg_match('/conflict_(\d+)_(current|previous)/', $userMessage, $matches)) {
            $conflictIndex = (int)$matches[1];
            $choice = $matches[2];
            
            $conflict = $state['pending_conflicts'][$conflictIndex] ?? null;
            
            if ($conflict) {
                // Update data with chosen value
                $chosenValue = $choice === 'current' ? $conflict['current'] : $conflict['previous'];
                $state['step_data'][$state['current_step']][$conflict['field']] = $chosenValue;
                
                // Remove resolved conflict
                array_splice($state['pending_conflicts'], $conflictIndex, 1);
                
                // If more conflicts remain, ask about next
                if (!empty($state['pending_conflicts'])) {
                    $this->saveSessionState($sessionId, $state);
                    
                    return [[
                        'type' => 'ask_user',
                        'content' => $this->formatConflicts($state['pending_conflicts']),
                        'buttons' => $this->createConflictButtons($state['pending_conflicts'])
                    ]];
                }
                
                // All conflicts resolved, continue with step
                $this->saveSessionState($sessionId, $state);
                $this->updatePlanSection($sessionId, $state['current_step'], $state['step_data'][$state['current_step']]);
                
                $stepConfig = $this->getStepConfig($state['current_step']);
                
                return [[
                    'type' => 'update_plan',
                    'section' => $stepConfig['section'],
                    'content' => $this->formatStepData($state['step_data'][$state['current_step']], $state['current_step'])
                ], [
                    'type' => 'ask_user',
                    'content' => $stepConfig['confirmation_message'],
                    'buttons' => [
                        ['text' => 'Yes, Continue', 'value' => 'yes'],
                        ['text' => 'Deep Research', 'value' => 'deep research'],
                        ['text' => 'Next Step', 'value' => 'next step']
                    ]
                ]];
            }
        }
        
        return [[
            'type' => 'message',
            'content' => 'Please select a resolution using the buttons.'
        ]];
    }
    
    private function updatePlanSection(string $sessionId, string $stepName, array $data): void
    {
        $stepConfig = $this->getStepConfig($stepName);
        $section = $stepConfig['section'];
        $content = $this->formatStepData($data, $stepName);
        
        $this->planService->updateSection($sessionId, $section, $content);
    }
    
    private function formatStepData(array $data, string $stepName): string
    {
        $formatted = '';
        
        foreach ($data as $key => $value) {
            if (is_array($value)) {
                if (isset($value['content'])) {
                    $formatted .= ucfirst(str_replace('_', ' ', $key)) . ":\n";
                    $formatted .= $value['content'] . "\n\n";
                    
                    if (!empty($value['sources'])) {
                        $formatted .= "Sources: " . implode(', ', array_slice($value['sources'], 0, 3)) . "\n\n";
                    }
                }
            } else {
                $formatted .= ucfirst(str_replace('_', ' ', $key)) . ": " . $value . "\n";
            }
        }
        
        return $formatted;
    }
    
    private function performDeepResearch(string $sessionId, string $stepName, array $state): array
    {
        // Perform additional searches with more specific queries
        $stepConfig = $this->getStepConfig($stepName);
        $deepQuery = $stepConfig['search_query'] . ' detailed analysis trends';
        $deepQuery = str_replace('{company}', $state['company_name'], $deepQuery);
        
        $searchResults = $this->researchService->search($deepQuery);
        $deepData = $this->extractStepData($searchResults, $stepName);
        
        // Merge with existing data
        $state['step_data'][$stepName] = array_merge(
            $state['step_data'][$stepName] ?? [],
            $deepData
        );
        
        $this->saveSessionState($sessionId, $state);
        $this->updatePlanSection($sessionId, $stepName, $state['step_data'][$stepName]);
        
        return [[
            'type' => 'update_plan',
            'section' => $stepConfig['section'],
            'content' => $this->formatStepData($state['step_data'][$stepName], $stepName)
        ], [
            'type' => 'ask_user',
            'content' => 'Deep research complete! ' . $stepConfig['confirmation_message'],
            'buttons' => [
                ['text' => 'Yes, Continue', 'value' => 'yes'],
                ['text' => 'Next Step', 'value' => 'next step']
            ]
        ]];
    }
    
    private function extractCompanyName(string $message): ?string
    {
        // Try to extract company name from message
        $patterns = [
            '/research\s+([A-Z][a-zA-Z0-9\s&]+?)(?:\s+company|\s+corp|\.|$)/i',
            '/analyze\s+([A-Z][a-zA-Z0-9\s&]+?)(?:\s+company|\s+corp|\.|$)/i',
            '/account plan for\s+([A-Z][a-zA-Z0-9\s&]+?)(?:\s+company|\s+corp|\.|$)/i',
            '/([A-Z][a-zA-Z0-9\s&]{2,})(?:\s+company|\s+corp)?/i'
        ];
        
        foreach ($patterns as $pattern) {
            if (preg_match($pattern, $message, $matches)) {
                return trim($matches[1]);
            }
        }
        
        return null;
    }
}
