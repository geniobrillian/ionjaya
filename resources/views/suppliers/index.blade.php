@extends('layouts.app')

@section('title', 'Data Pemasok')
@section('page_title', 'Data Pemasok')

@section('content')
<div class="space-y-6">
    <!-- Bar Pencarian & Tombol Tambah -->
    <div class="bg-white rounded-lg border border-gray-200 shadow-sm p-5 flex flex-col sm:flex-row items-center justify-between gap-4">
        <form action="{{ route('suppliers.index') }}" method="GET" class="w-full sm:w-80">
            <input type="text" name="q" value="{{ $search }}" placeholder="Cari nama, kode, atau telepon..."
                class="w-full rounded-md border border-gray-300 px-3 py-2 text-sm text-gray-900 placeholder-gray-400 focus:border-blue-500 focus:outline-none focus:ring-1 focus:ring-blue-500">
        </form>

        <button type="button" onclick="openCreateModal()"
            class="w-full sm:w-auto px-4 py-2 bg-blue-600 hover:bg-blue-700 text-white text-sm font-medium rounded-md shadow-sm transition-colors whitespace-nowrap">
            + Tambah Pemasok
        </button>
    </div>

    <!-- Tabel Data Pemasok -->
    <div class="bg-white rounded-lg border border-gray-200 shadow-sm overflow-hidden">
        <div class="overflow-x-auto">
            <table class="w-full text-sm text-left text-gray-700">
                <thead class="text-xs text-gray-500 uppercase bg-gray-50 border-b border-gray-200">
                    <tr>
                        <th class="py-3.5 px-4 w-12 text-center">No</th>
                        <th class="py-3.5 px-4 w-28">Kode</th>
                        <th class="py-3.5 px-4">Nama Pemasok</th>
                        <th class="py-3.5 px-4">Alamat</th>
                        <th class="py-3.5 px-4 w-36">Telepon</th>
                        <th class="py-3.5 px-4 w-32 text-center">Lead Time</th>
                        <th class="py-3.5 px-4 w-28 text-center">Jumlah Item</th>
                        <th class="py-3.5 px-4 w-32 text-center">Aksi</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-200">
                    @forelse ($suppliers as $supplier)
                        <tr class="hover:bg-gray-50 transition-colors">
                            <td class="py-3.5 px-4 text-center text-xs text-gray-500 font-mono">{{ $loop->iteration + ($suppliers->currentPage() - 1) * $suppliers->perPage() }}</td>
                            <td class="py-3.5 px-4 font-mono text-xs font-semibold text-gray-900">{{ $supplier->supplier_code }}</td>
                            <td class="py-3.5 px-4 font-medium text-gray-900">{{ $supplier->supplier_name }}</td>
                            <td class="py-3.5 px-4 text-xs text-gray-600">{{ $supplier->address ?? '-' }}</td>
                            <td class="py-3.5 px-4 text-xs text-gray-600 font-mono">{{ $supplier->phone ?? '-' }}</td>
                            <td class="py-3.5 px-4 text-center text-xs font-semibold text-gray-800">
                                {{ $supplier->lead_time }} hari
                            </td>
                            <td class="py-3.5 px-4 text-center text-xs text-gray-600 font-medium">
                                {{ $supplier->items_count }} item
                            </td>
                            <td class="py-3.5 px-4 text-center">
                                <div class="flex items-center justify-center space-x-2">
                                    <button type="button"
                                        onclick="openEditModal({{ json_encode($supplier) }})"
                                        class="text-blue-600 hover:text-blue-800 text-xs font-medium">
                                        Ubah
                                    </button>
                                    <span class="text-gray-300">|</span>
                                    <form action="{{ route('suppliers.destroy', $supplier->id) }}" method="POST"
                                        onsubmit="return confirm('Apakah Anda yakin ingin menghapus pemasok {{ addslashes($supplier->supplier_name) }}?')">
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
                                Tidak ada data pemasok ditemukan.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        @if ($suppliers->hasPages())
            <div class="p-4 border-t border-gray-200">
                {{ $suppliers->links() }}
            </div>
        @endif
    </div>
</div>

