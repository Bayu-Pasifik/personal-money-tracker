<?php

namespace App\Http\Controllers\Api;

use App\Exceptions\AiParsingException;
use App\Http\Controllers\Controller;
use App\Services\AdvisoryService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AdvisoryController extends Controller
{
    public function __construct(private readonly AdvisoryService $advisory) {}

    public function ask(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'question' => ['required', 'string', 'max:1000'],
        ]);

        try {
            $result = $this->advisory->ask($request->user(), $validated['question'], 'web');
        } catch (AiParsingException $e) {
            return response()->json(['message' => $e->getMessage()], 502);
        }

        return response()->json($result);
    }
}
