<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Wallet;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class WalletController extends Controller
{
    /**
     * PRD.md Fase 3 / Task.md: dukungan multi-wallet (mis. pisah rekening/tunai).
     */
    public function index(Request $request): JsonResponse
    {
        // Pastikan dompet default ada supaya list tidak pernah kosong.
        $request->user()->defaultWallet();

        return response()->json($request->user()->wallets()->orderByDesc('is_default')->orderBy('name')->get());
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
        ]);

        $wallet = $request->user()->wallets()->create([
            ...$validated,
            'is_default' => false,
        ]);

        return response()->json($wallet, 201);
    }

    public function update(Request $request, Wallet $wallet): JsonResponse
    {
        $this->authorizeOwnership($request, $wallet);

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
        ]);

        $wallet->update($validated);

        return response()->json($wallet);
    }

    public function destroy(Request $request, Wallet $wallet): JsonResponse
    {
        $this->authorizeOwnership($request, $wallet);

        if ($wallet->is_default) {
            return response()->json(['message' => 'Dompet default tidak bisa dihapus.'], 422);
        }

        // Transaksi yang masih terhubung ke dompet ini otomatis lepas
        // referensinya (wallet_id null-on-delete), riwayatnya tetap utuh.
        $wallet->delete();

        return response()->json(status: 204);
    }

    private function authorizeOwnership(Request $request, Wallet $wallet): void
    {
        abort_unless($wallet->user_id === $request->user()->id, 403);
    }
}
