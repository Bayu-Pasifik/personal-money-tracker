<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CategoryController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        return response()->json(
            $request->user()->categories()->orderBy('type')->orderBy('name')->get(),
        );
    }
}
