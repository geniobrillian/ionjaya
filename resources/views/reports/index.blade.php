@extends('layouts.app')

@section('title', 'Laporan')
@section('page_title', 'Laporan')

@section('content')
<div class="space-y-6">
    <!-- Tab Navigasi Pilihan Jenis Laporan (no-print) -->
    <div class="bg-white rounded-lg border border-gray-200 shadow-sm p-4 no-print flex flex-col sm:flex-row items-center justify-between gap-4">
        <div class="flex items-center space-x-2 overflow-x-auto w-full sm:w-auto">
            <a href="{{ route('reports.index', ['type' => 'stok']) }}"
                class="px-4 py-2 text-xs font-semibold rounded-md border transition-colors whitespace-nowrap {{ $reportType === 'stok' ? 'bg-blue-600 text-white border-blue-600' : 'bg-gray-50 text-gray-700 border-gray-200 hover:bg-gray-100' }}">
                1. Laporan Stok Barang
            </a>
            <a href="{{ route('reports.index', ['type' => 'transaksi']) }}"
                class="px-4 py-2 text-xs font-semibold rounded-md border transition-colors whitespace-nowrap {{ $reportType === 'transaksi' ? 'bg-blue-600 text-white border-blue-600' : 'bg-gray-50 text-gray-700 border-gray-200 hover:bg-gray-100' }}">
                2. Laporan Transaksi
            </a>
            <a href="{{ route('reports.index', ['type' => 'fsn']) }}"
                class="px-4 py-2 text-xs font-semibold rounded-md border transition-colors whitespace-nowrap {{ $reportType === 'fsn' ? 'bg-blue-600 text-white border-blue-600' : 'bg-gray-50 text-gray-700 border-gray-200 hover:bg-gray-100' }}">
                3. Laporan Hasil FSN & ROP
            </a>
        </div>

        <button type="button" onclick="window.print()"
            class="w-full sm:w-auto px-5 py-2 bg-gray-900 hover:bg-gray-800 text-white text-xs font-bold rounded-md shadow-sm transition-colors flex items-center justify-center space-x-1">
            <span>Cetak Laporan</span>
        </button>
    </div>

    <!-- Filter Khusus untuk Laporan Transaksi (no-print) -->
    @if ($reportType === 'transaksi')
        <div class="bg-white rounded-lg border border-gray-200 shadow-sm p-4 sm:p-5 no-print">
            <form action="{{ route('reports.index') }}" method="GET" class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-12 gap-4 items-end">
                <input type="hidden" name="type" value="transaksi">

                <div class="sm:col-span-1 lg:col-span-3">
                    <label for="start_date" class="block text-xs font-semibold text-gray-700 mb-1">Dari Tanggal</label>
                    <input type="date" name="start_date" id="start_date" value="{{ $startDateStr }}" required
                        class="w-full rounded-md border border-gray-300 px-3 py-1.5 text-xs text-gray-900 focus:border-blue-500 focus:outline-none">
                </div>

                <div class="sm:col-span-1 lg:col-span-3">
                    <label for="end_date" class="block text-xs font-semibold text-gray-700 mb-1">Sampai Tanggal</label>
                    <input type="date" name="end_date" id="end_date" value="{{ $endDateStr }}" required
                        class="w-full rounded-md border border-gray-300 px-3 py-1.5 text-xs text-gray-900 focus:border-blue-500 focus:outline-none">
                </div>

                <div class="sm:col-span-1 lg:col-span-3">
                    <label for="transaction_type" class="block text-xs font-semibold text-gray-700 mb-1">Jenis Transaksi</label>
                    <select name="transaction_type" id="transaction_type"
                        class="w-full rounded-md border border-gray-300 px-3 py-1.5 text-xs text-gray-900 focus:border-blue-500 focus:outline-none">
                        <option value="all" {{ $transactionType === 'all' ? 'selected' : '' }}>Semua (Penjualan & Pembelian)</option>
                        <option value="penjualan" {{ $transactionType === 'penjualan' ? 'selected' : '' }}>Hanya Penjualan</option>
                        <option value="pembelian" {{ $transactionType === 'pembelian' ? 'selected' : '' }}>Hanya Pembelian</option>
                    </select>
                </div>

                <div class="sm:col-span-1 lg:col-span-3">
                    <button type="submit"
                        class="w-full py-1.5 px-4 bg-blue-600 hover:bg-blue-700 text-white text-xs font-semibold rounded-md transition-colors text-center">
                        Terapkan Filter
                    </button>
                </div>
            </form>
        </div>
    @endif

    <!-- Area Dokumen Laporan (Tampil di Layar & Cetak) -->
    <div class="bg-white rounded-lg border border-gray-200 shadow-sm p-4 sm:p-8 print:p-0 print:border-none print:shadow-none">
        <!-- Kop Surat Laporan (khusus cetak / tampilan formal) -->
        <div class="text-center pb-6 mb-6 border-b-2 border-gray-900">
            <h1 class="text-xl font-bold uppercase tracking-wider text-gray-900">UD ION JAYA MOTOR</h1>
            <p class="text-xs text-gray-600 mt-1">
                Toko Suku Cadang & Perlengkapan Sepeda Motor &bull; Jombang, Jawa Timur
            </p>
            <div class="mt-4 pt-2 border-t border-gray-300 inline-block px-8">
                <h2 class="text-sm font-extrabold uppercase tracking-wide text-gray-900">
                    @if ($reportType === 'stok')
                        LAPORAN PERSEDIAAN & STOK BARANG
                    @elseif ($reportType === 'transaksi')
                        LAPORAN RIWAYAT TRANSAKSI {{ strtoupper($transactionType === 'all' ? 'PENJUALAN & PEMBELIAN' : $transactionType) }}
                    @else
                        LAPORAN HASIL ANALISIS FSN & PARAMETER REORDER POINT
                    @endif
                </h2>
                <p class="text-[11px] text-gray-500 mt-0.5">
                    Dicetak pada: {{ Carbon\Carbon::now()->translatedFormat('d F Y, H:i') }} WIB
                    @if ($reportType === 'transaksi')
                        &bull; Periode: {{ Carbon\Carbon::parse($startDateStr)->translatedFormat('d M Y') }} s/d {{ Carbon\Carbon::parse($endDateStr)->translatedFormat('d M Y') }}
                    @endif
                </p>
            </div>
        </div>

        <!-- Konten 1: Laporan Stok Barang -->
        @if ($reportType === 'stok')
            <div class="overflow-x-auto">
                <table class="w-full text-xs text-left text-gray-700 border border-gray-300">
                    <thead class="bg-gray-100 text-gray-700 uppercase font-bold border-b border-gray-300">
                        <tr>
                            <th class="py-2.5 px-3 w-10 text-center border-r border-gray-300">No</th>
                            <th class="py-2.5 px-3 w-24 border-r border-gray-300">Kode</th>
                            <th class="py-2.5 px-3 border-r border-gray-300">Nama Barang</th>
                            <th class="py-2.5 px-3 w-28 text-center border-r border-gray-300">Kategori</th>
                            <th class="py-2.5 px-3 text-right w-28 border-r border-gray-300">Harga Beli</th>
                            <th class="py-2.5 px-3 text-right w-28 border-r border-gray-300">Harga Jual</th>
                            <th class="py-2.5 px-3 text-center w-20 border-r border-gray-300">Stok</th>
                            <th class="py-2.5 px-3 text-center w-24 border-r border-gray-300">Batas Pesan</th>
                            <th class="py-2.5 px-3 text-center w-24">Status</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-300">
                        @foreach ($reportData as $item)
                            <tr class="hover:bg-gray-50">
                                <td class="py-2 px-3 text-center border-r border-gray-300 font-mono">{{ $loop->iteration }}</td>
                                <td class="py-2 px-3 font-mono font-semibold border-r border-gray-300">{{ $item->item_code }}</td>
                                <td class="py-2 px-3 font-medium border-r border-gray-300">{{ $item->item_name }}</td>
                                <td class="py-2 px-3 text-center font-bold border-r border-gray-300">
                                    {{ $item->fsn_category ?? '-' }}
                                </td>
                                <td class="py-2 px-3 text-right font-mono border-r border-gray-300">Rp {{ number_format($item->purchase_price, 0, ',', '.') }}</td>
                                <td class="py-2 px-3 text-right font-mono border-r border-gray-300">Rp {{ number_format($item->selling_price, 0, ',', '.') }}</td>
                                <td class="py-2 px-3 text-center font-bold border-r border-gray-300 {{ $item->is_low_stock ? 'text-red-600' : 'text-gray-900' }}">
                                    {{ $item->stock }}
                                </td>
                                <td class="py-2 px-3 text-center font-mono border-r border-gray-300">
                                    {{ $item->order_threshold > 0 ? $item->order_threshold : '-' }}
                                </td>
                                <td class="py-2 px-3 text-center font-bold">
                                    <span class="{{ $item->is_low_stock ? 'text-red-600' : 'text-green-700' }}">
                                        {{ $item->is_low_stock ? 'MENIPIS' : 'AMAN' }}
                                    </span>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif

        <!-- Konten 2: Laporan Transaksi -->
        @if ($reportType === 'transaksi')
            <div class="overflow-x-auto">
                <table class="w-full text-xs text-left text-gray-700 border border-gray-300">
                    <thead class="bg-gray-100 text-gray-700 uppercase font-bold border-b border-gray-300">
                        <tr>
                            <th class="py-2.5 px-3 w-10 text-center border-r border-gray-300">No</th>
                            <th class="py-2.5 px-3 w-28 border-r border-gray-300">No Nota</th>
                            <th class="py-2.5 px-3 w-24 border-r border-gray-300">Tanggal</th>
                            <th class="py-2.5 px-3 w-24 text-center border-r border-gray-300">Jenis</th>
                            <th class="py-2.5 px-3 w-28 border-r border-gray-300">Kasir/Petugas</th>
                            <th class="py-2.5 px-3 border-r border-gray-300">Rincian Barang</th>
                            <th class="py-2.5 px-3 text-right w-36">Total Transaksi</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-300">
                        @forelse ($reportData as $tx)
                            <tr class="hover:bg-gray-50">
                                <td class="py-2 px-3 text-center border-r border-gray-300 font-mono">{{ $loop->iteration }}</td>
                                <td class="py-2 px-3 font-mono font-semibold border-r border-gray-300">
                                    {{ $tx->transaction_type === 'penjualan' ? 'TRX' : 'BELI' }}-{{ str_pad($tx->id, 5, '0', STR_PAD_LEFT) }}
                                </td>
                                <td class="py-2 px-3 border-r border-gray-300">{{ Carbon\Carbon::parse($tx->date)->translatedFormat('d/m/Y') }}</td>
                                <td class="py-2 px-3 text-center uppercase font-bold border-r border-gray-300 {{ $tx->transaction_type === 'penjualan' ? 'text-blue-700' : 'text-purple-700' }}">
                                    {{ $tx->transaction_type }}
                                </td>
                                <td class="py-2 px-3 border-r border-gray-300">{{ $tx->user->name ?? 'Kasir' }}</td>
                                <td class="py-2 px-3 text-gray-600 border-r border-gray-300">
                                    {{ $tx->details->map(fn($d) => ($d->item->item_name ?? 'Item') . ' (' . $d->quantity . ')')->join(', ') }}
                                </td>
                                <td class="py-2 px-3 text-right font-mono font-bold text-gray-900">
                                    Rp {{ number_format($tx->total, 0, ',', '.') }}
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="7" class="py-6 text-center text-gray-400">Tidak ada transaksi pada periode yang dipilih.</td>
                            </tr>
                        @endforelse
                    </tbody>
                    <tfoot class="bg-gray-100 font-bold border-t-2 border-gray-400 text-gray-900">
                        <tr>
                            <td colspan="6" class="py-3 px-4 text-right uppercase border-r border-gray-300">Total Keseluruhan Nilai Transaksi:</td>
                            <td class="py-3 px-3 text-right font-mono text-sm">Rp {{ number_format($totalAmount, 0, ',', '.') }}</td>
                        </tr>
                    </tfoot>
                </table>
            </div>
        @endif

        <!-- Konten 3: Laporan Hasil Analisis FSN & ROP -->
        @if ($reportType === 'fsn')
            <div class="overflow-x-auto">
                <table class="w-full text-xs text-left text-gray-700 border border-gray-300">
                    <thead class="bg-gray-100 text-gray-700 uppercase font-bold border-b border-gray-300">
                        <tr>
                            <th class="py-2.5 px-3 w-10 text-center border-r border-gray-300">No</th>
                            <th class="py-2.5 px-3 w-24 border-r border-gray-300">Kode</th>
                            <th class="py-2.5 px-3 border-r border-gray-300">Nama Barang</th>
                            <th class="py-2.5 px-3 w-28 text-center border-r border-gray-300">Kategori FSN</th>
                            <th class="py-2.5 px-3 text-center w-24 border-r border-gray-300">Lead Time</th>
                            <th class="py-2.5 px-3 text-center w-28 border-r border-gray-300">Safety Stock</th>
                            <th class="py-2.5 px-3 text-center w-28 border-r border-gray-300">Nilai ROP</th>
                            <th class="py-2.5 px-3 text-center w-28 border-r border-gray-300">Batas Statis</th>
                            <th class="py-2.5 px-3 text-center w-28">Batas Akhir</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-300">
                        @foreach ($reportData as $item)
                            <tr class="hover:bg-gray-50">
                                <td class="py-2 px-3 text-center border-r border-gray-300 font-mono">{{ $loop->iteration }}</td>
                                <td class="py-2 px-3 font-mono font-semibold border-r border-gray-300">{{ $item->item_code }}</td>
                                <td class="py-2 px-3 font-medium border-r border-gray-300">
                                    {{ $item->item_name }}
                                    <span class="text-[10px] text-gray-400 block">{{ $item->supplier->supplier_name ?? '-' }}</span>
                                </td>
                                <td class="py-2 px-3 text-center font-bold border-r border-gray-300">
                                    @if ($item->fsn_category === 'F')
                                        <span class="text-green-700">Fast Moving (F)</span>
                                    @elseif ($item->fsn_category === 'S')
                                        <span class="text-yellow-700">Slow Moving (S)</span>
                                    @elseif ($item->fsn_category === 'N')
                                        <span class="text-gray-600">Non Moving (N)</span>
                                    @else
                                        <span class="text-gray-400">Belum dihitung</span>
                                    @endif
                                </td>
                                <td class="py-2 px-3 text-center font-mono border-r border-gray-300">{{ $item->supplier->lead_time ?? 1 }} hr</td>
                                <td class="py-2 px-3 text-center font-mono border-r border-gray-300">
                                    {{ $item->safety_stock !== null ? $item->safety_stock : '-' }}
                                </td>
                                <td class="py-2 px-3 text-center font-mono font-bold text-blue-700 border-r border-gray-300">
                                    {{ $item->rop !== null ? $item->rop : '-' }}
                                </td>
                                <td class="py-2 px-3 text-center font-mono border-r border-gray-300">
                                    {{ $item->minimum_stock !== null ? $item->minimum_stock : '-' }}
                                </td>
                                <td class="py-2 px-3 text-center font-mono font-bold text-gray-900">
                                    {{ $item->order_threshold }}
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif

        <!-- Tanda Tangan Laporan Saat Dicetak -->
        <div class="mt-12 hidden print:grid grid-cols-2 gap-8 text-center text-xs text-gray-800">
            <div>
                <p>Mengetahui,</p>
                <p class="font-bold mt-1">Pemilik UD Ion Jaya Motor</p>
                <div class="h-16"></div>
                <p class="font-bold underline">( .................................................. )</p>
            </div>
            <div>
                <p>Jombang, {{ Carbon\Carbon::now()->translatedFormat('d F Y') }}</p>
                <p class="font-bold mt-1">Petugas / Kasir</p>
                <div class="h-16"></div>
                <p class="font-bold underline">{{ Auth::user()->name }}</p>
            </div>
        </div>
    </div>
</div>
@endsection

@push('scripts')
<style>
    @media print {
        aside, header, footer, .no-print {
            display: none !important;
        }
        body {
            background-color: white !important;
            color: black !important;
        }
        main {
            padding: 0 !important;
            margin: 0 !important;
        }
    }
</style>
@endpush
