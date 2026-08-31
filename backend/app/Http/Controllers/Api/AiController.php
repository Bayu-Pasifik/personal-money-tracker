<?php

namespace App\Http\Controllers\Api;

use App\Exceptions\AiParsingException;
use App\Http\Controllers\Controller;
use App\Services\TransactionParserService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AiController extends Controller
{
    public function __construct(private readonly TransactionParserService $parser) {}

    /**
     * Dipakai oleh webhook Telegram maupun input manual dari web (Task.md 1.2).
     */
    public function parseTransaction(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'text' => ['required', 'string', 'max:2000'],
        ]);

        $categories = $request->user()
            ->categories()
            ->pluck('name')
            ->all();

        try {
            $result = $this->parser->parse($validated['text'], $categories);
        } catch (AiParsingException $e) {
            return response()->json(['message' => $e->getMessage()], 502);
        }

        return response()->json($result);
    }
}