<!-- Modal Tambah Pemasok -->
<div id="createModal" class="fixed inset-0 z-50 hidden bg-gray-900/50 backdrop-blur-none flex items-center justify-center p-4">
    <div class="bg-white rounded-lg max-w-lg w-full border border-gray-200 shadow-lg p-6">
        <div class="flex items-center justify-between pb-3 border-b border-gray-200 mb-4">
            <h3 class="text-base font-semibold text-gray-900">Tambah Data Pemasok</h3>
            <button type="button" onclick="closeCreateModal()" class="text-gray-400 hover:text-gray-600 text-lg font-bold">&times;</button>
        </div>

        <form action="{{ route('suppliers.store') }}" method="POST" class="space-y-4">
            @csrf
            <div>
                <label for="supplier_name" class="block text-xs font-medium text-gray-700 mb-1">Nama Pemasok</label>
                <input type="text" name="supplier_name" id="supplier_name" required
                    class="w-full rounded-md border border-gray-300 px-3 py-2 text-sm text-gray-900 focus:border-blue-500 focus:outline-none"
                    placeholder="Nama distributor atau toko">
            </div>

            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                <div>
                    <label for="phone" class="block text-xs font-medium text-gray-700 mb-1">No Telepon</label>
                    <input type="text" name="phone" id="phone"
                        class="w-full rounded-md border border-gray-300 px-3 py-2 text-sm text-gray-900 focus:border-blue-500 focus:outline-none"
                        placeholder="Contoh: 08123456789">
                </div>

                <div>
                    <label for="lead_time" class="block text-xs font-medium text-gray-700 mb-1">Waktu Tunggu / Lead Time (Hari)</label>
                    <input type="number" name="lead_time" id="lead_time" min="1" value="1" required
                        class="w-full rounded-md border border-gray-300 px-3 py-2 text-sm text-gray-900 focus:border-blue-500 focus:outline-none">
                    <span class="text-[10px] text-gray-500 mt-0.5 block">Minimal 1 hari (dipakai rumus SS & ROP).</span>
                </div>
            </div>

            <div>
                <label for="address" class="block text-xs font-medium text-gray-700 mb-1">Alamat</label>
                <textarea name="address" id="address" rows="2"
                    class="w-full rounded-md border border-gray-300 px-3 py-2 text-sm text-gray-900 focus:border-blue-500 focus:outline-none"
                    placeholder="Alamat kantor atau gudang pemasok"></textarea>
            </div>

            <div class="pt-3 border-t border-gray-200 flex items-center justify-end space-x-2">
                <button type="button" onclick="closeCreateModal()"
                    class="px-4 py-2 border border-gray-300 rounded-md text-sm text-gray-700 hover:bg-gray-50">
                    Batal
                </button>
                <button type="submit"
                    class="px-4 py-2 bg-blue-600 hover:bg-blue-700 text-white rounded-md text-sm font-medium">
                    Simpan Pemasok
                </button>
            </div>
        </form>
    </div>
</div>

<!-- Modal Ubah Pemasok -->
<div id="editModal" class="fixed inset-0 z-50 hidden bg-gray-900/50 backdrop-blur-none flex items-center justify-center p-4">
    <div class="bg-white rounded-lg max-w-lg w-full border border-gray-200 shadow-lg p-6">
        <div class="flex items-center justify-between pb-3 border-b border-gray-200 mb-4">
            <h3 class="text-base font-semibold text-gray-900">Ubah Data Pemasok</h3>
            <button type="button" onclick="closeEditModal()" class="text-gray-400 hover:text-gray-600 text-lg font-bold">&times;</button>
        </div>

        <form id="editForm" method="POST" class="space-y-4">
            @csrf
            @method('PUT')

            <div>
                <label for="edit_supplier_code" class="block text-xs font-medium text-gray-700 mb-1">Kode Pemasok</label>
                <input type="text" name="supplier_code" id="edit_supplier_code" required
                    class="w-full rounded-md border border-gray-300 px-3 py-2 text-sm text-gray-900 focus:border-blue-500 focus:outline-none">
            </div>

            <div>
                <label for="edit_supplier_name" class="block text-xs font-medium text-gray-700 mb-1">Nama Pemasok</label>
                <input type="text" name="supplier_name" id="edit_supplier_name" required
                    class="w-full rounded-md border border-gray-300 px-3 py-2 text-sm text-gray-900 focus:border-blue-500 focus:outline-none">
            </div>

            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                <div>
                    <label for="edit_phone" class="block text-xs font-medium text-gray-700 mb-1">No Telepon</label>
                    <input type="text" name="phone" id="edit_phone"
                        class="w-full rounded-md border border-gray-300 px-3 py-2 text-sm text-gray-900 focus:border-blue-500 focus:outline-none">
                </div>

                <div>
                    <label for="edit_lead_time" class="block text-xs font-medium text-gray-700 mb-1">Waktu Tunggu / Lead Time (Hari)</label>
                    <input type="number" name="lead_time" id="edit_lead_time" min="1" required
                        class="w-full rounded-md border border-gray-300 px-3 py-2 text-sm text-gray-900 focus:border-blue-500 focus:outline-none">
                </div>
            </div>

            <div>
                <label for="edit_address" class="block text-xs font-medium text-gray-700 mb-1">Alamat</label>
                <textarea name="address" id="edit_address" rows="2"
                    class="w-full rounded-md border border-gray-300 px-3 py-2 text-sm text-gray-900 focus:border-blue-500 focus:outline-none"></textarea>
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

    function openEditModal(supplier) {
        const form = document.getElementById('editForm');
        form.action = '/pemasok/' + supplier.id;

        document.getElementById('edit_supplier_code').value = supplier.supplier_code;
        document.getElementById('edit_supplier_name').value = supplier.supplier_name;
        document.getElementById('edit_phone').value = supplier.phone || '';
        document.getElementById('edit_lead_time').value = supplier.lead_time || 1;
        document.getElementById('edit_address').value = supplier.address || '';

        document.getElementById('editModal').classList.remove('hidden');
    }

    function closeEditModal() {
        document.getElementById('editModal').classList.add('hidden');
    }
</script>
@endpush
