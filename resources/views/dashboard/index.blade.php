@extends('layouts.app')

@section('title', 'Dashboard')
@section('page_title', 'Dashboard')

@section('content')
<div class="space-y-6">
    <!-- Header Dashboard & Filter Rentang Waktu -->
    <div class="bg-white rounded-lg border border-gray-200 shadow-sm p-4 sm:p-5 flex flex-col sm:flex-row items-start sm:items-center justify-between gap-4">
        <div>
            <h2 class="text-base font-semibold text-gray-900">Ringkasan Operasional & Estimasi Laba</h2>
            <p class="text-xs text-gray-500 mt-0.5">
                Periode data: <strong>{{ Carbon\Carbon::parse($metrics['start_date'])->translatedFormat('d M Y') }}</strong> s/d <strong>{{ Carbon\Carbon::parse($metrics['end_date'])->translatedFormat('d M Y') }}</strong>
            </p>
        </div>

        <!-- Filter Pilihan Rentang Waktu -->
        <div class="flex items-center space-x-1 bg-gray-100 p-1 rounded-md text-xs font-medium overflow-x-auto max-w-full">
            <a href="{{ route('dashboard', ['range' => 'today']) }}"
                class="px-2.5 sm:px-3 py-1.5 rounded transition-colors whitespace-nowrap {{ $range === 'today' ? 'bg-white text-gray-900 font-bold shadow-xs' : 'text-gray-600 hover:text-gray-900' }}">
                Hari Ini
            </a>
            <a href="{{ route('dashboard', ['range' => 'weekly']) }}"
                class="px-2.5 sm:px-3 py-1.5 rounded transition-colors whitespace-nowrap {{ $range === 'weekly' ? 'bg-white text-gray-900 font-bold shadow-xs' : 'text-gray-600 hover:text-gray-900' }}">
                7 Hari Terakhir
            </a>
            <a href="{{ route('dashboard', ['range' => 'monthly']) }}"
                class="px-2.5 sm:px-3 py-1.5 rounded transition-colors whitespace-nowrap {{ $range === 'monthly' ? 'bg-white text-gray-900 font-bold shadow-xs' : 'text-gray-600 hover:text-gray-900' }}">
                Bulan Ini
            </a>
        </div>
    </div>

    <!-- 4 Kartu Metrik Indikator Utama -->
    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4 sm:gap-5">
        <!-- Kartu 1: Estimasi Laba Kotor -->
        <div class="bg-white rounded-lg border border-gray-200 shadow-sm p-4 sm:p-5">
            <span class="text-xs font-semibold text-gray-500 uppercase tracking-wider block">Estimasi Laba Kotor</span>
            <div class="text-xl sm:text-2xl font-bold text-blue-700 mt-2">
                Rp {{ number_format($metrics['total_profit'], 0, ',', '.') }}
            </div>
            <p class="text-[11px] text-gray-400 mt-1">Penjualan &minus; Harga Beli Terakhir</p>
        </div>

        <!-- Kartu 2: Total Omset Penjualan -->
        <div class="bg-white rounded-lg border border-gray-200 shadow-sm p-4 sm:p-5">
            <span class="text-xs font-semibold text-gray-500 uppercase tracking-wider block">Total Penjualan</span>
            <div class="text-xl sm:text-2xl font-bold text-gray-900 mt-2">
                Rp {{ number_format($metrics['total_sales'], 0, ',', '.') }}
            </div>
            <p class="text-[11px] text-gray-400 mt-1">Nilai kotor transaksi penjualan</p>
        </div>

        <!-- Kartu 3: Jumlah Transaksi -->
        <div class="bg-white rounded-lg border border-gray-200 shadow-sm p-4 sm:p-5">
            <span class="text-xs font-semibold text-gray-500 uppercase tracking-wider block">Jumlah Transaksi</span>
            <div class="text-xl sm:text-2xl font-bold text-gray-900 mt-2">
                {{ $metrics['total_transactions'] }} <span class="text-xs font-normal text-gray-500">struk</span>
            </div>
            <p class="text-[11px] text-gray-400 mt-1">Total nota penjualan periode ini</p>
        </div>

        <!-- Kartu 4: Peringatan Stok Menipis -->
        <div class="bg-white rounded-lg border border-gray-200 shadow-sm p-4 sm:p-5">
            <span class="text-xs font-semibold text-gray-500 uppercase tracking-wider block">Peringatan Stok</span>
            <div class="text-xl sm:text-2xl font-bold {{ $metrics['low_stock_count'] > 0 ? 'text-red-600' : 'text-green-700' }} mt-2">
                {{ $metrics['low_stock_count'] }} <span class="text-xs font-normal text-gray-500">barang</span>
            </div>
            <p class="text-[11px] {{ $metrics['low_stock_count'] > 0 ? 'text-red-600 font-semibold' : 'text-gray-400' }} mt-1">
                {{ $metrics['low_stock_count'] > 0 ? 'Stok mencapai batas pemesanan' : 'Seluruh stok dalam kondisi aman' }}
            </p>
        </div>
    </div>

    <!-- Grafik Batang Tren 7 Hari Terakhir (SVG / CSS Flat) -->
    <div class="bg-white rounded-lg border border-gray-200 shadow-sm p-4 sm:p-6">
        <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-2 pb-4 border-b border-gray-100 mb-6">
            <div>
                <h3 class="text-base font-semibold text-gray-900">Tren Penjualan & Laba Harian (7 Hari Terakhir)</h3>
                <p class="text-xs text-gray-500">Perbandingan nilai omset penjualan (biru) dan estimasi laba kotor (hijau).</p>
            </div>
            <div class="flex items-center space-x-4 text-xs shrink-0">
                <span class="flex items-center"><span class="w-3 h-3 bg-blue-600 rounded-xs mr-1.5"></span> Penjualan</span>
                <span class="flex items-center"><span class="w-3 h-3 bg-green-600 rounded-xs mr-1.5"></span> Laba Kotor</span>
            </div>
        </div>

        @php
            $maxVal = collect($metrics['daily_trend'])->max('sales') ?: 100000;
        @endphp

        <div class="grid grid-cols-7 gap-3 sm:gap-6 items-end h-56 pt-6 pb-2 px-2 border-b border-gray-200">
            @foreach ($metrics['daily_trend'] as $day)
                @php
                    $salesHeight = $maxVal > 0 ? max(4, round(($day['sales'] / $maxVal) * 100)) : 4;
                    $profitHeight = $maxVal > 0 ? max(4, round(($day['profit'] / $maxVal) * 100)) : 4;
                @endphp
                <div class="flex flex-col items-center h-full justify-end group">
                    <div class="text-[10px] text-gray-500 font-mono opacity-0 group-hover:opacity-100 transition-opacity mb-1 whitespace-nowrap">
                        Rp {{ number_format($day['profit'], 0, ',', '.') }}
                    </div>
                    <div class="w-full flex items-end justify-center space-x-1 sm:space-x-2 h-40">
                        <!-- Bar Penjualan -->
                        <div class="w-3 sm:w-5 bg-blue-600 rounded-t-xs transition-all hover:bg-blue-700"
                            style="height: {{ $salesHeight }}%"
                            title="Penjualan: Rp {{ number_format($day['sales'], 0, ',', '.') }}"></div>
                        <!-- Bar Laba -->
                        <div class="w-3 sm:w-5 bg-green-600 rounded-t-xs transition-all hover:bg-green-700"
                            style="height: {{ $profitHeight }}%"
                            title="Laba: Rp {{ number_format($day['profit'], 0, ',', '.') }}"></div>
                    </div>
                    <span class="text-[11px] text-gray-600 mt-2 font-medium">{{ $day['label'] }}</span>
                </div>
            @endforeach
        </div>
    </div>

    <!-- 2 Kolom Ringkasan Data Bawah -->
    <div class="grid grid-cols-1 lg:grid-cols-2 gap-4 sm:gap-6">
        <!-- Kolom Kiri: 5 Transaksi Penjualan Terakhir -->
        <div class="bg-white rounded-lg border border-gray-200 shadow-sm p-4 sm:p-5">
            <div class="flex items-center justify-between pb-3 border-b border-gray-200 mb-4">
                <h3 class="text-sm font-semibold text-gray-900">Transaksi Penjualan Terkini</h3>
                <a href="{{ route('sales.index') }}" class="text-xs text-blue-600 hover:text-blue-800 font-medium">Buka Kasir &rarr;</a>
            </div>
            <div class="overflow-x-auto">
                <table class="w-full text-xs text-left text-gray-700">
                    <thead class="text-gray-500 uppercase bg-gray-50 border-b border-gray-200">
                        <tr>
                            <th class="py-2.5 px-3">No Nota</th>
                            <th class="py-2.5 px-3">Tanggal</th>
                            <th class="py-2.5 px-3">Kasir</th>
                            <th class="py-2.5 px-3 text-right">Total</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100">
                        @forelse ($recentTransactions as $tx)
                            <tr class="hover:bg-gray-50">
                                <td class="py-2.5 px-3 font-mono font-semibold text-gray-900">TRX-{{ str_pad($tx->id, 5, '0', STR_PAD_LEFT) }}</td>
                                <td class="py-2.5 px-3 text-gray-600">{{ Carbon\Carbon::parse($tx->date)->translatedFormat('d M Y') }}</td>
                                <td class="py-2.5 px-3">{{ $tx->user->name ?? 'Kasir' }}</td>
                                <td class="py-2.5 px-3 text-right font-bold text-gray-900">Rp {{ number_format($tx->total, 0, ',', '.') }}</td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="4" class="py-6 text-center text-gray-400">Belum ada transaksi penjualan.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>

        <!-- Kolom Kanan: 5 Barang Stok Paling Menipis -->
        <div class="bg-white rounded-lg border border-gray-200 shadow-sm p-4 sm:p-5">
            <div class="flex items-center justify-between pb-3 border-b border-gray-200 mb-4">
                <h3 class="text-sm font-semibold text-gray-900">Barang Stok Paling Menipis</h3>
                <a href="{{ route('recommendations.index') }}" class="text-xs text-blue-600 hover:text-blue-800 font-medium">Lihat Semua &rarr;</a>
            </div>
            <div class="overflow-x-auto">
                <table class="w-full text-xs text-left text-gray-700">
                    <thead class="text-gray-500 uppercase bg-gray-50 border-b border-gray-200">
                        <tr>
                            <th class="py-2.5 px-3">Kode</th>
                            <th class="py-2.5 px-3">Nama Barang</th>
                            <th class="py-2.5 px-3 text-center">Stok</th>
                            <th class="py-2.5 px-3 text-center">Batas</th>
                            <th class="py-2.5 px-3 text-center">Status</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100">
                        @forelse ($lowStockItems as $item)
                            <tr class="hover:bg-red-50/40">
                                <td class="py-2.5 px-3 font-mono font-semibold text-gray-900">{{ $item->item_code }}</td>
                                <td class="py-2.5 px-3 font-medium text-gray-900">{{ $item->item_name }}</td>
                                <td class="py-2.5 px-3 text-center font-bold text-red-600">{{ $item->stock }}</td>
                                <td class="py-2.5 px-3 text-center font-mono text-gray-600">{{ $item->order_threshold }}</td>
                                <td class="py-2.5 px-3 text-center">
                                    <span class="px-2 py-0.5 rounded-full text-[10px] font-bold bg-red-600 text-white">MENIPIS</span>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="5" class="py-6 text-center text-green-700 font-medium">Seluruh stok barang dalam kondisi aman.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>
@endsection
