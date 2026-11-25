<?php

declare(strict_types=1);

namespace App\Services\Ai;

use App\Contracts\Ai\ChatClient;
use App\Contracts\Ai\ChatCompletion;
use App\Contracts\Ai\ToolCall;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use RuntimeException;

final class OpenAiCompatibleChatClient implements ChatClient
{
    public function complete(array $messages, array $tools): ChatCompletion
    {
        $key = (string) config('support.ai.key');
        if ($key === '') {
            throw new AiDisabledException('The assistant is not configured.');
        }

        $payload = [
            'model' => config('support.ai.model'),
            'messages' => $messages,
            'temperature' => 0.1,
        ];
        if ($tools !== []) {
            $payload['tools'] = $tools;
            $payload['tool_choice'] = 'auto';
        }

        $response = Http::timeout(30)
            ->withToken($key)
            ->acceptJson()
            ->post(rtrim((string) config('support.ai.base_url'), '/').'/chat/completions', $payload);

        if (! $response->successful()) {
            Log::warning('AI provider request failed', ['status' => $response->status()]);
            throw new RuntimeException('The assistant could not be reached. Try again in a moment.');
        }

        $message = $response->json('choices.0.message') ?? [];
        $calls = [];
        foreach ($message['tool_calls'] ?? [] as $call) {
            $arguments = json_decode($call['function']['arguments'] ?? '{}', true);
            $calls[] = new ToolCall(
                (string) ($call['id'] ?? ''),
                (string) ($call['function']['name'] ?? ''),
                is_array($arguments) ? $arguments : [],
            );
        }

        return new ChatCompletion(
            isset($message['content']) ? (string) $message['content'] : null,
            $calls,
            is_array($message) ? $message : ['role' => 'assistant', 'content' => null],
        );
    }
}
