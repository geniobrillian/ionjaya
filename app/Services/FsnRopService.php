<?php

namespace App\Services;

use App\Models\Item;
use App\Models\Transaction;
use App\Models\TransactionDetail;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class FsnRopService
{
    /**
     * Memproses analisis FSN dan ROP untuk seluruh barang pada rentang tanggal tertentu.
     * Mengikuti algoritma tahap A sampai D sesuai kontrak penelitian.
     *
     * @param Carbon $startDate
     * @param Carbon $endDate
     * @return array
     */
    public function calculateAnalysis(Carbon $startDate, Carbon $endDate): array
    {
        $startStr = $startDate->toDateString();
        $endStr = $endDate->toDateString();

        // Hitung jumlah hari dalam periode pengamatan (J_hp)
        $jHp = max(1, (int) $startDate->diffInDays($endDate) + 1);
        $jHt = 365; // Jumlah hari dalam satu tahun acuan

        // Ambil seluruh master barang beserta relasi pemasok
        $items = Item::with('suppliers')->get();

        // Ambil total penjualan (P_pk) per barang dalam periode
        $salesQuantities = DB::table('transaction_details')
            ->join('transactions', 'transaction_details.transaction_id', '=', 'transactions.id')
            ->where('transactions.transaction_type', 'penjualan')
            ->whereBetween('transactions.date', [$startStr, $endStr])
            ->groupBy('transaction_details.item_id')
            ->select('transaction_details.item_id', DB::raw('SUM(transaction_details.quantity) as total_sold'))
            ->pluck('total_sold', 'item_id');

        // Ambil total pembelian (P_ms) per barang dalam periode
        $purchaseQuantities = DB::table('transaction_details')
            ->join('transactions', 'transaction_details.transaction_id', '=', 'transactions.id')
            ->where('transactions.transaction_type', 'pembelian')
            ->whereBetween('transactions.date', [$startStr, $endStr])
            ->groupBy('transaction_details.item_id')
            ->select('transaction_details.item_id', DB::raw('SUM(transaction_details.quantity) as total_bought'))
            ->pluck('total_bought', 'item_id');

        // Ambil data penjualan harian untuk mencari penjualan maksimum harian (P_max)
        $dailySalesGroup = DB::table('transaction_details')
            ->join('transactions', 'transaction_details.transaction_id', '=', 'transactions.id')
            ->where('transactions.transaction_type', 'penjualan')
            ->whereBetween('transactions.date', [$startStr, $endStr])
            ->groupBy('transaction_details.item_id', 'transactions.date')
            ->select('transaction_details.item_id', 'transactions.date', DB::raw('SUM(transaction_details.quantity) as daily_sold'))
            ->get()
            ->groupBy('item_id');

        // Hitung mutasi setelah tanggal mulai untuk merekonstruksi stok awal (P_aw)
        $purchasesSinceStart = DB::table('transaction_details')
            ->join('transactions', 'transaction_details.transaction_id', '=', 'transactions.id')
            ->where('transactions.transaction_type', 'pembelian')
            ->where('transactions.date', '>=', $startStr)
            ->groupBy('transaction_details.item_id')
            ->select('transaction_details.item_id', DB::raw('SUM(transaction_details.quantity) as total_qty'))
            ->pluck('total_qty', 'item_id');

        $salesSinceStart = DB::table('transaction_details')
            ->join('transactions', 'transaction_details.transaction_id', '=', 'transactions.id')
            ->where('transactions.transaction_type', 'penjualan')
            ->where('transactions.date', '>=', $startStr)
            ->groupBy('transaction_details.item_id')
            ->select('transaction_details.item_id', DB::raw('SUM(transaction_details.quantity) as total_qty'))
            ->pluck('total_qty', 'item_id');

        $results = [];
        $fastCount = 0;
        $slowCount = 0;
        $nonCount = 0;

        foreach ($items as $item) {
            $itemId = $item->id;

            // Kuantitas penjualan periode (P_pk) dan pembelian periode (P_ms)
            $pPk = (int) ($salesQuantities[$itemId] ?? 0);
            $pMs = (int) ($purchaseQuantities[$itemId] ?? 0);

            // Rekonstruksi stok awal periode (P_aw) dari stok fisik saat ini
            $totalBoughtSince = (int) ($purchasesSinceStart[$itemId] ?? 0);
            $totalSoldSince = (int) ($salesSinceStart[$itemId] ?? 0);
            $pAw = max(0, $item->stock - $totalBoughtSince + $totalSoldSince);

            // Tahap A: Perhitungan Persediaan Akhir (P_ak) dan Persediaan Rata-rata (P_rt)
            // Rumus 3.1: P_ak = P_aw + P_ms - P_pk
            $pAk = max(0, $pAw + $pMs - $pPk);

            // Rumus 3.2: P_rt = (P_aw + P_ak) / 2
            $pRt = ($pAw + $pAk) / 2;

            // Rumus 3.3, 3.4, 3.5: TOR periode, W_sp, dan TOR tahunan
            if ($pRt > 0 && $pPk > 0) {
                $torP = $pPk / $pRt;
                $wSp = $jHp / $torP; // Waktu simpan rata-rata (hari)
                $tor = $wSp > 0 ? ($jHt / $wSp) : 0; // TOR tahunan
            } else {
                $torP = 0;
                $wSp = 0;
                $tor = 0;
            }

            $tor = round($tor, 2);

            // Tahap B: Klasifikasi FSN
            // Fast Moving: TOR > 3
            // Slow Moving: 1 <= TOR <= 3
            // Non Moving: TOR < 1
            if ($tor > 3) {
                $fsnCategory = 'F';
                $fastCount++;
            } elseif ($tor >= 1) {
                $fsnCategory = 'S';
                $slowCount++;
            } else {
                $fsnCategory = 'N';
                $nonCount++;
            }

            // Tahap C & D: Perhitungan Safety Stock dan ROP.
            // Safety Stock khusus Fast Moving; ROP dihitung untuk Fast
            // (ROP + SS) dan Slow (ROP saja, tanpa SS).
            $safetyStock = null;
            $rop = null;
            $pMax = 0;
            $pRtDaily = 0;
            // Lead time acuan = lead time Pemasok Utama; barang tanpa
            // Utama memakai 1 hari (fallback yang sama seperti dulu).
            $leadTime = max(1, (int) ($item->primarySupplier->lead_time ?? 1));

            // Penjualan rata-rata harian (P_rt harian = P_pk / J_hp),
            // dipakai kategori Fast dan Slow.
            if ($fsnCategory === 'F' || $fsnCategory === 'S') {
                $pRtDaily = $pPk / $jHp;
            }

            if ($fsnCategory === 'F') {
                // Penjualan maksimum harian (P_max)
                $itemDailySales = $dailySalesGroup[$itemId] ?? collect();
                $pMax = $itemDailySales->max('daily_sold') ?? 0;

                // Rumus 3.6: SS = (P_max - P_rt) x L
                $calculatedSs = ($pMax - $pRtDaily) * $leadTime;
                $safetyStock = max(0, (int) round($calculatedSs));

                // Rumus 3.7: ROP = (P_rt x L) + SS
                $calculatedRop = ($pRtDaily * $leadTime) + $safetyStock;
                $rop = max(1, (int) round($calculatedRop));
            } elseif ($fsnCategory === 'S') {
                // ROP tanpa Safety Stock: R = ceil(P_rt harian x L).
                // SS = 0 untuk Slow adalah keputusan trade-off biaya yang
                // sadar (bukan klaim tanpa ketidakpastian): lead time
                // 1 hari stabil, volume Slow kecil, dan pembelian
                // insidental pegawai menjadi cadangan saat stok habis.
                $rop = max(1, (int) ceil($pRtDaily * $leadTime));
            }

            // Batas pemesanan untuk evaluasi stok menipis (ROP hasil hitung,
            // untuk Fast maupun Slow; Non Moving tidak punya batas).
            $orderThreshold = 0;
            if ($fsnCategory === 'F' || $fsnCategory === 'S') {
                $orderThreshold = $rop ?? 0;
            }

            // Status stok menipis (Tahap E)
            $isLowStock = ($fsnCategory !== 'N') && ($orderThreshold > 0) && ($item->stock <= $orderThreshold);

            $results[] = [
                'item_id' => $item->id,
                'item_code' => $item->item_code,
                'item_name' => $item->item_name,
                'supplier_name' => $item->primarySupplier->supplier_name ?? '-',
                'lead_time' => $leadTime,
                'stock' => $item->stock,
                'p_aw' => $pAw,
                'p_ms' => $pMs,
                'p_pk' => $pPk,
                'p_ak' => $pAk,
                'p_rt' => round($pRt, 2),
                'tor_p' => round($torP, 4),
                'w_sp' => round($wSp, 2),
                'tor' => $tor,
                'fsn_category' => $fsnCategory,
                'p_max' => (int) $pMax,
                'p_rt_daily' => round($pRtDaily, 2),
                'safety_stock' => $safetyStock,
                'rop' => $rop,
                'order_threshold' => $orderThreshold,
                'is_low_stock' => $isLowStock,
            ];
        }

        return [
            'period' => [
                'start_date' => $startStr,
                'end_date' => $endStr,
                'days' => $jHp,
            ],
            'summary' => [
                'total_items' => count($results),
                'fast_count' => $fastCount,
                'slow_count' => $slowCount,
                'non_count' => $nonCount,
            ],
            'items' => $results,
        ];
    }

    /**
     * Menerapkan hasil batch klasifikasi FSN dan ROP ke dalam tabel master items.
     *
     * @param array $analysisItems
     * @return int Jumlah item yang diperbarui
     */
    public function applyAnalysisToItems(array $analysisItems): int
    {
        $updatedCount = 0;

        DB::transaction(function () use ($analysisItems, &$updatedCount) {
            foreach ($analysisItems as $row) {
                $itemId = $row['item_id'] ?? null;
                if (! $itemId) {
                    continue;
                }

                $item = Item::find($itemId);
                if (! $item) {
                    continue;
                }

                $category = $row['fsn_category'];
                $item->fsn_category = $category;

                // Fast: SS + ROP. Slow: ROP saja (SS null). Non: tidak ada.
                if ($category === 'F') {
                    $item->safety_stock = $row['safety_stock'] ?? 0;
                    $item->rop = $row['rop'] ?? 0;
                } elseif ($category === 'S') {
                    $item->safety_stock = null;
                    $item->rop = $row['rop'] ?? 0;
                } else {
                    $item->safety_stock = null;
                    $item->rop = null;
                }

                $item->save();
                $updatedCount++;
            }
        });

        return $updatedCount;
    }

    /**
     * Mengambil daftar barang yang stoknya menipis (Stok <= Batas Pemesanan).
     * Diurutkan berdasarkan prioritas: Fast Moving terlebih dahulu, lalu Slow Moving.
     *
     * @return Collection
     */
    public function getLowStockItems(): Collection
    {
        $items = Item::with('suppliers')->get();

        return $items->filter(function (Item $item) {
            return $item->is_low_stock;
        })->sortBy(function (Item $item) {
            // Urutan prioritas: Fast Moving (1), Slow Moving (2)
            return $item->fsn_category === 'F' ? 1 : 2;
        })->values();
    }

    /**
     * Menghitung estimasi laba kotor dan ringkasan metrik dashboard (Tahap F).
     * Rumus: Laba = Jumlah x (Harga Jual - Harga Beli Terakhir).
     *
     * @param string $range 'today', 'weekly', 'monthly', atau 'all'
     * @return array
     */
    public function calculateProfit(string $range = 'weekly'): array
    {
        $today = Carbon::today();

        if ($range === 'today') {
            $startDate = $today->copy()->startOfDay();
            $endDate = $today->copy()->endOfDay();
        } elseif ($range === 'monthly') {
            $startDate = $today->copy()->startOfMonth()->startOfDay();
            $endDate = $today->copy()->endOfMonth()->endOfDay();
        } elseif ($range === 'last_month') {
            // Bulan lalu: satu bulan kalender penuh sebelum bulan berjalan.
            $lastMonth = $today->copy()->subMonthNoOverflow();
            $startDate = $lastMonth->copy()->startOfMonth()->startOfDay();
            $endDate = $lastMonth->copy()->endOfMonth()->endOfDay();
        } elseif ($range === 'all') {
            $startDate = Carbon::create(2020, 1, 1)->startOfDay();
            $endDate = $today->copy()->endOfDay();
        } else {
            // Default: 7 hari terakhir (weekly)
            $startDate = $today->copy()->subDays(6)->startOfDay();
            $endDate = $today->copy()->endOfDay();
        }

        // Ambil rincian transaksi penjualan pada rentang waktu yang dipilih
        $details = TransactionDetail::with('item')
            ->whereHas('transaction', function ($q) use ($startDate, $endDate) {
                $q->where('transaction_type', 'penjualan')
                    // Batas atas memakai end-of-day (23:59:59), bukan 'Y-m-d':
                    // pola 'Y-m-d' mengecualikan hari terakhir di SQLite
                    // (nilai tersimpan 'Y-m-d 00:00:00' > 'Y-m-d') — penyebab
                    // yang sama dengan bug grafik tren yang diperbaiki di
                    // commit 480f1fc. Aman juga untuk kolom DATE di MySQL.
                    ->whereBetween('date', [$startDate->toDateString(), $endDate->toDateTimeString()]);
            })
            ->get();

        $totalSales = 0;
        $totalCost = 0;
        $totalProfit = 0;

        foreach ($details as $detail) {
            $item = $detail->item;
            $qty = $detail->quantity;
            $sellingPrice = (float) $detail->unit_price;
            $purchasePrice = $item ? (float) $item->purchase_price : 0;

            $salesValue = $qty * $sellingPrice;
            $costValue = $qty * $purchasePrice;
            $profitValue = $salesValue - $costValue;

            $totalSales += $salesValue;
            $totalCost += $costValue;
            $totalProfit += $profitValue;
        }

        // Hitung total transaksi penjualan unik
        $totalTransactions = Transaction::where('transaction_type', 'penjualan')
            ->whereBetween('date', [$startDate->toDateString(), $endDate->toDateTimeString()])
            ->count();

        // Hitung jumlah barang menipis saat ini
        $lowStockCount = $this->getLowStockItems()->count();

        // Susun grafik tren laba 7 hari terakhir
        $trendDates = [];
        for ($i = 6; $i >= 0; $i--) {
            $d = $today->copy()->subDays($i)->toDateString();
            $trendDates[$d] = [
                'date' => $d,
                'label' => Carbon::parse($d)->translatedFormat('d M'),
                // Judul lengkap untuk kartu tooltip (label sumbu tetap 'd M'
                // agar muat di 7 kolom).
                'title' => Carbon::parse($d)->translatedFormat('d M Y'),
                'sales' => 0,
                'profit' => 0,
            ];
        }

        // Pola yang sama seperti perhitungan card di atas: whereHas + relasi
        // yang di-eager-load, tanpa join manual. Tanggal diambil dari cast
        // 'date' model Transaction sehingga formatnya dijamin Y-m-d.
        // (Versi lama memakai join + alias tx_date untuk mencocokkan hari;
        // penulisan ulang ini menghapus konstruksi itu sepenuhnya.)
        $trendDetails = TransactionDetail::with(['item', 'transaction'])
            ->whereHas('transaction', function ($q) use ($today) {
                // Batas akhir memakai akhir hari (23:59:59), bukan tanggal saja:
                // di SQLite, nilai date tersimpan sebagai 'Y-m-d 00:00:00' (cast
                // date model di-bind sebagai datetime), sehingga batas 'Y-m-d'
                // mengecualikan seluruh transaksi di hari terakhir periode —
                // itulah yang membuat tes tren gagal pada asersi hari ini,
                // padahal asersi kemarin lulus. Di MySQL DATE, batas ini aman.
                $q->where('transaction_type', 'penjualan')
                    ->whereBetween('date', [$today->copy()->subDays(6)->toDateString(), $today->copy()->endOfDay()->toDateTimeString()]);
            })
            ->get();

        foreach ($trendDetails as $td) {
            $dateKey = $td->transaction?->date?->toDateString();
            if ($dateKey !== null && isset($trendDates[$dateKey])) {
                $qty = $td->quantity;
                $sellingPrice = (float) $td->unit_price;
                $purchasePrice = $td->item ? (float) $td->item->purchase_price : 0;

                $salesVal = $qty * $sellingPrice;
                $profitVal = $salesVal - ($qty * $purchasePrice);

                $trendDates[$dateKey]['sales'] += $salesVal;
                $trendDates[$dateKey]['profit'] += $profitVal;
            }
        }

        return [
            'range' => $range,
            'start_date' => $startDate->toDateString(),
            'end_date' => $endDate->toDateString(),
            'total_sales' => round($totalSales, 2),
            'total_cost' => round($totalCost, 2),
            'total_profit' => round($totalProfit, 2),
            'total_transactions' => $totalTransactions,
            'low_stock_count' => $lowStockCount,
            'daily_trend' => array_values($trendDates),
        ];
    }
}
