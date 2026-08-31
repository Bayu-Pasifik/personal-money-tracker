<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <style>
        body { font-family: 'Helvetica', sans-serif; font-size: 12px; color: #1C2620; }
        h1 { font-size: 20px; margin-bottom: 4px; }
        .subtitle { color: #5B6358; margin-bottom: 24px; }
        .summary { width: 100%; margin-bottom: 24px; border-collapse: collapse; }
        .summary td { padding: 8px 12px; border: 1px solid #D6D9CC; }
        .summary .label { color: #5B6358; font-size: 10px; }
        .summary .value { font-family: 'Courier New', monospace; font-size: 16px; font-weight: bold; }
        .income { color: #2F6F5E; }
        .expense { color: #A63D2F; }
        table.transactions { width: 100%; border-collapse: collapse; margin-top: 8px; }
        table.transactions th { text-align: left; border-bottom: 2px solid #1C2620; padding: 6px 8px; font-size: 10px; color: #5B6358; }
        table.transactions td { padding: 6px 8px; border-bottom: 1px solid #D6D9CC; font-size: 11px; }
        table.transactions td.amount { text-align: right; font-family: 'Courier New', monospace; }
        h2 { font-size: 14px; margin-top: 24px; margin-bottom: 8px; }
    </style>
</head>
<body>
    <h1>FinTrack AI — Laporan Bulanan</h1>
    <p class="subtitle">{{ $user->name }} &middot; {{ $monthLabel }}</p>

    <table class="summary">
        <tr>
            <td>
                <div class="label">SALDO BERSIH</div>
                <div class="value">Rp{{ number_format($balance, 0, ',', '.') }}</div>
            </td>
            <td>
                <div class="label">PEMASUKAN</div>
                <div class="value income">Rp{{ number_format($totalIncome, 0, ',', '.') }}</div>
            </td>
            <td>
                <div class="label">PENGELUARAN</div>
                <div class="value expense">Rp{{ number_format($totalExpense, 0, ',', '.') }}</div>
            </td>
        </tr>
    </table>

    <h2>Breakdown per Kategori</h2>
    <table class="transactions">
        <thead>
            <tr><th>Kategori</th><th>Tipe</th><th style="text-align:right">Total</th></tr>
        </thead>
        <tbody>
            @forelse ($byCategory as $row)
                <tr>
                    <td>{{ $row['category_name'] }}</td>
                    <td>{{ $row['type'] === 'income' ? 'Pemasukan' : 'Pengeluaran' }}</td>
                    <td class="amount">Rp{{ number_format($row['total'], 0, ',', '.') }}</td>
                </tr>
            @empty
                <tr><td colspan="3">Tidak ada transaksi bulan ini.</td></tr>
            @endforelse
        </tbody>
    </table>

    <h2>Riwayat Transaksi</h2>
    <table class="transactions">
        <thead>
            <tr><th>Tanggal</th><th>Deskripsi</th><th>Kategori</th><th style="text-align:right">Nominal</th></tr>
        </thead>
        <tbody>
            @forelse ($transactions as $transaction)
                <tr>
                    <td>{{ \Illuminate\Support\Carbon::parse($transaction->transaction_date)->translatedFormat('d M Y') }}</td>
                    <td>{{ $transaction->description }}</td>
                    <td>{{ $transaction->category->name }}</td>
                    <td class="amount {{ $transaction->type === 'income' ? 'income' : 'expense' }}">
                        {{ $transaction->type === 'income' ? '+' : '-' }}Rp{{ number_format($transaction->amount, 0, ',', '.') }}
                    </td>
                </tr>
            @empty
                <tr><td colspan="4">Tidak ada transaksi bulan ini.</td></tr>
            @endforelse
        </tbody>
    </table>
</body>
</html>
