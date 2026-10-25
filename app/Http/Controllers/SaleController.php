<?php

namespace App\Http\Controllers;

use App\Models\Item;
use App\Models\Transaction;
use App\Models\TransactionDetail;
use Carbon\Carbon;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class SaleController extends Controller
{
    /**
     * Menampilkan antarmuka transaksi penjualan (model kasir 2-kolom)
     * serta daftar riwayat penjualan terkini.
     */
    public function index(Request $request): View
    {
        // Ambil daftar barang yang siap dijual (urut abjad)
        $items = Item::with('supplier')
            ->orderBy('item_name')
            ->get();

        // Ambil 10 transaksi penjualan terakhir
        $recentSales = Transaction::with(['user', 'details.item'])
            ->where('transaction_type', 'penjualan')
            ->orderByDesc('id')
            ->take(10)
            ->get();

        return view('sales.index', compact('items', 'recentSales'));
    }

    /**
     * Menyimpan transaksi penjualan dan memotong stok barang.
     * Validasi mutlak: transaksi ditolak jika stok barang tidak mencukupi.
     */
    public function store(Request $request): RedirectResponse
    {
        $request->validate([
            'items' => ['required', 'array', 'min:1'],
            'items.*.id' => ['required', 'exists:items,id'],
            'items.*.quantity' => ['required', 'integer', 'min:1'],
        ], [
            'items.required' => 'Keranjang penjualan masih kosong.',
            'items.min' => 'Pilih minimal satu barang untuk melakukan transaksi penjualan.',
            'items.*.quantity.min' => 'Jumlah barang minimal 1.',
        ]);

        try {
            DB::transaction(function () use ($request) {
                $totalTransaction = 0;
                $detailsToInsert = [];

                foreach ($request->items as $row) {
                    $item = Item::lockForUpdate()->findOrFail($row['id']);
                    $qty = (int) $row['quantity'];

                    // Aturan Domain: Penjualan ditolak jika stok tidak mencukupi
                    if ($item->stock < $qty) {
                        throw new \Exception(
                            "Stok untuk barang '{$item->item_name}' tidak mencukupi. Stok tersedia: {$item->stock}, diminta: {$qty}."
                        );
                    }

                    $unitPrice = (float) $item->selling_price;
                    $subtotal = $qty * $unitPrice;
                    $totalTransaction += $subtotal;

                    $detailsToInsert[] = [
                        'item' => $item,
                        'quantity' => $qty,
                        'unit_price' => $unitPrice,
                        'subtotal' => $subtotal,
                    ];
                }

                // 1. Simpan Header Transaksi Penjualan
                $transaction = Transaction::create([
                    'date' => Carbon::today()->toDateString(),
                    'transaction_type' => 'penjualan',
                    'total' => $totalTransaction,
                    'user_id' => Auth::id(),
                ]);

                // 2. Simpan Detail Transaksi dan Kurangi Stok Barang
                foreach ($detailsToInsert as $detail) {
                    TransactionDetail::create([
                        'transaction_id' => $transaction->id,
                        'item_id' => $detail['item']->id,
                        'quantity' => $detail['quantity'],
                        'unit_price' => $detail['unit_price'],
                        'subtotal' => $detail['subtotal'],
                    ]);

                    $detail['item']->decrement('stock', $detail['quantity']);
                }
            });

            return redirect()->route('sales.index')->with('success', 'Transaksi penjualan berhasil disimpan dan stok telah diperbarui.');
        } catch (\Exception $e) {
            return back()->with('error', $e->getMessage())->withInput();
        }
    }
}
