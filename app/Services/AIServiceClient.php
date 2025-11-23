<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class AIServiceClient
{
    private string $baseUrl;
    private int $timeout;

    public function __construct()
    {
        $this->baseUrl = config('services.ai_service.url', 'http://localhost:8000');
        $this->timeout = config('services.ai_service.timeout', 120);
    }

    /**
     * Synthesize a research section
     */
    public function synthesizeSection(
        string $sessionId,
        string $step,
        array $searchResults,
        string $companyName
    ): array {
        return $this->makeRequest('/synthesize-section', [
            'session_id' => $sessionId,
            'step' => $step,
            'raw_search_results' => $searchResults,
            'company_name' => $companyName,
        ]);
    }

    /**
     * Detect conflicts between data
     */
    public function detectConflicts(
        string $sessionId,
        string $step,
        array $currentData,
        ?array $previousData
    ): array {
        return $this->makeRequest('/detect-conflicts', [
            'session_id' => $sessionId,
            'step' => $step,
            'current_data' => $currentData,
            'previous_data' => $previousData ?? [],
        ]);
    }

    /**
     * Process a complete research step
     */
    public function processStep(
        string $sessionId,
        string $step,
        string $companyName,
        array $searchResults,
        ?array $previousContent = null
    ): array {
        return $this->makeRequest('/process-step', [
            'session_id' => $sessionId,
            'step' => $step,
            'company_name' => $companyName,
            'search_results' => $searchResults,
            'previous_content' => $previousContent,
        ]);
    }

    /**
     * Generate final account plan
     */
    public function generateFinalPlan(
        string $sessionId,
        string $companyName,
        array $allSections
    ): array {
        return $this->makeRequest('/generate-final-plan', [
            'session_id' => $sessionId,
            'company_name' => $companyName,
            'all_sections' => $allSections,
        ]);
    }

    /**
     * Clean text
     */
    public function cleanText(string $text, ?int $maxLength = null): array
    {
        return $this->makeRequest('/clean-text', [
            'text' => $text,
            'max_length' => $maxLength,
        ]);
    }

    /**
     * Clear session cache
     */
    public function clearSessionCache(string $sessionId): array
    {
        return $this->makeRequest("/clear-cache/{$sessionId}", [], 'POST');
    }

    /**
     * Process messages directly (for agent LLM calls)
     * Accepts standard message array format and returns action/response
     */
    public function processMessages(array $messages, string $sessionId = 'default'): ?array
    {
        return $this->makeRequest('/process-messages', [
            'messages' => $messages,
            'session_id' => $sessionId,
        ]);
    }

    /**
     * Analyze data with AI (for synthesis and analysis)
     */
    public function analyzeData(string $prompt, string $context = '', string $sessionId = 'default'): string
    {
        $result = $this->makeRequest('/analyze', [
            'prompt' => $prompt,
            'context' => $context,
            'session_id' => $sessionId,
        ]);

        return $result['content'] ?? $result['text'] ?? '';
    }

    /**
     * Check health of AI service
     */
    public function healthCheck(): array
    {
        try {
            $response = Http::timeout(5)->get($this->baseUrl . '/health');
            return [
                'status' => $response->successful() ? 'healthy' : 'unhealthy',
                'data' => $response->json()
            ];
        } catch (\Exception $e) {
            return [
                'status' => 'unreachable',
                'error' => $e->getMessage()
            ];
        }
    }

    /**
     * Make HTTP request to AI service
     */
    private function makeRequest(string $endpoint, array $data = [], string $method = 'POST'): array
    {
        try {
            $url = $this->baseUrl . $endpoint;
            
            Log::info("AI Service Request: {$method} {$url}", ['data' => $data]);

            $response = Http::timeout($this->timeout)
                ->acceptJson()
                ->{strtolower($method)}($url, $data);

            if ($response->failed()) {
                Log::error("AI Service Error", [
                    'status' => $response->status(),
                    'body' => $response->body(),
                ]);

                // Return fallback response
                return [
                    'action' => 'error',
                    'error' => "AI Service returned {$response->status()}",
                    'needs_retry' => true,
                ];
            }

            $result = $response->json();
            Log::info("AI Service Response", ['result' => $result]);

            return $result;

        } catch (\Exception $e) {
            Log::error("AI Service Exception: {$e->getMessage()}");
            
            // Return fallback response
            return [
                'action' => 'error',
                'error' => $e->getMessage(),
                'needs_retry' => true,
            ];
        }
    }
}
