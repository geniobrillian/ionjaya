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
        // lowStockCount dipertahankan untuk banner (barang Fast/Slow yang
        // stoknya mencapai/di bawah batas pemesanan, termasuk yang habis).
        $totalItems = $allItems->count();
        $lowStockCount = $allItems->filter(fn($item) => $item->is_low_stock)->count();
        $menipisCount = $allItems->filter(fn($item) => $item->stock_status === 'menipis')->count();
        $safeStockCount = $allItems->filter(fn($item) => $item->stock_status === 'aman')->count();
        $habisCount = $allItems->filter(fn($item) => $item->stock_status === 'habis')->count();
        $belumCount = $allItems->filter(fn($item) => $item->stock_status === 'belum')->count();

        // Filter status stok (berdasarkan status tampilan, bukan biner menipis/aman)
        if (in_array($statusFilter, ['menipis', 'aman', 'habis', 'belum'], true)) {
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
            'lowStockCount',
            'menipisCount',
            'safeStockCount',
            'habisCount',
            'belumCount'
        ));
    }
}
