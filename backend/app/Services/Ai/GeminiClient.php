<?php

namespace App\Services\Ai;

use App\Exceptions\AiParsingException;
use Illuminate\Support\Facades\Http;

/**
 * Wrapper tipis di atas Google Gemini API (generateContent, function calling).
 * Dipakai bersama oleh TransactionParserService, CommentGeneratorService,
 * dan AdvisoryService. Gemini dipilih karena free tier-nya cukup generous
 * untuk pemakaian personal dan tetap dukung tool-calling penuh.
 */
class GeminiClient
{
    private const API_BASE = 'https://generativelanguage.googleapis.com/v1beta/models/';

    /**
     * @param  array<int, array{role: string, content: string}>  $messages  role 'user' atau 'assistant'
     * @param  array<int, array<string, mixed>>  $tools  Function declarations (Gemini schema, huruf besar utk type)
     * @return array<string, mixed> Response body Gemini generateContent
     */
    public function send(
        string $model,
        string $systemPrompt,
        array $messages,
        array $tools = [],
        bool $forceFunctionCall = false,
        int $maxTokens = 1024,
    ): array {
        $apiKey = config('services.gemini.api_key');

        if (! $apiKey) {
            throw new AiParsingException('GEMINI_API_KEY belum diset di .env');
        }

        $contents = array_map(
            fn (array $message) => [
                'role' => $message['role'] === 'assistant' ? 'model' : 'user',
                'parts' => [['text' => $message['content']]],
            ],
            $messages,
        );

        $payload = [
            'contents' => $contents,
            'systemInstruction' => ['parts' => [['text' => $systemPrompt]]],
            'generationConfig' => ['maxOutputTokens' => $maxTokens],
        ];

        if ($tools !== []) {
            $payload['tools'] = [['functionDeclarations' => $tools]];
        }

        if ($forceFunctionCall) {
            $payload['toolConfig'] = ['functionCallingConfig' => ['mode' => 'ANY']];
        }

        $url = self::API_BASE.$model.':generateContent?key='.$apiKey;

        $response = Http::asJson()->post($url, $payload);

        if ($response->failed()) {
            throw new AiParsingException(
                'Panggilan ke Gemini API gagal: '.$response->body(),
            );
        }

        return $response->json();
    }
}
