<?php

declare(strict_types=1);

namespace App\Services\Ai;

use App\Contracts\Ai\ChatClient;
use App\Contracts\Ai\ChatCompletion;
use App\Contracts\Ai\ToolCall;

/**
 * Deterministic local stand-in. It never calls a network. Production should use the OpenAI-compatible driver.
 */
final class FakeChatClient implements ChatClient
{
    public function complete(array $messages, array $tools): ChatCompletion
    {
        $text = '';
        foreach (array_reverse($messages) as $message) {
            if (($message['role'] ?? '') === 'user') {
                $text = (string) $message['content'];
                break;
            }
        }

        $allowed = array_map(fn (array $tool) => $tool['function']['name'] ?? '', $tools);
        $call = $this->interpret($text);
        if ($call === null || ! in_array($call['name'], $allowed, true)) {
            $content = 'I can prepare a draft invoice, quote, or ticket. Include the customer and the work.';

            return new ChatCompletion($content, [], ['role' => 'assistant', 'content' => $content]);
        }

        $id = 'call_'.substr(sha1($call['name'].json_encode($call['arguments'])), 0, 12);
        $raw = [
            'role' => 'assistant',
            'content' => null,
            'tool_calls' => [[
                'id' => $id,
                'type' => 'function',
                'function' => [
                    'name' => $call['name'],
                    'arguments' => json_encode($call['arguments'], JSON_THROW_ON_ERROR),
                ],
            ]],
        ];

        return new ChatCompletion(null, [new ToolCall($id, $call['name'], $call['arguments'])], $raw);
    }

    /**
     * @return array{name: string, arguments: array<string, mixed>}|null
     */
    private function interpret(string $text): ?array
    {
        if (preg_match('/invoice\s+(.+?)\s+for\s+(.+)$/i', $text, $matches)) {
            return [
                'name' => 'create_invoice_draft',
                'arguments' => [
                    'customer' => trim($matches[1]),
                    'lines' => $this->linesFromText($matches[2]),
                ],
            ];
        }

        if (preg_match('/quote\s+to\s+.+?\bat\s+(.+?)\s+for\s+(.+)$/i', $text, $matches)) {
            return [
                'name' => 'create_quote_draft',
                'arguments' => [
                    'customer' => trim($matches[1]),
                    'lines' => $this->linesFromText($matches[2]),
                ],
            ];
        }

        if (preg_match('/ticket\s+for\s+(.+?):\s*(.+)$/i', $text, $matches)) {
            $body = trim($matches[2]);
            $priority = 'medium';
            if (preg_match('/\b(low|medium|high|critical)\b/i', $body, $priorityMatch)) {
                $priority = strtolower($priorityMatch[1]);
            }
            $subject = trim((string) preg_replace('/,?\s*(low|medium|high|critical)\s+priority/i', '', $body));
            $category = 'other';
            if (preg_match('/printer|print/i', $body)) {
                $category = 'printing';
            } elseif (preg_match('/cctv|camera/i', $body)) {
                $category = 'cctv';
            } elseif (preg_match('/phone|telephone/i', $body)) {
                $category = 'telephones';
            }

            return [
                'name' => 'create_ticket',
                'arguments' => [
                    'customer' => trim($matches[1]),
                    'subject' => $subject !== '' ? $subject : 'Support request',
                    'description' => $body,
                    'priority' => $priority,
                    'category' => $category,
                ],
            ];
        }

        return null;
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function linesFromText(string $text): array
    {
        $lines = [];
        if (preg_match('/(\d+(?:\.\d+)?)\s+hours?\s+(.+?)\s+at\s+R?\s*(\d+(?:\.\d{1,2})?)/i', $text, $hours)) {
            $lines[] = [
                'description' => trim($hours[2]),
                'product' => trim($hours[2]),
                'quantity' => $hours[1],
                'unit_price' => $this->rands($hours[3]),
                'includes_vat' => (bool) preg_match('/incl|including vat/i', $text),
                'vat_code' => 'standard',
            ];
        }

        if (preg_match('/\band\s+(?:a|an)\s+(.+)$/i', $text, $extra)) {
            $product = trim((string) preg_replace('/\s+at\s+R?\s*\d+(?:\.\d{1,2})?.*$/i', '', $extra[1]));
            $lines[] = [
                'description' => $product,
                'product' => $product,
                'quantity' => '1',
                'unit_price' => '',
                'includes_vat' => false,
                'vat_code' => 'standard',
            ];
        }

        if ($lines === []) {
            $lines[] = [
                'description' => trim($text),
                'product' => trim($text),
                'quantity' => '1',
                'unit_price' => '',
                'includes_vat' => false,
                'vat_code' => 'standard',
            ];
        }

        return $lines;
    }

    private function rands(string $amount): string
    {
        if (! str_contains($amount, '.')) {
            return $amount.'.00';
        }
        [$whole, $frac] = explode('.', $amount, 2);

        return $whole.'.'.str_pad(substr($frac, 0, 2), 2, '0');
    }
}
