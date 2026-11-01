<?php

namespace App\Http\Controllers;

use App\Models\Item;
use Illuminate\Http\Request;
use Illuminate\View\View;

class StockController extends Controller
{
    /**
     * Menampilkan daftar monitoring stok seluruh barang
     * beserta indikator status HABIS / MENIPIS / AMAN / BELUM DIANALISIS.
     */
    public function index(Request $request): View
    {
        $search = $request->query('q');
        $categoryFilter = $request->query('category');
        $statusFilter = $request->query('status');

        $query = Item::with('suppliers')->orderBy('item_name');

        if (! empty($search)) {
            $query->where(function ($q) use ($search) {
                $q->where('item_name', 'like', "%{$search}%")
                  ->orWhere('item_code', 'like', "%{$search}%");
            });
        }

        if (! empty($categoryFilter) && in_array($categoryFilter, ['F', 'S', 'N'])) {
            $query->where('fsn_category', $categoryFilter);
        }

        $allItems = $query->get();

        // Hitung ringkasan status stok berdasarkan status tampilan.
        // Banner dan filter "Perlu Pengadaan" memakai gabungan habis +
        // menipis (status tampilan), BUKAN is_low_stock: is_low_stock
        // menghitung habis Fast/Slow tapi bukan habis Non, sehingga
        // angkanya tidak pernah sama persis dengan isi daftar gabungan.
        $totalItems = $allItems->count();
        $menipisCount = $allItems->filter(fn($item) => $item->stock_status === 'menipis')->count();
        $safeStockCount = $allItems->filter(fn($item) => $item->stock_status === 'aman')->count();
        $habisCount = $allItems->filter(fn($item) => $item->stock_status === 'habis')->count();
        $belumCount = $allItems->filter(fn($item) => $item->stock_status === 'belum')->count();
        $perluCount = $menipisCount + $habisCount;
        $perluParts = [];
        if ($habisCount > 0) {
            $perluParts[] = "{$habisCount} habis";
        }
        if ($menipisCount > 0) {
            $perluParts[] = "{$menipisCount} menipis";
        }
        $perluBreakdown = implode(' dan ', $perluParts);

        // Filter status stok (berdasarkan status tampilan).
        // 'perlu' = gabungan yang perlu pengadaan: habis dulu (paling
        // gawat), lalu menipis, abjad di dalam tiap kelompok.
        if ($statusFilter === 'perlu') {
            $items = $allItems->filter(fn($item) => in_array($item->stock_status, ['menipis', 'habis'], true))
                ->sortBy(fn($item) => ($item->stock_status === 'habis' ? '0' : '1').$item->item_name)
                ->values();
        } elseif (in_array($statusFilter, ['menipis', 'aman', 'habis', 'belum'], true)) {
            $items = $allItems->filter(fn($item) => $item->stock_status === $statusFilter);
        } else {
            $items = $allItems;
        }

        return view('stock.index', compact(
            'items',
            'search',
            'categoryFilter',
            'statusFilter',
            'totalItems',
            'menipisCount',
            'safeStockCount',
            'habisCount',
            'belumCount',
            'perluCount',
            'perluBreakdown'
        ));
    }
}
