<?php

namespace App\Http\Controllers;

use App\Models\Item;
use App\Models\Transaction;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ReportController extends Controller
{
    /**
     * Menampilkan halaman laporan dengan 3 jenis laporan:
     * 1. Laporan Stok / Persediaan Barang
     * 2. Laporan Riwayat Transaksi (Penjualan / Pembelian)
     * 3. Laporan Hasil Analisis FSN & ROP
     */
    public function index(Request $request): View
    {
        $reportType = $request->query('type', 'stok');
        if (! in_array($reportType, ['stok', 'transaksi', 'fsn'])) {
            $reportType = 'stok';
        }

        // Variabel untuk tipe transaksi
        $startDateStr = $request->query('start_date', Carbon::now()->subDays(30)->toDateString());
        $endDateStr = $request->query('end_date', Carbon::now()->toDateString());
        $transactionType = $request->query('transaction_type', 'all');

        $reportData = [];
        $totalAmount = 0;
        $totalPenjualan = 0;
        $totalPembelian = 0;

        if ($reportType === 'stok') {
            $reportData = Item::with('suppliers')->orderBy('item_name')->get();
        } elseif ($reportType === 'transaksi') {
            $query = Transaction::with(['user', 'details.item'])
                ->whereBetween('date', [$startDateStr, $endDateStr])
                ->orderBy('date', 'desc')
                ->orderBy('id', 'desc');

            if (in_array($transactionType, ['penjualan', 'pembelian'])) {
                $query->where('transaction_type', $transactionType);
            }

            $reportData = $query->get();
            $totalAmount = $reportData->sum('total');
            // Total dipisah per jenis agar filter "Semua" tidak menjumlahkan
            // uang masuk (penjualan) dan uang keluar (pembelian) jadi satu angka.
            $totalPenjualan = $reportData->where('transaction_type', 'penjualan')->sum('total');
            $totalPembelian = $reportData->where('transaction_type', 'pembelian')->sum('total');
        } elseif ($reportType === 'fsn') {
            $reportData = Item::with('suppliers')
                ->orderByRaw("CASE WHEN fsn_category = 'F' THEN 1 WHEN fsn_category = 'S' THEN 2 WHEN fsn_category = 'N' THEN 3 ELSE 4 END")
                ->orderBy('item_name')
                ->get();
        }

        return view('reports.index', compact(
            'reportType',
            'reportData',
            'startDateStr',
            'endDateStr',
            'transactionType',
            'totalAmount',
            'totalPenjualan',
            'totalPembelian'
        ));
    }
}
