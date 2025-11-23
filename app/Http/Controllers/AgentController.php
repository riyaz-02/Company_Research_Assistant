<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class AgentController extends Controller
{
    private string $aiServiceUrl;

    public function __construct()
    {
        $this->aiServiceUrl = config('services.ai_service.url', 'http://localhost:8001');
    }

    public function message(Request $request): JsonResponse
    {
        try {
            // Increase PHP execution time for research operations  
            set_time_limit(120);
            
            $sessionId = $request->input('session_id') ?: Str::uuid()->toString();
            $userMessage = $request->input('message', '');

            if (empty($userMessage)) {
                return response()->json([
                    'success' => false,
                    'error' => 'Message is required'
                ], 400);
            }

            Log::info('Processing user message', [
                'session_id' => $sessionId,
                'message' => $userMessage
            ]);

            // Call Python AI service with longer timeout
            $response = Http::timeout(120)->post($this->aiServiceUrl . '/research/message', [
                'session_id' => $sessionId,
                'user_message' => $userMessage
            ]);

            if (!$response->successful()) {
                throw new \Exception('AI service error: ' . $response->body());
            }

            $data = $response->json();

            return response()->json([
                'success' => $data['success'] ?? true,
                'session_id' => $sessionId,
                'response' => $data['response'] ?? '',
                'messages' => $data['messages'] ?? null,
                'data' => $data['data'] ?? null,
                'chart' => $data['chart'] ?? null,
                'step' => $data['step'] ?? null,
                'company' => $data['company'] ?? null,
                'prompt_user' => $data['prompt_user'] ?? false,
                'prompt_message' => $data['prompt_message'] ?? null
            ]);

        } catch (\Exception $e) {
            Log::error('Message processing error', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json([
                'success' => false,
                'error' => 'An error occurred processing your message'
            ], 500);
        }
    }
}
