<?php

namespace App\Http\Controllers;

use App\Models\Item;
use App\Models\Supplier;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ItemController extends Controller
{
    /**
     * Menampilkan daftar master data barang dan filter pencarian.
     */
    public function index(Request $request): View
    {
        $search = $request->query('q');

        $query = Item::with('supplier')->orderBy('item_name');

        if (! empty($search)) {
            $query->where(function ($q) use ($search) {
                $q->where('item_name', 'like', "%{$search}%")
                  ->orWhere('item_code', 'like', "%{$search}%");
            });
        }

        $items = $query->paginate(15)->withQueryString();
        $suppliers = Supplier::orderBy('supplier_name')->get();

        return view('items.index', compact('items', 'suppliers', 'search'));
    }

    /**
     * Menyimpan data barang baru ke basis data.
     */
    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'item_code' => ['nullable', 'string', 'max:20', 'unique:items,item_code'],
            'item_name' => ['required', 'string', 'max:100'],
            // Pemasok opsional saat tambah barang (ditugaskan lewat Ubah Barang).
            'supplier_id' => ['nullable', 'exists:suppliers,id'],
            'purchase_price' => ['required', 'numeric', 'min:0'],
            'selling_price' => ['required', 'numeric', 'min:0'],
            // Stok awal dihapus (1 pintu ketat): barang baru mulai dari stok 0,
            // stok pertama hanya masuk lewat Pembelian.
        ], [
            'item_code.unique' => 'Kode barang sudah digunakan.',
            'item_name.required' => 'Nama barang wajib diisi.',
            'supplier_id.exists' => 'Pemasok yang dipilih tidak valid.',
            'purchase_price.required' => 'Harga beli wajib diisi.',
            'selling_price.required' => 'Harga jual wajib diisi.',
        ]);

        if (empty($validated['item_code'])) {
            $validated['item_code'] = Item::generateNextCode();
        }

        Item::create($validated);

        return redirect()->route('items.index')->with('success', 'Data barang baru berhasil ditambahkan.');
    }

    /**
     * Memperbarui data barang.
     * Catatan: Jumlah stok fisik tidak dapat diedit langsung lewat form ini
     * (stok hanya boleh berubah melalui transaksi penjualan/pembelian).
     */
    public function update(Request $request, Item $item): RedirectResponse
    {
        $validated = $request->validate([
            'item_code' => ['required', 'string', 'max:20', 'unique:items,item_code,' . $item->id],
            'item_name' => ['required', 'string', 'max:100'],
            // Pemasok wajib diisi di form ubah: disinilah penugasan pemasok dilakukan.
            'supplier_id' => ['required', 'exists:suppliers,id'],
            'selling_price' => ['required', 'numeric', 'min:0'],
            // Harga beli diubah hanya lewat form ubah (1 pintu harga).
            'purchase_price' => ['required', 'numeric', 'min:0'],
        ], [
            'item_code.required' => 'Kode barang wajib diisi.',
            'item_code.unique' => 'Kode barang sudah digunakan.',
            'item_name.required' => 'Nama barang wajib diisi.',
            'supplier_id.required' => 'Pemasok wajib dipilih.',
            'selling_price.required' => 'Harga jual wajib diisi.',
            'purchase_price.required' => 'Harga beli wajib diisi.',
        ]);

        $item->update($validated);

        return redirect()->route('items.index')->with('success', 'Data barang berhasil diperbarui.');
    }

    /**
     * Menghapus data barang.
     * Validasi: Ditolak jika barang sudah memiliki catatan riwayat transaksi.
     */
    public function destroy(Item $item): RedirectResponse
    {
        if ($item->transactionDetails()->exists()) {
            return back()->with('error', "Barang '{$item->item_name}' tidak dapat dihapus karena telah memiliki catatan riwayat transaksi penjualan/pembelian.");
        }

        $item->delete();

        return redirect()->route('items.index')->with('success', 'Data barang berhasil dihapus.');
    }
}
