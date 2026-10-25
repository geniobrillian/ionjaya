<?php

namespace App\Http\Controllers;

use App\Models\Item;
use Illuminate\Http\Request;
use Illuminate\View\View;

class StockController extends Controller
{
    /**
     * Menampilkan daftar monitoring stok seluruh barang
     * beserta indikator status MENIPIS / AMAN.
     */
    public function index(Request $request): View
    {
        $search = $request->query('q');
        $categoryFilter = $request->query('category');
        $statusFilter = $request->query('status');

        $query = Item::with('supplier')->orderBy('item_name');

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

        // Hitung total ringkasan status stok
        $totalItems = $allItems->count();
        $lowStockCount = $allItems->filter(fn($item) => $item->is_low_stock)->count();
        $safeStockCount = $totalItems - $lowStockCount;

        // Filter status stok (Menipis vs Aman)
        if ($statusFilter === 'menipis') {
            $items = $allItems->filter(fn($item) => $item->is_low_stock);
        } elseif ($statusFilter === 'aman') {
            $items = $allItems->filter(fn($item) => ! $item->is_low_stock);
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
            'safeStockCount'
        ));
    }
}
