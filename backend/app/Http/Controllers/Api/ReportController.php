<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Symfony\Component\HttpFoundation\Response;

class ReportController extends Controller
{
    /**
     * PRD.md Fase 3 / Task.md: export laporan bulanan (PDF), ringan untuk
     * shared hosting (dompdf murni PHP, tidak butuh binary eksternal).
     */
    public function monthly(Request $request): Response
    {
        $validated = $request->validate([
            'month' => ['nullable', 'date_format:Y-m'],
        ]);

        $user = $request->user();
        $month = Carbon::createFromFormat('Y-m', $validated['month'] ?? now()->format('Y-m'))->startOfMonth();
        $end = $month->copy()->endOfMonth();

        $transactions = $user->transactions()
            ->with('category')
            ->whereBetween('transaction_date', [$month, $end])
            ->orderBy('transaction_date')
            ->get();

        $totalIncome = (int) $transactions->where('type', 'income')->sum('amount');
        $totalExpense = (int) $transactions->where('type', 'expense')->sum('amount');

        $byCategory = $transactions
            ->groupBy('category_id')
            ->map(function ($group) {
                $category = $group->first()->category;

                return [
                    'category_name' => $category->name,
                    'type' => $category->type,
                    'total' => (int) $group->sum('amount'),
                ];
            })
            ->values();

        $pdf = Pdf::loadView('reports.monthly', [
            'user' => $user,
            'monthLabel' => $month->translatedFormat('F Y'),
            'balance' => $totalIncome - $totalExpense,
            'totalIncome' => $totalIncome,
            'totalExpense' => $totalExpense,
            'byCategory' => $byCategory,
            'transactions' => $transactions,
        ]);

        $filename = 'fintrack-laporan-'.$month->format('Y-m').'.pdf';

        return $pdf->download($filename);
    }
}
