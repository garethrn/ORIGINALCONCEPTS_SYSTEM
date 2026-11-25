<?php

declare(strict_types=1);

namespace App\Contracts\Ai;

interface ChatClient
{
    /**
     * @param  array<int, array<string, mixed>>  $messages
     * @param  array<int, array<string, mixed>>  $tools
     */
    public function complete(array $messages, array $tools): ChatCompletion;
}
