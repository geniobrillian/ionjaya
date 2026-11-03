@extends('layouts.app')

@section('title', 'Detail Barang')
@section('page_title', 'Detail Barang')

@section('content')
<div class="space-y-6">
    <div>
        <a href="{{ route('items.index') }}" class="text-sm text-blue-600 hover:text-blue-800 font-medium">&larr; Kembali ke Data Barang</a>
    </div>

    <!-- Info Barang -->
    <div class="bg-white rounded-lg border border-gray-200 shadow-sm p-5">
        <h2 class="text-base font-semibold text-gray-900 mb-4">{{ $item->item_name }}</h2>
        <dl class="grid grid-cols-1 sm:grid-cols-3 gap-4 text-sm">
            <div>
                <dt class="text-xs text-gray-500 uppercase font-semibold">Kode Barang</dt>
                <dd class="font-mono text-gray-900 mt-0.5">{{ $item->item_code }}</dd>
            </div>
            <div>
                <dt class="text-xs text-gray-500 uppercase font-semibold">Harga Beli</dt>
                <dd class="font-mono text-gray-900 mt-0.5">Rp {{ number_format($item->purchase_price, 0, ',', '.') }}</dd>
            </div>
            <div>
                <dt class="text-xs text-gray-500 uppercase font-semibold">Harga Jual</dt>
                <dd class="font-mono text-gray-900 mt-0.5">Rp {{ number_format($item->selling_price, 0, ',', '.') }}</dd>
            </div>
        </dl>
        <p class="text-xs text-gray-500 mt-4">
            Pemasok utama digunakan sebagai pilihan utama saat pembelian.
        </p>
    </div>

    <!-- Daftar Pemasok Barang -->
    <div class="bg-white rounded-lg border border-gray-200 shadow-sm overflow-hidden">
        <div class="p-5 pb-3 border-b border-gray-200">
            <h3 class="text-base font-semibold text-gray-900">Pemasok</h3>
            <p class="text-xs text-gray-500 mt-0.5">Satu barang bisa punya banyak pemasok; paling banyak satu Pemasok Utama.</p>
        </div>
        <div class="overflow-x-auto">
            <table class="w-full text-sm text-left text-gray-700">
                <thead class="text-xs text-gray-500 uppercase bg-gray-50 border-b border-gray-200">
                    <tr>
                        <th class="py-3 px-4">Pemasok</th>
                        <th class="py-3 px-4">Lama Pengiriman</th>
                        <th class="py-3 px-4">Status</th>
                        <th class="py-3 px-4 text-center">Aksi</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-200">
                    @forelse ($item->suppliers as $supplier)
                        <tr class="hover:bg-gray-50 transition-colors">
                            <td class="py-3 px-4">
                                <span class="font-medium text-gray-900">{{ $supplier->supplier_name }}</span>
                                <span class="text-xs text-gray-500 block font-mono">{{ $supplier->supplier_code }}</span>
                            </td>
                            <td class="py-3 px-4 text-xs text-gray-600">{{ $supplier->lead_time }} hari</td>
                            <td class="py-3 px-4">
                                @if ($supplier->pivot->pemasok_utama)
                                    <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-semibold bg-yellow-100 text-yellow-800">&#9733; Utama</span>
                                @else
                                    <span class="text-xs text-gray-400">—</span>
                                @endif
                            </td>
                            <td class="py-3 px-4 text-center">
                                <div class="flex items-center justify-center space-x-2">
                                    @if (! $supplier->pivot->pemasok_utama)
                                        <form action="{{ route('items.suppliers.primary', [$item->id, $supplier->id]) }}" method="POST">
                                            @csrf
                                            <button type="submit" class="text-blue-600 hover:text-blue-800 text-xs font-medium">
                                                Jadikan Utama
                                            </button>
                                        </form>
                                        <span class="text-gray-300">|</span>
                                    @endif
                                    <form action="{{ route('items.suppliers.detach', [$item->id, $supplier->id]) }}" method="POST"
                                        onsubmit="return confirm('Hapus pemasok {{ addslashes($supplier->supplier_name) }} dari barang ini?')">
                                        @csrf
                                        @method('DELETE')
                                        <button type="submit" class="text-red-600 hover:text-red-800 text-xs font-medium">
                                            Hapus
                                        </button>
                                    </form>
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="4" class="py-8 text-center text-gray-400 text-sm">
                                Barang ini belum punya pemasok. Tambahkan di bawah — barang tanpa pemasok tidak bisa dibeli sampai punya pemasok.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    <!-- Form Tambah Pemasok -->
    <div class="bg-white rounded-lg border border-gray-200 shadow-sm p-5">
        <h3 class="text-base font-semibold text-gray-900 mb-4">Tambah Pemasok</h3>
        @if ($availableSuppliers->isEmpty())
            <p class="text-sm text-gray-500">Semua pemasok sudah terdaftar untuk barang ini.</p>
        @else
            <form action="{{ route('items.suppliers.attach', $item->id) }}" method="POST" class="space-y-4">
                @csrf
                <div>
                    <label for="supplier_id" class="block text-xs font-medium text-gray-700 mb-1">Pemasok</label>
                    <select name="supplier_id" id="supplier_id" required
                        class="w-full sm:w-80 rounded-md border border-gray-300 px-3 py-2 text-sm text-gray-900 focus:border-blue-500 focus:outline-none">
                        <option value="">== Pilih Pemasok ==</option>
                        @foreach ($availableSuppliers as $supplier)
                            <option value="{{ $supplier->id }}">{{ $supplier->supplier_name }} ({{ $supplier->supplier_code }})</option>
                        @endforeach
                    </select>
                </div>
                @if ($item->suppliers->isNotEmpty())
                    <div class="flex items-center gap-2">
                        <input type="checkbox" name="pemasok_utama" id="pemasok_utama" value="1"
                            class="rounded border-gray-300 text-blue-600 focus:ring-blue-500">
                        <label for="pemasok_utama" class="text-sm text-gray-700">Jadikan pemasok utama</label>
                    </div>
                @else
                    <p class="text-xs text-gray-500">Pemasok pertama otomatis menjadi Pemasok Utama.</p>
                @endif
                <div>
                    <button type="submit"
                        class="px-4 py-2 bg-blue-600 hover:bg-blue-700 text-white rounded-md text-sm font-medium">
                        Tambah Pemasok
                    </button>
                </div>
            </form>
        @endif
    </div>
</div>
@endsection
