@extends('layouts.app')

@section('title', 'Hasil Impor')
@section('page_title', 'Hasil Impor')

@section('content')
<div class="space-y-6">
    <div class="bg-green-50 rounded-lg border border-green-200 shadow-sm p-4 sm:p-6">
        <h2 class="text-base font-semibold text-green-800 mb-2">Impor berhasil</h2>
        <p class="text-xs text-green-700 leading-relaxed">
            Data berikut sudah masuk database. Langkah berikutnya: jalankan
            <strong>Analisis FSN &amp; ROP</strong> untuk menghitung kategori dan ambang dari data yang baru diimpor.
        </p>
    </div>

    <div class="bg-white rounded-lg border border-gray-200 shadow-sm p-4 sm:p-6">
        <div class="grid grid-cols-2 sm:grid-cols-4 gap-3 text-center">
            <div class="rounded-md border border-gray-200 p-3">
                <div class="text-xl font-extrabold text-gray-900">{{ $counts['suppliers_new'] }} / {{ $counts['suppliers'] }}</div>
                <div class="text-[11px] text-gray-500">Pemasok Baru / Total</div>
            </div>
            <div class="rounded-md border border-gray-200 p-3">
                <div class="text-xl font-extrabold text-gray-900">{{ $counts['items'] }}</div>
                <div class="text-[11px] text-gray-500">Barang</div>
            </div>
            <div class="rounded-md border border-gray-200 p-3">
                <div class="text-xl font-extrabold text-gray-900">{{ $counts['purchases'] }}</div>
                <div class="text-[11px] text-gray-500">Transaksi Pembelian</div>
            </div>
            <div class="rounded-md border border-gray-200 p-3">
                <div class="text-xl font-extrabold text-gray-900">{{ $counts['sales'] }}</div>
                <div class="text-[11px] text-gray-500">Transaksi Penjualan</div>
            </div>
        </div>
        <div class="flex flex-wrap gap-3 mt-5">
            <a href="{{ route('analysis.index') }}"
                class="px-4 py-2 bg-blue-600 hover:bg-blue-700 text-white rounded-md text-sm font-medium">
                Ke Analisis FSN &amp; ROP
            </a>
            <a href="{{ route('items.index') }}"
                class="px-4 py-2 bg-white hover:bg-gray-50 border border-gray-300 rounded-md text-sm font-medium text-gray-700">
                Ke Data Barang
            </a>
            <a href="{{ route('imports.index') }}"
                class="px-4 py-2 bg-white hover:bg-gray-50 border border-gray-300 rounded-md text-sm font-medium text-gray-700">
                Impor Lagi
            </a>
        </div>
    </div>
</div>
@endsection
