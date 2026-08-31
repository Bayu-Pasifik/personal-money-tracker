<?php

namespace App\Services\Ai;

use App\Exceptions\AiParsingException;
use Illuminate\Support\Facades\Http;

/**
 * Wrapper tipis di atas Anthropic Messages API. Dipakai bersama oleh
 * TransactionParserService (tool-calling) dan advisory engine (Fase 2).
 */
class AnthropicClient
{
    private const API_URL = 'https://api.anthropic.com/v1/messages';

    private const API_VERSION = '2023-06-01';

    /**
     * @param  array<int, array{role: string, content: mixed}>  $messages
     * @param  array<int, array<string, mixed>>  $tools
     * @return array<string, mixed> Response body Anthropic Messages API
     */
    public function send(
        string $model,
        string $systemPrompt,
        array $messages,
        array $tools = [],
        ?array $toolChoice = null,
        int $maxTokens = 1024,
    ): array {
        $apiKey = config('services.anthropic.api_key');

        if (! $apiKey) {
            throw new AiParsingException('ANTHROPIC_API_KEY belum diset di .env');
        }

        $payload = [
            'model' => $model,
            'max_tokens' => $maxTokens,
            'system' => $systemPrompt,
            'messages' => $messages,
        ];

        if ($tools !== []) {
            $payload['tools'] = $tools;
        }

        if ($toolChoice !== null) {
            $payload['tool_choice'] = $toolChoice;
        }

        $response = Http::withHeaders([
            'x-api-key' => $apiKey,
            'anthropic-version' => self::API_VERSION,
            'content-type' => 'application/json',
        ])->post(self::API_URL, $payload);

        if ($response->failed()) {
            throw new AiParsingException(
                'Panggilan ke Claude API gagal: '.$response->body(),
            );
        }

        return $response->json();
    }
}
