@extends('layouts.app')

@section('title', 'Stok Barang')
@section('page_title', 'Stok Barang')

@section('content')
<div class="space-y-6">
    <!-- Banner Peringatan Stok Menipis -->
    @if ($lowStockCount > 0)
        <div class="bg-red-50 border border-red-200 p-4 rounded-lg shadow-sm flex flex-col sm:flex-row sm:items-center justify-between gap-3">
            <div>
                <p class="text-sm font-bold text-red-900">
                    Pemberitahuan Persediaan
                </p>
                <p class="text-xs text-red-700 mt-0.5">
                    Terdapat <strong>{{ $lowStockCount }} jenis barang</strong> yang stoknya telah mencapai atau berada di bawah batas pemesanan. Segera lakukan pengadaan.
                </p>
            </div>
            <a href="{{ route('recommendations.index') }}"
                class="w-full sm:w-auto px-4 py-2 bg-red-600 hover:bg-red-700 text-white text-xs font-semibold rounded-md shadow-sm transition-colors text-center whitespace-nowrap shrink-0">
                Lihat Rekomendasi
            </a>
        </div>
    @endif

    <!-- Kartu Filter & Pencarian -->
    <div class="bg-white rounded-lg border border-gray-200 shadow-sm p-4 sm:p-5">
        <form action="{{ route('stock.index') }}" method="GET" class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-12 gap-4 items-end">
            <!-- Pencarian Teks (5 cols) -->
            <div class="sm:col-span-2 lg:col-span-5">
                <label for="q" class="block text-xs font-semibold text-gray-600 mb-1">Cari Barang</label>
                <input type="text" name="q" id="q" value="{{ $search }}"
                    placeholder="Ketik kode atau nama sparepart..."
                    class="w-full rounded-md border border-gray-300 px-3 py-2 text-sm text-gray-900 placeholder-gray-400 focus:border-blue-500 focus:outline-none focus:ring-1 focus:ring-blue-500">
            </div>

            <!-- Filter Kategori FSN (3 cols) -->
            <div class="sm:col-span-1 lg:col-span-3">
                <label for="category" class="block text-xs font-semibold text-gray-600 mb-1">Kategori FSN</label>
                <select name="category" id="category"
                    class="w-full rounded-md border border-gray-300 px-3 py-2 text-sm text-gray-900 focus:border-blue-500 focus:outline-none focus:ring-1 focus:ring-blue-500">
                    <option value="">Semua Kategori</option>
                    <option value="F" {{ $categoryFilter === 'F' ? 'selected' : '' }}>Fast Moving (F)</option>
                    <option value="S" {{ $categoryFilter === 'S' ? 'selected' : '' }}>Slow Moving (S)</option>
                    <option value="N" {{ $categoryFilter === 'N' ? 'selected' : '' }}>Non Moving (N)</option>
                </select>
            </div>

            <!-- Filter Status Stok (4 cols) -->
            <div class="sm:col-span-1 lg:col-span-4">
                <label class="block text-xs font-semibold text-gray-600 mb-1">Status Ketersediaan</label>
                <div class="flex flex-wrap items-center gap-1.5">
                    <a href="{{ route('stock.index', ['q' => $search, 'category' => $categoryFilter]) }}"
                        class="px-2.5 sm:px-3 py-2 text-xs font-medium rounded-md border transition-colors {{ empty($statusFilter) ? 'bg-gray-800 text-white border-gray-800' : 'bg-white text-gray-700 border-gray-300 hover:bg-gray-50' }}">
                        Semua ({{ $totalItems }})
                    </a>
                    <a href="{{ route('stock.index', ['q' => $search, 'category' => $categoryFilter, 'status' => 'menipis']) }}"
                        class="px-2.5 sm:px-3 py-2 text-xs font-medium rounded-md border transition-colors {{ $statusFilter === 'menipis' ? 'bg-red-600 text-white border-red-600' : 'bg-white text-red-700 border-gray-300 hover:bg-red-50' }}">
                        Menipis ({{ $lowStockCount }})
                    </a>
                    <a href="{{ route('stock.index', ['q' => $search, 'category' => $categoryFilter, 'status' => 'aman']) }}"
                        class="px-2.5 sm:px-3 py-2 text-xs font-medium rounded-md border transition-colors {{ $statusFilter === 'aman' ? 'bg-green-700 text-white border-green-700' : 'bg-white text-green-700 border-gray-300 hover:bg-green-50' }}">
                        Aman ({{ $safeStockCount }})
                    </a>
                </div>
            </div>
        </form>
    </div>

    <!-- Tabel Data Stok Barang -->
    <div class="bg-white rounded-lg border border-gray-200 shadow-sm overflow-hidden">
        <div class="overflow-x-auto">
            <table class="w-full text-sm text-left text-gray-700">
                <thead class="text-xs text-gray-500 uppercase bg-gray-50 border-b border-gray-200">
                    <tr>
                        <th class="py-3.5 px-4 w-12 text-center">No</th>
                        <th class="py-3.5 px-4 w-28">Kode</th>
                        <th class="py-3.5 px-4">Nama Barang</th>
                        <th class="py-3.5 px-4 w-24">Satuan</th>
                        <th class="py-3.5 px-4 w-32 text-center">Kategori FSN</th>
                        <th class="py-3.5 px-4 text-center w-32">Batas Pesan</th>
                        <th class="py-3.5 px-4 text-center w-28">Stok Fisik</th>
                        <th class="py-3.5 px-4 text-center w-32">Status</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-200">
                    @forelse ($items as $index => $item)
                        @php
                            $isLow = $item->is_low_stock;
                        @endphp
                        <tr class="{{ $isLow ? 'bg-red-50/60 hover:bg-red-50' : 'hover:bg-gray-50' }} transition-colors">
                            <td class="py-3 px-4 text-center text-xs text-gray-500 font-mono">{{ $loop->iteration }}</td>
                            <td class="py-3 px-4 font-mono text-xs font-semibold text-gray-900">{{ $item->item_code }}</td>
                            <td class="py-3 px-4">
                                <div class="font-medium text-gray-900">{{ $item->item_name }}</div>
                                <div class="text-xs text-gray-500">{{ $item->supplier->supplier_name ?? '-' }}</div>
                            </td>
                            <td class="py-3 px-4 text-xs text-gray-600">{{ $item->unit }}</td>
                            <td class="py-3 px-4 text-center">
                                @if ($item->fsn_category === 'F')
                                    <span class="px-2.5 py-0.5 rounded-full text-xs font-semibold bg-green-100 text-green-800">
                                        Fast Moving
                                    </span>
                                @elseif ($item->fsn_category === 'S')
                                    <span class="px-2.5 py-0.5 rounded-full text-xs font-semibold bg-yellow-100 text-yellow-800">
                                        Slow Moving
                                    </span>
                                @elseif ($item->fsn_category === 'N')
                                    <span class="px-2.5 py-0.5 rounded-full text-xs font-semibold bg-gray-100 text-gray-700">
                                        Non Moving
                                    </span>
                                @else
                                    <span class="text-xs text-gray-400">Belum dihitung</span>
                                @endif
                            </td>
                            <td class="py-3 px-4 text-center font-mono text-xs">
                                @if ($item->fsn_category === 'F')
                                    <span class="font-semibold text-gray-900">{{ $item->rop ?? '-' }}</span>
                                    <span class="text-[10px] text-gray-400 block">(ROP)</span>
                                @elseif ($item->fsn_category === 'S')
                                    <span class="font-semibold text-gray-900">{{ $item->minimum_stock ?? '-' }}</span>
                                    <span class="text-[10px] text-gray-400 block">(Statis)</span>
                                @else
                                    <span class="text-gray-400">-</span>
                                @endif
                            </td>
                            <td class="py-3 px-4 text-center">
                                <span class="text-base font-bold {{ $isLow ? 'text-red-600' : 'text-gray-900' }}">
                                    {{ $item->stock }}
                                </span>
                            </td>
                            <td class="py-3 px-4 text-center">
                                @if ($isLow)
                                    <span class="inline-block px-3 py-1 bg-red-600 text-white text-xs font-bold rounded-full tracking-wide">
                                        MENIPIS
                                    </span>
                                @else
                                    <span class="inline-block px-3 py-1 bg-green-100 text-green-700 text-xs font-semibold rounded-full">
                                        AMAN
                                    </span>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="8" class="py-8 text-center text-gray-400 text-sm">
                                Tidak ada data barang yang sesuai dengan kriteria filter.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>
@endsection
