<?php

namespace App\Http\Controllers;

use App\Services\AgentService;
use App\Services\PlanService;
use App\Services\MemoryService;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Str;

class AgentController extends Controller
{
    private AgentService $agentService;
    private PlanService $planService;
    private MemoryService $memoryService;

    public function __construct(
        AgentService $agentService,
        PlanService $planService,
        MemoryService $memoryService
    ) {
        $this->agentService = $agentService;
        $this->planService = $planService;
        $this->memoryService = $memoryService;
    }

    /**
     * Handle incoming message from user
     */
    public function message(Request $request): JsonResponse
    {
        $request->validate([
            'message' => 'required|string|max:5000',
            'session_id' => 'nullable|string',
        ]);

        $userMessage = $request->input('message');
        $sessionId = $request->input('session_id') ?? $this->generateSessionId();

        try {
            // Set a longer timeout for agent processing
            set_time_limit(60); // 60 seconds instead of default 30
            
            $result = $this->agentService->processMessage($sessionId, $userMessage);

            // If no responses were generated, provide a helpful error
            if (empty($result['responses'])) {
                return response()->json([
                    'success' => false,
                    'error' => 'No response generated. Please check your LLM API configuration.',
                    'message' => 'The AI agent could not generate a response. This might be due to API configuration issues.',
                ], 500);
            }

            return response()->json([
                'success' => true,
                'session_id' => $sessionId,
                'responses' => $result['responses'],
                'plan' => $result['plan'],
            ]);
        } catch (\Exception $e) {
            \Log::error('AgentController error: ' . $e->getMessage() . "\n" . $e->getTraceAsString());
            return response()->json([
                'success' => false,
                'error' => 'An error occurred while processing your message.',
                'message' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Get current account plan
     */
    public function getPlan(Request $request): JsonResponse
    {
        $request->validate([
            'session_id' => 'required|string',
        ]);

        $sessionId = $request->input('session_id');
        $plan = $this->planService->getPlanSections($sessionId);

        return response()->json([
            'success' => true,
            'plan' => $plan,
        ]);
    }

    /**
     * Update a specific plan section
     */
    public function updatePlanSection(Request $request): JsonResponse
    {
        $request->validate([
            'session_id' => 'required|string',
            'section' => 'required|string',
            'content' => 'required',
        ]);

        $sessionId = $request->input('session_id');
        $section = $request->input('section');
        $content = $request->input('content');

        try {
            $plan = $this->planService->updateSection($sessionId, $section, $content);

            return response()->json([
                'success' => true,
                'plan' => $this->planService->getPlanSections($sessionId),
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'error' => 'Failed to update plan section.',
                'message' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Regenerate a plan section
     */
    public function regenerateSection(Request $request): JsonResponse
    {
        $request->validate([
            'session_id' => 'required|string',
            'section' => 'required|string',
        ]);

        $sessionId = $request->input('session_id');
        $section = $request->input('section');

        try {
            // Clear the section
            $this->planService->regenerateSection($sessionId, $section);

            // Get conversation context
            $history = $this->memoryService->getRecentMessages($sessionId, 5);
            $contextMessage = "Please regenerate the '{$section}' section of the account plan based on our conversation.";

            // Process regeneration through agent
            $result = $this->agentService->processMessage($sessionId, $contextMessage);

            return response()->json([
                'success' => true,
                'plan' => $result['plan'],
                'responses' => $result['responses'],
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'error' => 'Failed to regenerate section.',
                'message' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Get conversation history
     */
    public function getHistory(Request $request): JsonResponse
    {
        $request->validate([
            'session_id' => 'required|string',
        ]);

        $sessionId = $request->input('session_id');
        $history = $this->memoryService->getHistory($sessionId);

        return response()->json([
            'success' => true,
            'history' => $history,
        ]);
    }

    /**
     * Clear conversation history
     */
    public function clearHistory(Request $request): JsonResponse
    {
        $request->validate([
            'session_id' => 'required|string',
        ]);

        $sessionId = $request->input('session_id');
        $this->memoryService->clearHistory($sessionId);

        return response()->json([
            'success' => true,
            'message' => 'History cleared successfully',
        ]);
    }

    /**
     * Generate a unique session ID
     */
    private function generateSessionId(): string
    {
        return Str::uuid()->toString();
    }
}

