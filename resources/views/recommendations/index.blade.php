@extends('layouts.app')

@section('title', 'Rekomendasi Pengadaan')
@section('page_title', 'Rekomendasi Pengadaan')

@section('content')
<div class="space-y-6">
    <!-- Penjelasan Kriteria Rekomendasi -->
    <div class="bg-white rounded-lg border border-gray-200 shadow-sm p-4 sm:p-5">
        <div class="flex flex-col sm:flex-row sm:items-start justify-between gap-4">
            <div>
                <h2 class="text-base font-semibold text-gray-900">Daftar Barang Perlu Dipesan</h2>
                <p class="text-xs text-gray-600 mt-1 max-w-3xl leading-relaxed">
                    Halaman ini menampilkan seluruh barang yang stok fisiknya telah mencapai atau berada di bawah batas pemesanan 
                    (nilai <strong>Reorder Point (ROP)</strong> untuk kategori <em>Fast Moving</em>, atau <strong>ROP tanpa Safety Stock</strong> untuk kategori <em>Slow Moving</em>).
                    Daftar diurutkan berdasarkan prioritas kategori perputaran barang.
                </p>
            </div>
            <a href="{{ route('purchases.index') }}"
                class="w-full sm:w-auto px-4 py-2 bg-blue-600 hover:bg-blue-700 text-white text-xs font-semibold rounded-md shadow-sm transition-colors text-center whitespace-nowrap shrink-0">
                + Catat Pembelian
            </a>
        </div>
    </div>

    <!-- Tabel Rekomendasi -->
    <div class="bg-white rounded-lg border border-gray-200 shadow-sm overflow-hidden">
        <div class="overflow-x-auto">
            <table class="w-full text-sm text-left text-gray-700">
                <thead class="text-xs text-gray-500 uppercase bg-gray-50 border-b border-gray-200">
                    <tr>
                        <th class="py-3.5 px-4 w-12 text-center">No</th>
                        <th class="py-3.5 px-4 w-28">Kode</th>
                        <th class="py-3.5 px-4">Nama Barang</th>
                        <th class="py-3.5 px-4 w-32 text-center">Kategori FSN</th>
                        <th class="py-3.5 px-4 text-center w-28">Stok Saat Ini</th>
                        <th class="py-3.5 px-4 text-center w-32">Batas Pesan</th>
                        <th class="py-3.5 px-4">Pemasok</th>
                        <th class="py-3.5 px-4 text-center w-32">Waktu Tunggu</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-200">
                    @forelse ($recommendations as $index => $item)
                        <tr class="hover:bg-red-50/40 transition-colors">
                            <td class="py-3.5 px-4 text-center text-xs text-gray-500 font-mono">{{ $loop->iteration }}</td>
                            <td class="py-3.5 px-4 font-mono text-xs font-semibold text-gray-900">{{ $item->item_code }}</td>
                            <td class="py-3.5 px-4">
                                <div class="font-semibold text-gray-900">{{ $item->item_name }}</div>
                            </td>
                            <td class="py-3.5 px-4 text-center">
                                @if ($item->fsn_category === 'F')
                                    <span class="px-2.5 py-0.5 rounded-full text-xs font-semibold bg-green-100 text-green-800">
                                        Fast Moving
                                    </span>
                                @elseif ($item->fsn_category === 'S')
                                    <span class="px-2.5 py-0.5 rounded-full text-xs font-semibold bg-yellow-100 text-yellow-800">
                                        Slow Moving
                                    </span>
                                @else
                                    <span class="px-2.5 py-0.5 rounded-full text-xs font-semibold bg-gray-100 text-gray-700">
                                        Non Moving
                                    </span>
                                @endif
                            </td>
                            <td class="py-3.5 px-4 text-center">
                                <span class="text-base font-bold text-red-600">
                                    {{ $item->stock }}
                                </span>
                            </td>
                            <td class="py-3.5 px-4 text-center font-mono text-xs">
                                <span class="font-bold text-gray-900">{{ $item->rop }}</span>
                                <span class="text-[10px] text-gray-400 block">Nilai ROP</span>
                            </td>
                            <td class="py-3.5 px-4">
                                <div class="font-medium text-gray-900">{{ $item->supplier->supplier_name ?? '-' }}</div>
                                <div class="text-xs text-gray-500">{{ $item->supplier->phone ?? '-' }}</div>
                            </td>
                            <td class="py-3.5 px-4 text-center text-xs font-medium text-gray-700">
                                {{ $item->supplier->lead_time ?? 1 }} hari
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="8" class="py-12 text-center">
                                <div class="max-w-sm mx-auto">
                                    <div class="w-12 h-12 rounded-full bg-green-100 text-green-700 flex items-center justify-center mx-auto mb-3 font-bold text-lg">
                                        OK
                                    </div>
                                    <h3 class="text-sm font-semibold text-gray-900 mb-1">Seluruh Stok dalam Kondisi Aman</h3>
                                    <p class="text-xs text-gray-500">
                                        Saat ini tidak ada barang yang stok fisiknya berada pada atau di bawah batas pemesanan.
                                    </p>
                                </div>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>
@endsection
