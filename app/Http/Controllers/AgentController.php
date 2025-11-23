<?php

namespace App\Http\Controllers;

use App\Services\AgentService;
use App\Services\PlanService;
use App\Services\MemoryService;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Cache;

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
            set_time_limit(120); // 2 minutes for comprehensive research
            
            $result = $this->agentService->processMessage($sessionId, $userMessage);

            // Handle array responses (step-by-step mode)
            if (is_array($result) && isset($result[0]['type'])) {
                // Check if it's a step workflow session
                $state = Cache::get("agent_state_{$sessionId}");
                $isStepWorkflow = $state && isset($state['mode']) && $state['mode'] === 'step_workflow';
                
                // Format responses for frontend
                $formattedResponses = $this->formatResponses($result);
                
                return response()->json([
                    'success' => true,
                    'session_id' => $sessionId,
                    'responses' => $formattedResponses,
                    'plan' => $isStepWorkflow 
                        ? $this->planService->getStepSections($sessionId)
                        : $this->planService->getPlanSections($sessionId),
                    'is_step_workflow' => $isStepWorkflow,
                ]);
            }

            // Handle legacy response format
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
     * Format responses for frontend consumption
     */
    private function formatResponses(array $responses): array
    {
        $formatted = [];
        
        foreach ($responses as $response) {
            $type = $response['type'] ?? 'message';
            
            switch ($type) {
                case 'progress':
                    $formatted[] = [
                        'type' => 'progress',
                        'message' => $response['message'] ?? 'Processing...'
                    ];
                    break;
                    
                case 'ask_user':
                    $formatted[] = [
                        'type' => 'ask_user',
                        'content' => $response['content'] ?? '',
                        'buttons' => $response['buttons'] ?? []
                    ];
                    break;
                    
                case 'update_plan':
                    $formatted[] = [
                        'type' => 'update_plan',
                        'section' => $response['section'] ?? '',
                        'content' => $response['content'] ?? ''
                    ];
                    break;
                    
                case 'message':
                    $formatted[] = [
                        'type' => 'message',
                        'content' => $response['content'] ?? ''
                    ];
                    break;
                    
                case 'finish':
                    $formatted[] = [
                        'type' => 'finish',
                        'content' => $response['content'] ?? 'Research complete!'
                    ];
                    break;
                    
                default:
                    $formatted[] = $response;
            }
        }
        
        return $formatted;
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
        
        // Check if it's a step workflow session
        $state = Cache::get("agent_state_{$sessionId}");
        $isStepWorkflow = $state && isset($state['mode']) && $state['mode'] === 'step_workflow';
        
        // Use appropriate section getter
        $plan = $isStepWorkflow 
            ? $this->planService->getStepSections($sessionId)
            : $this->planService->getPlanSections($sessionId);

        return response()->json([
            'success' => true,
            'plan' => $plan,
            'is_step_workflow' => $isStepWorkflow,
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

