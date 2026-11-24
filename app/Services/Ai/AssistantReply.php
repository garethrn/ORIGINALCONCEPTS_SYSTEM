<?php

declare(strict_types=1);

namespace App\Services\Ai;

use App\Models\AiDraft;

final class AssistantReply
{
    public function __construct(
        public readonly string $message,
        public readonly ?AiDraft $draft = null,
    ) {}
}
