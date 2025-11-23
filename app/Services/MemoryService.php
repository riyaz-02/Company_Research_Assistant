<?php

namespace App\Services;

use App\Models\Conversation;
use Illuminate\Support\Collection;

class MemoryService
{
    /**
     * Add a message to conversation history
     */
    public function addMessage(string $sessionId, string $role, string $content, ?array $metadata = null): void
    {
        $conversation = Conversation::firstOrCreate(
            ['session_id' => $sessionId],
            ['messages' => [], 'metadata' => []]
        );

        $message = [
            'role' => $role, // 'user' or 'assistant'
            'content' => $content,
            'timestamp' => now()->toIso8601String(),
        ];

        if ($metadata) {
            $message['metadata'] = $metadata;
        }

        $messages = $conversation->messages ?? [];
        $messages[] = $message;
        $conversation->messages = $messages;
        $conversation->save();
    }

    /**
     * Get conversation history
     */
    public function getHistory(string $sessionId, int $limit = 50): array
    {
        $conversation = Conversation::where('session_id', $sessionId)->first();

        if (!$conversation || empty($conversation->messages)) {
            return [];
        }

        $messages = $conversation->messages;
        
        // Return last N messages
        return array_slice($messages, -$limit);
    }

    /**
     * Get formatted history for LLM prompt
     */
    public function getFormattedHistory(string $sessionId, int $limit = 20): string
    {
        $messages = $this->getHistory($sessionId, $limit);
        
        $formatted = [];
        foreach ($messages as $message) {
            $role = $message['role'] ?? 'user';
            $content = $message['content'] ?? '';
            $formatted[] = "{$role}: {$content}";
        }

        return implode("\n", $formatted);
    }

    /**
     * Clear conversation history
     */
    public function clearHistory(string $sessionId): void
    {
        Conversation::where('session_id', $sessionId)->delete();
    }

    /**
     * Get recent messages for context (last N messages)
     */
    public function getRecentMessages(string $sessionId, int $count = 10): array
    {
        return $this->getHistory($sessionId, $count);
    }
}

