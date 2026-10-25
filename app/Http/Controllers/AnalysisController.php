<?php

namespace App\Http\Controllers;

use App\Models\Transaction;
use App\Services\FsnRopService;
use Carbon\Carbon;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class AnalysisController extends Controller
{
    protected FsnRopService $fsnRopService;

    public function __construct(FsnRopService $fsnRopService)
    {
        $this->fsnRopService = $fsnRopService;
    }

    /**
     * Menampilkan antarmuka pemilih periode analisis FSN & ROP
     * serta hasil perhitungan batch jika tombol proses telah ditekan.
     */
    public function index(Request $request): View
    {
        // Tentukan nilai default rentang tanggal
        // Cek apakah ada data transaksi pada tahun 2025
        $has2025Transactions = Transaction::whereYear('date', 2025)->exists();

        if ($has2025Transactions) {
            $defaultStart = '2025-01-01';
            $defaultEnd = '2025-12-31';
        } else {
            // Default 90 hari terakhir (data demonstrasi terkini)
            $defaultStart = Carbon::now()->subDays(90)->toDateString();
            $defaultEnd = Carbon::now()->toDateString();
        }

        $startDateStr = $request->query('start_date', $defaultStart);
        $endDateStr = $request->query('end_date', $defaultEnd);

        $analysisResult = null;
        $hasTransactions = false;

        // Jika pengguna meminta kalkulasi otomatis saat memuat atau submit
        if ($request->has('process') || $request->has('start_date')) {
            $startDate = Carbon::parse($startDateStr)->startOfDay();
            $endDate = Carbon::parse($endDateStr)->endOfDay();

            // Cek apakah terdapat transaksi penjualan atau pembelian pada periode tersebut
            $hasTransactions = Transaction::whereBetween('date', [$startDate->toDateString(), $endDate->toDateString()])->exists();

            if ($hasTransactions) {
                $analysisResult = $this->fsnRopService->calculateAnalysis($startDate, $endDate);
            }
        }

        return view('analysis.index', compact(
            'startDateStr',
            'endDateStr',
            'analysisResult',
            'hasTransactions'
        ));
    }

    /**
     * Menerapkan hasil perhitungan batch FSN dan ROP ke dalam tabel master barang.
     */
    public function apply(Request $request): RedirectResponse
    {
        $request->validate([
            'start_date' => ['required', 'date'],
            'end_date' => ['required', 'date', 'after_or_equal:start_date'],
        ], [
            'start_date.required' => 'Tanggal awal periode analisis wajib diisi.',
            'end_date.required' => 'Tanggal akhir periode analisis wajib diisi.',
            'end_date.after_or_equal' => 'Tanggal akhir harus sama dengan atau setelah tanggal awal.',
        ]);

        $startDate = Carbon::parse($request->start_date)->startOfDay();
        $endDate = Carbon::parse($request->end_date)->endOfDay();

        $analysisResult = $this->fsnRopService->calculateAnalysis($startDate, $endDate);

        if (empty($analysisResult['items'])) {
            return back()->with('error', 'Tidak ada data barang yang dapat diperbarui.');
        }

        $updatedCount = $this->fsnRopService->applyAnalysisToItems($analysisResult['items']);

        return redirect()->route('analysis.index', [
            'start_date' => $request->start_date,
            'end_date' => $request->end_date,
            'process' => 1,
        ])->with('success', "Hasil perhitungan FSN dan parameter ROP berhasil disimpan ke {$updatedCount} master data barang.");
    }
}
