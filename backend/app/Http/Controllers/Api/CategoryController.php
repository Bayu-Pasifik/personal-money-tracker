<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Category;
use Illuminate\Database\QueryException;
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

    /**
     * PRD.md FR-3.6 / Task.md Fase 2: CRUD kategori custom dari halaman Pengaturan.
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'type' => ['required', 'in:income,expense'],
        ]);

        $category = $request->user()->categories()->create([
            ...$validated,
            'is_default' => false,
        ]);

        return response()->json($category, 201);
    }

    public function update(Request $request, Category $category): JsonResponse
    {
        $this->authorizeOwnership($request, $category);

        if ($category->is_default) {
            return response()->json(['message' => 'Kategori default tidak bisa diedit.'], 422);
        }

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
        ]);

        $category->update($validated);

        return response()->json($category);
    }

    public function destroy(Request $request, Category $category): JsonResponse
    {
        $this->authorizeOwnership($request, $category);

        if ($category->is_default) {
            return response()->json(['message' => 'Kategori default tidak bisa dihapus.'], 422);
        }

        try {
            $category->delete();
        } catch (QueryException) {
            return response()->json(['message' => 'Kategori ini masih dipakai di transaksi, tidak bisa dihapus.'], 422);
        }

        return response()->json(status: 204);
    }

    private function authorizeOwnership(Request $request, Category $category): void
    {
        abort_unless($category->user_id === $request->user()->id, 403);
    }
}
