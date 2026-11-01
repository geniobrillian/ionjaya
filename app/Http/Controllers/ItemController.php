<?php

namespace App\Http\Controllers;

use App\Models\Item;
use App\Models\Supplier;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class ItemController extends Controller
{
    /**
     * Menampilkan daftar master data barang dan filter pencarian.
     */
    public function index(Request $request): View
    {
        $search = $request->query('q');

        $query = Item::with('suppliers')->orderBy('item_name');

        if (! empty($search)) {
            $query->where(function ($q) use ($search) {
                $q->where('item_name', 'like', "%{$search}%")
                  ->orWhere('item_code', 'like', "%{$search}%");
            });
        }

        $items = $query->paginate(15)->withQueryString();

        return view('items.index', compact('items', 'search'));
    }

    /**
     * Menyimpan data barang baru ke basis data.
     */
    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'item_code' => ['nullable', 'string', 'max:20', 'unique:items,item_code'],
            'item_name' => ['required', 'string', 'max:100'],
            // Tanpa pemasok saat tambah barang: relasi pemasok ditugaskan
            // lewat halaman Detail Barang (bisa banyak, bisa nol).
            'purchase_price' => ['required', 'numeric', 'min:0'],
            'selling_price' => ['required', 'numeric', 'min:0'],
            // Stok awal dihapus (1 pintu ketat): barang baru mulai dari stok 0,
            // stok pertama hanya masuk lewat Pembelian.
        ], [
            'item_code.unique' => 'Kode barang sudah digunakan.',
            'item_name.required' => 'Nama barang wajib diisi.',
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
     * Menampilkan halaman Detail Barang: info barang + daftar
     * pemasoknya (dengan penanda Utama) + form tambah pemasok.
     * Dibuka dengan klik nama barang di tabel Data Barang.
     */
    public function show(Item $item): View
    {
        $item->load('suppliers');
        $attachedIds = $item->suppliers->pluck('id')->all();
        $availableSuppliers = Supplier::whereNotIn('id', $attachedIds)
            ->orderBy('supplier_name')
            ->get();

        return view('items.show', compact('item', 'availableSuppliers'));
    }

    /**
     * Menambahkan relasi pemasok ke barang.
     * Pemasok pertama otomatis menjadi Pemasok Utama; bila barang
     * sudah punya pemasok, Utama hanya berubah bila checkbox
     * "Jadikan pemasok utama" dicentang (Utama lama dicabut,
     * maks 1 Utama selalu terjaga).
     */
    public function attachSupplier(Request $request, Item $item): RedirectResponse
    {
        $validated = $request->validate([
            'supplier_id' => ['required', 'exists:suppliers,id'],
            'pemasok_utama' => ['nullable', 'boolean'],
        ], [
            'supplier_id.required' => 'Pilih pemasok yang akan ditambahkan.',
            'supplier_id.exists' => 'Pemasok yang dipilih tidak ditemukan.',
        ]);

        $supplierId = (int) $validated['supplier_id'];

        if ($item->suppliers()->where('suppliers.id', $supplierId)->exists()) {
            return back()->with('error', 'Pemasok tersebut sudah terdaftar untuk barang ini.');
        }

        DB::transaction(function () use ($item, $supplierId, $request) {
            $isFirst = $item->suppliers()->count() === 0;
            $utama = $isFirst ? true : $request->boolean('pemasok_utama');

            if ($utama) {
                DB::table('barang_pemasok')
                    ->where('barang_id', $item->id)
                    ->update(['pemasok_utama' => false]);
            }

            $item->suppliers()->attach($supplierId, ['pemasok_utama' => $utama]);
        });

        return redirect()->route('items.show', $item->id)->with('success', 'Pemasok berhasil ditambahkan untuk barang ini.');
    }

    /**
     * Menghapus relasi pemasok dari barang. Boleh menghapus Utama
     * atau satu-satunya relasi: barang boleh 0 pemasok, dan tidak
     * ada promosi otomatis (Utama baru dipilih manual oleh owner).
     */
    public function detachSupplier(Item $item, Supplier $supplier): RedirectResponse
    {
        if (! $item->suppliers()->where('suppliers.id', $supplier->id)->exists()) {
            return back()->with('error', 'Pemasok tersebut tidak terdaftar untuk barang ini.');
        }

        $item->suppliers()->detach($supplier->id);

        return redirect()->route('items.show', $item->id)->with('success', "Pemasok '{$supplier->supplier_name}' berhasil dihapus dari barang ini.");
    }

    /**
     * Menjadikan satu pemasok sebagai Pemasok Utama barang ini
     * (Utama lama dicabut dalam satu transaksi).
     */
    public function setPrimarySupplier(Item $item, Supplier $supplier): RedirectResponse
    {
        if (! $item->suppliers()->where('suppliers.id', $supplier->id)->exists()) {
            return back()->with('error', 'Pemasok tersebut tidak terdaftar untuk barang ini.');
        }

        DB::transaction(function () use ($item, $supplier) {
            DB::table('barang_pemasok')
                ->where('barang_id', $item->id)
                ->update(['pemasok_utama' => false]);
            DB::table('barang_pemasok')
                ->where('barang_id', $item->id)
                ->where('pemasok_id', $supplier->id)
                ->update(['pemasok_utama' => true]);
        });

        return redirect()->route('items.show', $item->id)->with('success', "Pemasok '{$supplier->supplier_name}' kini menjadi Pemasok Utama barang ini.");
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
            // Tanpa pemasok di form ubah: relasi pemasok dikelola lewat
            // halaman Detail Barang (bisa banyak, bisa nol).
            'selling_price' => ['required', 'numeric', 'min:0'],
            // Harga beli diubah hanya lewat form ubah (1 pintu harga).
            'purchase_price' => ['required', 'numeric', 'min:0'],
        ], [
            'item_code.required' => 'Kode barang wajib diisi.',
            'item_code.unique' => 'Kode barang sudah digunakan.',
            'item_name.required' => 'Nama barang wajib diisi.',
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
