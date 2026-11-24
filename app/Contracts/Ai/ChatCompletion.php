<?php

declare(strict_types=1);

namespace App\Contracts\Ai;

final class ChatCompletion
{
    /**
     * @param  list<ToolCall>  $toolCalls
     * @param  array<string, mixed>  $rawMessage
     */
    public function __construct(
        public readonly ?string $content,
        public readonly array $toolCalls,
        public readonly array $rawMessage,
    ) {}
}
