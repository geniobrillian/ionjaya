<?php

namespace App\Http\Controllers;

use App\Models\Supplier;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class SupplierController extends Controller
{
    /**
     * Menampilkan daftar master data pemasok beserta pencarian.
     */
    public function index(Request $request): View
    {
        $search = $request->query('q');

        $query = Supplier::withCount('items')->orderBy('supplier_name');

        if (! empty($search)) {
            $query->where(function ($q) use ($search) {
                $q->where('supplier_name', 'like', "%{$search}%")
                  ->orWhere('supplier_code', 'like', "%{$search}%")
                  ->orWhere('phone', 'like', "%{$search}%");
            });
        }

        $suppliers = $query->paginate(15)->withQueryString();

        return view('suppliers.index', compact('suppliers', 'search'));
    }

    /**
     * Menyimpan data pemasok baru.
     */
    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'supplier_code' => ['required', 'string', 'max:20', 'unique:suppliers,supplier_code'],
            'supplier_name' => ['required', 'string', 'max:100'],
            'address' => ['nullable', 'string'],
            'phone' => ['nullable', 'string', 'max:20'],
            'lead_time' => ['required', 'integer', 'min:1'],
        ], [
            'supplier_code.required' => 'Kode pemasok wajib diisi.',
            'supplier_code.unique' => 'Kode pemasok sudah digunakan.',
            'supplier_name.required' => 'Nama pemasok wajib diisi.',
            'lead_time.required' => 'Waktu tunggu (lead time) wajib diisi.',
            'lead_time.min' => 'Waktu tunggu pengiriman minimal 1 hari.',
        ]);

        Supplier::create($validated);

        return redirect()->route('suppliers.index')->with('success', 'Data pemasok berhasil ditambahkan.');
    }

    /**
     * Memperbarui data pemasok.
     */
    public function update(Request $request, Supplier $supplier): RedirectResponse
    {
        $validated = $request->validate([
            'supplier_code' => ['required', 'string', 'max:20', 'unique:suppliers,supplier_code,' . $supplier->id],
            'supplier_name' => ['required', 'string', 'max:100'],
            'address' => ['nullable', 'string'],
            'phone' => ['nullable', 'string', 'max:20'],
            'lead_time' => ['required', 'integer', 'min:1'],
        ], [
            'supplier_code.required' => 'Kode pemasok wajib diisi.',
            'supplier_code.unique' => 'Kode pemasok sudah digunakan.',
            'supplier_name.required' => 'Nama pemasok wajib diisi.',
            'lead_time.required' => 'Waktu tunggu (lead time) wajib diisi.',
            'lead_time.min' => 'Waktu tunggu pengiriman minimal 1 hari.',
        ]);

        $supplier->update($validated);

        return redirect()->route('suppliers.index')->with('success', 'Data pemasok berhasil diperbarui.');
    }

    /**
     * Menghapus data pemasok.
     * Validasi: Ditolak jika pemasok masih memiliki data barang terhubung.
     */
    public function destroy(Supplier $supplier): RedirectResponse
    {
        if ($supplier->items()->exists()) {
            return back()->with('error', "Pemasok '{$supplier->supplier_name}' tidak dapat dihapus karena masih menjadi pemasok untuk barang aktif.");
        }

        $supplier->delete();

        return redirect()->route('suppliers.index')->with('success', 'Data pemasok berhasil dihapus.');
    }
}
