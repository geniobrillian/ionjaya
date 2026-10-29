@extends('layouts.app')

@section('title', 'Pratinjau Impor')
@section('page_title', 'Pratinjau Impor')

@section('content')
<div class="space-y-6">
    <div class="bg-white rounded-lg border border-gray-200 shadow-sm p-4 sm:p-6">
        <h2 class="text-base font-semibold text-gray-900 mb-4">Ringkasan Pratinjau</h2>
        <div class="grid grid-cols-2 sm:grid-cols-4 gap-3 text-center">
            <div class="rounded-md border border-gray-200 p-3">
                <div class="text-xl font-extrabold text-gray-900">{{ $result['summary']['items'] }}</div>
                <div class="text-[11px] text-gray-500">Barang Baru</div>
            </div>
            <div class="rounded-md border border-gray-200 p-3">
                <div class="text-xl font-extrabold text-gray-900">{{ $result['summary']['suppliers_new'] }} / {{ $result['summary']['suppliers_total'] }}</div>
                <div class="text-[11px] text-gray-500">Pemasok Baru / Total</div>
            </div>
            <div class="rounded-md border border-gray-200 p-3">
                <div class="text-xl font-extrabold text-gray-900">{{ $result['summary']['purchase_groups'] }} ({{ $result['summary']['purchase_lines'] }} baris)</div>
                <div class="text-[11px] text-gray-500">Transaksi Pembelian</div>
            </div>
            <div class="rounded-md border border-gray-200 p-3">
                <div class="text-xl font-extrabold text-gray-900">{{ $result['summary']['sale_groups'] }} ({{ $result['summary']['sale_lines'] }} baris)</div>
                <div class="text-[11px] text-gray-500">Transaksi Penjualan</div>
            </div>
        </div>
    </div>

    @if ($result['errors'] !== [])
        <div class="bg-red-50 rounded-lg border border-red-200 shadow-sm p-4 sm:p-6">
            <h3 class="text-sm font-semibold text-red-800 mb-2">Impor tidak bisa dilanjutkan — {{ count($result['errors']) }} masalah ditemukan</h3>
            <ul class="text-xs text-red-700 leading-relaxed list-disc pl-5 space-y-1">
                @foreach ($result['errors'] as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
            <p class="text-xs text-red-700 mt-3">Perbaiki file Excel Anda, lalu unggah ulang.</p>
            <a href="{{ route('imports.index') }}"
                class="inline-block mt-3 px-4 py-2 bg-white hover:bg-gray-50 border border-gray-300 rounded-md text-sm font-medium text-gray-700">
                Kembali ke Halaman Impor
            </a>
        </div>
    @else
        @if ($result['infos'] !== [])
            <div class="bg-gray-50 rounded-lg border border-gray-200 shadow-sm p-4 sm:p-6">
                <h3 class="text-sm font-semibold text-gray-900 mb-2">Catatan</h3>
                <ul class="text-xs text-gray-600 leading-relaxed list-disc pl-5 space-y-1">
                    @foreach ($result['infos'] as $info)
                        <li>{{ $info }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

        <div class="bg-white rounded-lg border border-gray-200 shadow-sm p-4 sm:p-6">
            <h3 class="text-sm font-semibold text-gray-900 mb-3">Contoh Barang (5 pertama)</h3>
            <div class="overflow-x-auto">
                <table class="w-full text-xs text-left text-gray-700">
                    <thead class="text-gray-500 uppercase bg-gray-50 border-b border-gray-200">
                        <tr>
                            <th class="py-2 px-3">Kode</th>
                            <th class="py-2 px-3">Nama</th>
                            <th class="py-2 px-3">Pemasok</th>
                            <th class="py-2 px-3 text-right">Harga Beli</th>
                            <th class="py-2 px-3 text-right">Harga Jual</th>
                            <th class="py-2 px-3 text-center">Stok Awal</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-200">
                        @foreach (array_slice($result['items'], 0, 5) as $item)
                            <tr>
                                <td class="py-2 px-3 font-mono">{{ $item['item_code'] }}</td>
                                <td class="py-2 px-3">{{ $item['item_name'] }}</td>
                                <td class="py-2 px-3 font-mono">{{ $item['supplier_code'] }}</td>
                                <td class="py-2 px-3 text-right font-mono">Rp {{ number_format($item['purchase_price'], 0, ',', '.') }}</td>
                                <td class="py-2 px-3 text-right font-mono">Rp {{ number_format($item['selling_price'], 0, ',', '.') }}</td>
                                <td class="py-2 px-3 text-center font-mono">{{ $result['opening'][$item['item_code']]['opening_stock'] ?? 0 }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            <p class="text-[11px] text-gray-500 mt-2">Menampilkan 5 dari {{ count($result['items']) }} barang. Transaksi akan diimpor terurut kronologis (tanggal, jam; pembelian didahulukan pada waktu yang sama).</p>
        </div>

        <div class="bg-white rounded-lg border border-gray-200 shadow-sm p-4 sm:p-6">
            <h3 class="text-sm font-semibold text-gray-900 mb-2">Konfirmasi Impor</h3>
            <p class="text-xs text-gray-600 leading-relaxed mb-4">
                Pratinjau bersih (tidak ada error). Menekan tombol di bawah akan menulis
                {{ $result['summary']['items'] }} barang, {{ $result['summary']['purchase_groups'] }} transaksi pembelian, dan
                {{ $result['summary']['sale_groups'] }} transaksi penjualan ke database dalam satu transaksi.
                Tindakan ini tidak bisa dibatalkan dari aplikasi.
            </p>
            <form action="{{ route('imports.confirm') }}" method="POST" class="flex items-center gap-3">
                @csrf
                <input type="hidden" name="import_id" value="{{ $importId }}">
                <button type="submit"
                    class="px-4 py-2 bg-blue-600 hover:bg-blue-700 text-white rounded-md text-sm font-medium">
                    Konfirmasi &amp; Impor
                </button>
                <a href="{{ route('imports.index') }}" class="text-sm text-gray-600 hover:text-gray-900">Batal</a>
            </form>
        </div>
    @endif
</div>
@endsection
