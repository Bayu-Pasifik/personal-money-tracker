<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Transaction;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class TransactionController extends Controller
{
    /**
     * PRD.md FR-3.3: tabel riwayat transaksi dengan filter (tanggal, kategori, tipe) dan pencarian.
     */
    public function index(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'from' => ['nullable', 'date'],
            'to' => ['nullable', 'date'],
            'category_id' => ['nullable', 'integer', 'exists:categories,id'],
            'type' => ['nullable', 'in:income,expense'],
            'search' => ['nullable', 'string', 'max:255'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
        ]);

        $query = $request->user()->transactions()->with('category')->latest('transaction_date')->latest('id');

        if (! empty($validated['from'])) {
            $query->whereDate('transaction_date', '>=', $validated['from']);
        }

        if (! empty($validated['to'])) {
            $query->whereDate('transaction_date', '<=', $validated['to']);
        }

        if (! empty($validated['category_id'])) {
            $query->where('category_id', $validated['category_id']);
        }

        if (! empty($validated['type'])) {
            $query->where('type', $validated['type']);
        }

        if (! empty($validated['search'])) {
            $query->where('description', 'like', '%'.$validated['search'].'%');
        }

        return response()->json($query->paginate($validated['per_page'] ?? 20));
    }

    /**
     * PRD.md FR-3.4: CRUD manual transaksi dari web, untuk kasus di luar chat Telegram.
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $this->validatedPayload($request);

        $transaction = $request->user()->transactions()->create([
            ...$validated,
            'source' => 'web',
        ]);

        return response()->json($transaction->load('category'), 201);
    }

    public function update(Request $request, Transaction $transaction): JsonResponse
    {
        $this->authorizeOwnership($request, $transaction);

        $validated = $this->validatedPayload($request);

        $transaction->update($validated);

        return response()->json($transaction->load('category'));
    }

    public function destroy(Request $request, Transaction $transaction): JsonResponse
    {
        $this->authorizeOwnership($request, $transaction);

        $transaction->delete();

        return response()->json(status: 204);
    }

    /**
     * @return array<string, mixed>
     */
    private function validatedPayload(Request $request): array
    {
        return $request->validate([
            'category_id' => ['required', 'integer', 'exists:categories,id'],
            'amount' => ['required', 'integer', 'min:1'],
            'type' => ['required', 'in:income,expense'],
            'description' => ['required', 'string', 'max:255'],
            'transaction_date' => ['required', 'date'],
        ]);
    }

    private function authorizeOwnership(Request $request, Transaction $transaction): void
    {
        abort_unless($transaction->user_id === $request->user()->id, 403);
    }
}
