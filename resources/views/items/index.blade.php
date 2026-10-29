@extends('layouts.app')

@section('title', 'Data Barang')
@section('page_title', 'Data Barang')

@section('content')
<div class="space-y-6">
    <!-- Bar Pencarian & Tombol Tambah -->
    <div class="bg-white rounded-lg border border-gray-200 shadow-sm p-5 flex flex-col sm:flex-row items-center justify-between gap-4">
        <form action="{{ route('items.index') }}" method="GET" class="w-full sm:w-80">
            <input type="text" name="q" value="{{ $search }}" placeholder="Cari kode atau nama barang..."
                class="w-full rounded-md border border-gray-300 px-3 py-2 text-sm text-gray-900 placeholder-gray-400 focus:border-blue-500 focus:outline-none focus:ring-1 focus:ring-blue-500">
        </form>

        <button type="button" onclick="openCreateModal()"
            class="w-full sm:w-auto px-4 py-2 bg-blue-600 hover:bg-blue-700 text-white text-sm font-medium rounded-md shadow-sm transition-colors whitespace-nowrap">
            + Tambah Barang Baru
        </button>
    </div>

    <!-- Tabel Master Data Barang -->
    <div class="bg-white rounded-lg border border-gray-200 shadow-sm overflow-hidden">
        <div class="overflow-x-auto">
            <table class="w-full text-sm text-left text-gray-700">
                <thead class="text-xs text-gray-500 uppercase bg-gray-50 border-b border-gray-200">
                    <tr>
                        <th class="py-3.5 px-4 w-12 text-center">No</th>
                        <th class="py-3.5 px-4 w-24">Kode</th>
                        <th class="py-3.5 px-4">Nama Barang</th>
                        <th class="py-3.5 px-4">Pemasok</th>
                        <th class="py-3.5 px-4 text-right w-32">Harga Beli</th>
                        <th class="py-3.5 px-4 text-right w-32">Harga Jual</th>
                        <th class="py-3.5 px-4 text-center w-24">Stok</th>
                        <th class="py-3.5 px-4 w-28 text-center">Aksi</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-200">
                    @forelse ($items as $item)
                        <tr class="hover:bg-gray-50 transition-colors">
                            <td class="py-3.5 px-4 text-center text-xs text-gray-500 font-mono">{{ $loop->iteration + ($items->currentPage() - 1) * $items->perPage() }}</td>
                            <td class="py-3.5 px-4 font-mono text-xs font-semibold text-gray-900">{{ $item->item_code }}</td>
                            <td class="py-3.5 px-4 font-medium text-gray-900">{{ $item->item_name }}</td>
                            <td class="py-3.5 px-4 text-xs text-gray-600">{{ $item->supplier->supplier_name ?? '-' }}</td>
                            <td class="py-3.5 px-4 text-right font-mono text-xs text-gray-700">
                                Rp {{ number_format($item->purchase_price, 0, ',', '.') }}
                            </td>
                            <td class="py-3.5 px-4 text-right font-mono text-xs font-semibold text-gray-900">
                                Rp {{ number_format($item->selling_price, 0, ',', '.') }}
                            </td>
                            <td class="py-3.5 px-4 text-center font-bold {{ $item->stock <= 0 ? 'text-red-600' : 'text-gray-900' }}">
                                {{ $item->stock }}
                            </td>
                            <td class="py-3.5 px-4 text-center">
                                <div class="flex items-center justify-center space-x-2">
                                    <button type="button"
                                        onclick="openEditModal({{ json_encode($item) }})"
                                        class="text-blue-600 hover:text-blue-800 text-xs font-medium">
                                        Ubah
                                    </button>
                                    <span class="text-gray-300">|</span>
                                    <form action="{{ route('items.destroy', $item->id) }}" method="POST"
                                        onsubmit="return confirm('Apakah Anda yakin ingin menghapus barang {{ addslashes($item->item_name) }}?')">
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
                            <td colspan="8" class="py-8 text-center text-gray-400 text-sm">
                                Tidak ada data barang ditemukan.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        @if ($items->hasPages())
            <div class="p-4 border-t border-gray-200">
                {{ $items->links() }}
            </div>
        @endif
    </div>
</div>

