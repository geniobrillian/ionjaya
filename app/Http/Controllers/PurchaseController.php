<?php

namespace App\Http\Controllers;

use App\Models\Item;
use App\Models\Supplier;
use App\Models\Transaction;
use App\Models\TransactionDetail;
use Carbon\Carbon;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class PurchaseController extends Controller
{
    /**
     * Menampilkan formulir pencatatan transaksi pembelian barang
     * dan daftar riwayat pembelian terkini.
     */
    public function index(Request $request): View
    {
        $items = Item::with('supplier')
            ->orderBy('item_name')
            ->get();

        $suppliers = Supplier::orderBy('supplier_name')->get();

        // 10 riwayat transaksi pembelian terakhir
        $recentPurchases = Transaction::with(['user', 'details.item.supplier'])
            ->where('transaction_type', 'pembelian')
            ->orderByDesc('id')
            ->take(10)
            ->get();

        return view('purchases.index', compact('items', 'suppliers', 'recentPurchases'));
    }

    /**
     * Menyimpan transaksi pembelian dari pemasok.
     * Stok barang akan bertambah dan harga beli master barang diperbarui
     * dengan harga beli terakhir.
     */
    public function store(Request $request): RedirectResponse
    {
        $request->validate([
            'items' => ['required', 'array', 'min:1'],
            'items.*.id' => ['required', 'exists:items,id'],
            'items.*.quantity' => ['required', 'integer', 'min:1'],
            'items.*.purchase_price' => ['required', 'numeric', 'min:0'],
        ], [
            'items.required' => 'Daftar barang yang dibeli tidak boleh kosong.',
            'items.min' => 'Pilih minimal satu barang untuk dicatat sebagai pembelian.',
            'items.*.quantity.min' => 'Jumlah barang minimal 1.',
            'items.*.purchase_price.min' => 'Harga beli tidak boleh negatif.',
        ]);

        try {
            DB::transaction(function () use ($request) {
                $totalTransaction = 0;
                $detailsToInsert = [];

                foreach ($request->items as $row) {
                    $item = Item::lockForUpdate()->findOrFail($row['id']);
                    $qty = (int) $row['quantity'];
                    $purchasePrice = (float) $row['purchase_price'];
                    $subtotal = $qty * $purchasePrice;

                    $totalTransaction += $subtotal;

                    $detailsToInsert[] = [
                        'item' => $item,
                        'quantity' => $qty,
                        'unit_price' => $purchasePrice,
                        'subtotal' => $subtotal,
                    ];
                }

                // 1. Simpan Header Transaksi Pembelian
                $transaction = Transaction::create([
                    'date' => Carbon::today()->toDateString(),
                    'transaction_type' => 'pembelian',
                    'total' => $totalTransaction,
                    'user_id' => Auth::id(),
                ]);

                // 2. Simpan Detail Transaksi, Tambah Stok, dan Update Harga Beli Terakhir
                foreach ($detailsToInsert as $detail) {
                    TransactionDetail::create([
                        'transaction_id' => $transaction->id,
                        'item_id' => $detail['item']->id,
                        'quantity' => $detail['quantity'],
                        'unit_price' => $detail['unit_price'],
                        'subtotal' => $detail['subtotal'],
                    ]);

                    // Tambah stok fisik
                    $detail['item']->increment('stock', $detail['quantity']);

                    // Perbarui harga beli terakhir pada master barang (digunakan untuk rumus laba)
                    $detail['item']->update([
                        'purchase_price' => $detail['unit_price'],
                    ]);
                }
            });

            return redirect()->route('purchases.index')->with('success', 'Transaksi pembelian berhasil dicatat. Stok barang telah bertambah dan harga beli master telah diperbarui.');
        } catch (\Exception $e) {
            return back()->with('error', $e->getMessage())->withInput();
        }
    }
}
