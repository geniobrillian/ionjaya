@extends('layouts.app')

@section('title', 'Impor Data')
@section('page_title', 'Impor Data')

@section('content')
<div class="space-y-6">
    <div class="bg-white rounded-lg border border-gray-200 shadow-sm p-4 sm:p-6">
        <h2 class="text-base font-semibold text-gray-900 mb-2">Impor Data dari Excel (.xlsx)</h2>
        <p class="text-xs text-gray-600 leading-relaxed mb-4">
            Impor data luar ke dalam sistem memakai file Excel dengan 4 sheet:
            <strong>Data Barang</strong>, <strong>Stok Opname</strong>, <strong>Pembelian</strong>, dan <strong>Penjualan</strong>
            (format sama dengan file simulasi). File diunggah, divalidasi, dan ditampilkan pratinjaunya dulu —
            data baru masuk database setelah Anda menekan tombol konfirmasi.
        </p>

        <a href="{{ route('imports.template') }}"
            class="inline-block px-4 py-2 bg-gray-100 hover:bg-gray-200 border border-gray-300 rounded-md text-sm font-medium text-gray-700">
            Unduh Template Excel
        </a>
    </div>

    <div class="bg-white rounded-lg border border-gray-200 shadow-sm p-4 sm:p-6">
        <h3 class="text-sm font-semibold text-gray-900 mb-3">Aturan impor (penting)</h3>
        <ul class="text-xs text-gray-600 leading-relaxed list-disc pl-5 space-y-1">
            <li>Barang dibuat dengan <strong>stok opname</strong> sebagai stok awal; Pembelian menambah stok, Penjualan mengurangi stok. Tidak ada edit stok manual.</li>
            <li>Harga di sheet Pembelian/Penjualan <strong>diabaikan</strong>: transaksi selalu memakai harga master dari sheet Data Barang (1 pintu harga).</li>
            <li>Penjualan yang membuat stok negatif <strong>menggagalkan seluruh impor</strong> (terlihat di pratinjau, sebelum konfirmasi).</li>
            <li>Barang yang kodenya sudah ada di database <strong>ditolak</strong> (tidak ditimpa). Untuk impor awal, pakai database kosong (migrate fresh tanpa seed).</li>
            <li>Pemasok yang kodenya belum ada <strong>dibuat otomatis</strong> dari file (lead time dari Data Barang, atau 1 hari bila hanya muncul di Pembelian).</li>
            <li>Stok Akhir di Stok Opname bersifat opsional; bila diisi, hasil impor diverifikasi harus sama persis, kalau tidak impor ditolak.</li>
            <li>Transaksi diimpor atas nama akun Anda yang sedang login.</li>
            <li>Satu file diimpor dalam satu transaksi database: gagal satu baris berarti gagal semua.</li>
        </ul>
    </div>

    <div class="bg-white rounded-lg border border-gray-200 shadow-sm p-4 sm:p-6">
        <h3 class="text-sm font-semibold text-gray-900 mb-3">Unggah File</h3>
        <form action="{{ route('imports.preview') }}" method="POST" enctype="multipart/form-data" class="space-y-4">
            @csrf
            <div>
                <input type="file" name="file" accept=".xlsx" required
                    class="w-full text-sm text-gray-700 border border-gray-300 rounded-md px-3 py-2 focus:border-blue-500 focus:outline-none">
                <p class="text-[11px] text-gray-500 mt-1">Format .xlsx saja, maksimal 10 MB.</p>
                @error('file')
                    <p class="text-xs text-red-600 mt-1">{{ $message }}</p>
                @enderror
            </div>
            <button type="submit"
                class="px-4 py-2 bg-blue-600 hover:bg-blue-700 text-white rounded-md text-sm font-medium">
                Pratinjau Impor
            </button>
        </form>
    </div>
</div>
@endsection
