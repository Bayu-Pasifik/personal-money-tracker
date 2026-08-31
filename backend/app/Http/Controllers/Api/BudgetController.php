<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Budget;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Validation\Rule;

class BudgetController extends Controller
{
    /**
     * PRD.md Fase 3: budget bulanan per kategori, dengan early-warning saat
     * mendekati/melewati limit — index ini juga menghitung `spent` supaya
     * UI Pengaturan bisa langsung tampilkan progress tanpa panggilan terpisah.
     */
    public function index(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'month' => ['nullable', 'date_format:Y-m'],
        ]);

        $month = Carbon::createFromFormat('Y-m', $validated['month'] ?? now()->format('Y-m'))->startOfMonth();

        $budgets = $request->user()->budgets()
            ->with('category')
            ->whereDate('month', $month)
            ->get()
            ->map(function (Budget $budget) use ($request, $month) {
                $spent = $request->user()->transactions()
                    ->where('category_id', $budget->category_id)
                    ->where('type', 'expense')
                    ->whereBetween('transaction_date', [$month, $month->copy()->endOfMonth()])
                    ->sum('amount');

                return [
                    'id' => $budget->id,
                    'category_id' => $budget->category_id,
                    'category_name' => $budget->category->name,
                    'month' => $month->format('Y-m'),
                    'limit_amount' => $budget->limit_amount,
                    'spent' => (int) $spent,
                ];
            });

        return response()->json($budgets);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'category_id' => [
                'required',
                'integer',
                Rule::exists('categories', 'id')
                    ->where('user_id', $request->user()->id)
                    ->where('type', 'expense'),
            ],
            'month' => ['required', 'date_format:Y-m'],
            'limit_amount' => ['required', 'integer', 'min:1'],
        ]);

        $month = Carbon::createFromFormat('Y-m', $validated['month'])->startOfMonth();

        $budget = Budget::updateOrCreate(
            [
                'user_id' => $request->user()->id,
                'category_id' => $validated['category_id'],
                'month' => $month,
            ],
            ['limit_amount' => $validated['limit_amount']],
        );

        return response()->json($budget->load('category'), 201);
    }

    public function destroy(Request $request, Budget $budget): JsonResponse
    {
        abort_unless($budget->user_id === $request->user()->id, 403);

        $budget->delete();

        return response()->json(status: 204);
    }
}
