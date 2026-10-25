<?php

namespace App\Http\Controllers;

use App\Models\Transaction;
use App\Services\FsnRopService;
use Illuminate\Http\Request;
use Illuminate\View\View;

class DashboardController extends Controller
{
    protected FsnRopService $fsnRopService;

    public function __construct(FsnRopService $fsnRopService)
    {
        $this->fsnRopService = $fsnRopService;
    }

    /**
     * Menampilkan dashboard utama untuk Super Admin:
     * - Ringkasan estimasi laba kotor harian, mingguan, atau bulanan
     * - Omset total penjualan dan jumlah transaksi
     * - Indikator barang menipis
     * - Grafik tren penjualan dan laba 7 hari terakhir
     * - Transaksi penjualan terkini
     */
    public function index(Request $request): View
    {
        $range = $request->query('range', 'weekly');
        if (! in_array($range, ['today', 'weekly', 'monthly', 'all'])) {
            $range = 'weekly';
        }

        $metrics = $this->fsnRopService->calculateProfit($range);

        // Ambil 5 transaksi penjualan terakhir
        $recentTransactions = Transaction::with(['user', 'details.item'])
            ->where('transaction_type', 'penjualan')
            ->orderByDesc('id')
            ->take(5)
            ->get();

        // Ambil 5 barang dengan stok paling menipis
        $lowStockItems = $this->fsnRopService->getLowStockItems()->take(5);

        return view('dashboard.index', compact('metrics', 'range', 'recentTransactions', 'lowStockItems'));
    }
}
