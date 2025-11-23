<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class AgentService
{
    private ResearchService $researchService;
    private PlanService $planService;
    private MemoryService $memoryService;
    private string $llmProvider;
    private string $llmApiKey;
    private string $llmEndpoint;
    private int $maxIterations = 5; // Reduced to avoid timeouts

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
        
        // Try to extract company name from user message
        $this->extractAndSetCompanyName($sessionId, $userMessage);

        $responses = [];
        $iteration = 0;
        $messages = [];

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

            // Validate JSON response
            if (!$llmResponse || !isset($llmResponse['action'])) {
                // Try to extract JSON and retry once
                if ($iteration === 1) {
                    $llmResponse = $this->retryWithExtraction($messages);
                }
                
                if (!$llmResponse || !isset($llmResponse['action'])) {
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
                        $results = $this->researchService->search($query);
                        
                        // Add tool message with search results
                        $messages[] = [
                            'role' => 'tool',
                            'content' => json_encode([
                                'action' => 'search',
                                'query' => $query,
                                'results' => $results,
                                'count' => count($results),
                            ]),
                        ];
                        
                        // Store in memory
                        $this->memoryService->addMessage($sessionId, 'tool', json_encode(['action' => 'search', 'query' => $query, 'results_count' => count($results)]));
                        
                        // Show progress
                        $responses[] = [
                            'type' => 'progress',
                            'content' => "Searching for: {$query}",
                        ];
                    }
                    break;

                case 'update_plan':
                    $section = $llmResponse['section'] ?? '';
                    $content = $llmResponse['content'] ?? '';
                    $evidence = $llmResponse['evidence'] ?? [];
                    
                    if ($section && $content !== null) {
                        $updated = $this->planService->updateSection($sessionId, $section, $content, $evidence);
                        
                        // Add tool message
                        $messages[] = [
                            'role' => 'tool',
                            'content' => json_encode([
                                'action' => 'update_plan',
                                'section' => $section,
                                'status' => 'updated',
                            ]),
                        ];
                        
                        // Store in memory
                        $this->memoryService->addMessage($sessionId, 'tool', json_encode(['action' => 'update_plan', 'section' => $section]));
                        
                        // Update plan in response
                        $responses[] = [
                            'type' => 'plan_update',
                            'section' => $section,
                            'content' => $content,
                        ];
                    }
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
                            'content' => $question,
                            'action' => 'ask_user',
                        ];
                        
                        // Break loop to wait for user response
                        break;
                    }
                    break;

                case 'finish':
                    $content = $llmResponse['content'] ?? $llmResponse['message'] ?? 'Research completed.';
                    
                    // Store final message
                    $this->memoryService->addMessage($sessionId, 'assistant', $content, ['action' => 'finish']);
                    
                    // Return final message
                    $responses[] = [
                        'type' => 'message',
                        'content' => $content,
                        'action' => 'finish',
                    ];
                    
                    // Break loop
                    break 2;

                default:
                    Log::warning('Unknown action: ' . $action);
                    break;
            }

            // Add a small delay to avoid rate limiting
            if ($iteration < $this->maxIterations && $action !== 'finish' && $action !== 'ask_user') {
                usleep(500000); // 0.5 second delay
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
        return "You are ResearchAgent, an autonomous company research assistant.

CRITICAL RULES:
- ALWAYS respond with a valid JSON object. NO plain text responses unless action is 'finish'.
- NEVER repeat the same sentence multiple times.
- NEVER start every message with the same phrase.
- Vary your tone slightly but remain professional.
- Keep responses concise and professional.

REQUIRED JSON FORMAT:
{
    \"action\": \"search\" | \"update_plan\" | \"ask_user\" | \"finish\",
    \"query\": \"\",  // Required for 'search' action
    \"section\": \"\",  // Required for 'update_plan' action (overview, products, competitors, opportunities, recommendations, market_position, financial_summary, key_contacts)
    \"content\": \"\",  // Required for 'update_plan' action
    \"evidence\": [],  // Optional: array of evidence sources
    \"question\": \"\"  // Required for 'ask_user' action
}

ALLOWED ACTIONS:
1. \"search\": Perform web search using ResearchService
   - Must include \"query\" field with search query
   - Example: {\"action\": \"search\", \"query\": \"EightFold AI company overview\"}

2. \"update_plan\": Update a specific section in the account plan
   - Must include \"section\" (one of: overview, products, competitors, opportunities, recommendations, market_position, financial_summary, key_contacts)
   - Must include \"content\" (string or array depending on section)
   - Optional \"evidence\" array
   - Example: {\"action\": \"update_plan\", \"section\": \"overview\", \"content\": \"EightFold AI is a talent intelligence platform...\"}

3. \"ask_user\": Ask a clarifying question and pause for user input
   - Must include \"question\" field
   - Use this for: progress updates, conflict detection, clarification needs
   - Example: {\"action\": \"ask_user\", \"question\": \"I found conflicting information about the company size. One source says 500 employees, another says 1000. Which should I use?\"}

4. \"finish\": Return final summary message for user
   - Include final message in \"content\" field
   - Example: {\"action\": \"finish\", \"content\": \"I've completed the account plan for EightFold AI. All sections have been updated with the latest information.\"}

YOUR RESPONSIBILITIES:
1. Research companies using web search (use 'search' action)
2. Provide progress updates (use 'ask_user' action with status message in question field)
3. Detect conflicts in information and ask user which option to prioritize (use 'ask_user' action)
4. Generate and update account plan sections (use 'update_plan' action)
5. Keep responses concise and professional

CONFLICT DETECTION:
- If multiple search results provide conflicting data, identify the conflict
- Ask the user which version to trust using action='ask_user'
- DO NOT hallucinate or guess - always ask when uncertain

IMPORTANT:
- You will receive tool outputs from previous actions in the conversation
- Use these tool outputs as evidence for your next actions
- Build up the account plan incrementally based on search results
- Only use 'finish' when you have completed the research and updated all relevant plan sections";
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
        
        $response = Http::withHeaders([
            'Content-Type' => 'application/json',
        ])->post($endpoint, $payload);

        // If v1beta fails with 404 or 400, try v1 API (without systemInstruction and responseMimeType)
        if (!$response->successful() && ($response->status() === 404 || $response->status() === 400)) {
            Log::info('v1beta API failed, trying v1 API');
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
            
            // Extract text from Gemini response
            $text = '';
            if (isset($data['candidates'][0]['content']['parts'][0]['text'])) {
                $text = $data['candidates'][0]['content']['parts'][0]['text'];
            }
            
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
        if (!$this->llmApiKey) {
            Log::error('LLM API key not configured');
            return [
                'action' => 'ask_user',
                'message' => 'LLM API key is not configured. Please set LLM_API_KEY in .env file.',
                'thought' => 'Configuration error',
            ];
        }

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
}