<!-- Modal Tambah Barang -->
<div id="createModal" class="fixed inset-0 z-50 hidden bg-gray-900/50 backdrop-blur-none flex items-center justify-center p-4">
    <div class="bg-white rounded-lg max-w-lg w-full border border-gray-200 shadow-lg p-6 max-h-[90vh] overflow-y-auto">
        <div class="flex items-center justify-between pb-3 border-b border-gray-200 mb-4">
            <h3 class="text-base font-semibold text-gray-900">Tambah Data Barang Baru</h3>
            <button type="button" onclick="closeCreateModal()" class="text-gray-400 hover:text-gray-600 text-lg font-bold">&times;</button>
        </div>

        <form action="{{ route('items.store') }}" method="POST" class="space-y-4">
            @csrf
            <div>
                <label for="item_name" class="block text-xs font-medium text-gray-700 mb-1">Nama Barang</label>
                <input type="text" name="item_name" id="item_name" required
                    class="w-full rounded-md border border-gray-300 px-3 py-2 text-sm text-gray-900 focus:border-blue-500 focus:outline-none"
                    placeholder="Nama lengkap sparepart">
            </div>

            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                <div>
                    <label for="purchase_price" class="block text-xs font-medium text-gray-700 mb-1">Harga Beli Awal (Rp)</label>
                    <input type="number" name="purchase_price" id="purchase_price" min="0" required
                        class="w-full rounded-md border border-gray-300 px-3 py-2 text-sm text-gray-900 focus:border-blue-500 focus:outline-none"
                        placeholder="0">
                </div>

                <div>
                    <label for="selling_price" class="block text-xs font-medium text-gray-700 mb-1">Harga Jual (Rp)</label>
                    <input type="number" name="selling_price" id="selling_price" min="0" required
                        class="w-full rounded-md border border-gray-300 px-3 py-2 text-sm text-gray-900 focus:border-blue-500 focus:outline-none"
                        placeholder="0">
                </div>
            </div>

            <div class="rounded-md bg-gray-50 border border-gray-200 p-3">
                <p class="text-xs text-gray-600">
                    Barang baru mulai dari stok 0. Stok pertama masuk lewat Pembelian (satu pintu stok).
                </p>
            </div>

            <div class="pt-3 border-t border-gray-200 flex items-center justify-end space-x-2">
                <button type="button" onclick="closeCreateModal()"
                    class="px-4 py-2 border border-gray-300 rounded-md text-sm text-gray-700 hover:bg-gray-50">
                    Batal
                </button>
                <button type="submit"
                    class="px-4 py-2 bg-blue-600 hover:bg-blue-700 text-white rounded-md text-sm font-medium">
                    Simpan Barang
                </button>
            </div>
        </form>
    </div>
</div>

<!-- Modal Ubah Barang -->
<div id="editModal" class="fixed inset-0 z-50 hidden bg-gray-900/50 backdrop-blur-none flex items-center justify-center p-4">
    <div class="bg-white rounded-lg max-w-lg w-full border border-gray-200 shadow-lg p-6 max-h-[90vh] overflow-y-auto">
        <div class="flex items-center justify-between pb-3 border-b border-gray-200 mb-4">
            <h3 class="text-base font-semibold text-gray-900">Ubah Data Barang</h3>
            <button type="button" onclick="closeEditModal()" class="text-gray-400 hover:text-gray-600 text-lg font-bold">&times;</button>
        </div>

        <form id="editForm" method="POST" class="space-y-4">
            @csrf
            @method('PUT')

            <div>
                <label for="edit_item_code" class="block text-xs font-medium text-gray-700 mb-1">Kode Barang (Otomatis)</label>
                <input type="text" name="item_code" id="edit_item_code" required readonly
                    class="w-full rounded-md border border-gray-300 bg-gray-100 text-gray-500 px-3 py-2 text-sm focus:outline-none cursor-not-allowed">
            </div>

            <div>
                <label for="edit_item_name" class="block text-xs font-medium text-gray-700 mb-1">Nama Barang</label>
                <input type="text" name="item_name" id="edit_item_name" required
                    class="w-full rounded-md border border-gray-300 px-3 py-2 text-sm text-gray-900 focus:border-blue-500 focus:outline-none">
            </div>

            <div>
                <label for="edit_supplier_id" class="block text-xs font-medium text-gray-700 mb-1">Pemasok (penugasan lewat sini)</label>
                <select name="supplier_id" id="edit_supplier_id" required
                    class="w-full rounded-md border border-gray-300 px-3 py-2 text-sm text-gray-900 focus:border-blue-500 focus:outline-none">
                    <option value="">Belum ditentukan</option>
                    @foreach ($suppliers as $supplier)
                        <option value="{{ $supplier->id }}">{{ $supplier->supplier_name }} ({{ $supplier->lead_time }} hari)</option>
                    @endforeach
                </select>
            </div>

            <div>
                <label for="edit_selling_price" class="block text-xs font-medium text-gray-700 mb-1">Harga Jual (Rp)</label>
                <input type="number" name="selling_price" id="edit_selling_price" min="0" required
                    class="w-full rounded-md border border-gray-300 px-3 py-2 text-sm text-gray-900 focus:border-blue-500 focus:outline-none">
            </div>

            <div class="rounded-md bg-gray-50 border border-gray-200 p-3">
                <p class="text-xs text-gray-600">
                    <strong>Catatan:</strong> Stok fisik dan harga beli terakhir diperbarui secara otomatis melalui modul Transaksi Penjualan dan Transaksi Pembelian.
                </p>
            </div>

            <div class="pt-3 border-t border-gray-200 flex items-center justify-end space-x-2">
                <button type="button" onclick="closeEditModal()"
                    class="px-4 py-2 border border-gray-300 rounded-md text-sm text-gray-700 hover:bg-gray-50">
                    Batal
                </button>
                <button type="submit"
                    class="px-4 py-2 bg-blue-600 hover:bg-blue-700 text-white rounded-md text-sm font-medium">
                    Simpan Perubahan
                </button>
            </div>
        </form>
    </div>
</div>
@endsection

@push('scripts')
<script>
    function openCreateModal() {
        document.getElementById('createModal').classList.remove('hidden');
    }

    function closeCreateModal() {
        document.getElementById('createModal').classList.add('hidden');
    }

    function openEditModal(item) {
        const form = document.getElementById('editForm');
        form.action = '/barang/' + item.id;

        document.getElementById('edit_item_code').value = item.item_code;
        document.getElementById('edit_item_name').value = item.item_name;
        document.getElementById('edit_supplier_id').value = item.supplier_id ?? '';
        document.getElementById('edit_selling_price').value = Math.round(item.selling_price);

        document.getElementById('editModal').classList.remove('hidden');
    }

    function closeEditModal() {
        document.getElementById('editModal').classList.add('hidden');
    }
</script>
@endpush
