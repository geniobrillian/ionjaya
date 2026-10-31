@extends('layouts.app')

@section('title', 'Analisis Persediaan')
@section('page_title', 'Analisis Persediaan')

@section('content')
@php
    // Format tanggal Indonesia singkat: 1 Jan 2026 (tanpa bergantung locale server).
    $bulanSingkat = ['Jan', 'Feb', 'Mar', 'Apr', 'Mei', 'Jun', 'Jul', 'Agu', 'Sep', 'Okt', 'Nov', 'Des'];
    $tglSingkat = function ($s) use ($bulanSingkat) {
        $t = strtotime($s);
        return date('j', $t) . ' ' . $bulanSingkat[date('n', $t) - 1] . ' ' . date('Y', $t);
    };
@endphp
<div class="space-y-6">
    <!-- Kartu Pemilihan Periode Analisis -->
    <div class="bg-white rounded-lg border border-gray-200 shadow-sm p-6">
        <div class="mb-4">
            <h2 class="text-base font-semibold text-gray-900">Periode Analisis</h2>
            <p class="text-xs text-gray-500 mt-0.5">
                Pilih periode transaksi yang ingin digunakan untuk menganalisis pergerakan dan kebutuhan stok barang.
            </p>
        </div>

        <form action="{{ route('analysis.index') }}" method="GET" class="space-y-4">
            <input type="hidden" name="process" value="1">
            <div class="grid grid-cols-1 sm:grid-cols-12 gap-4 items-end">
                <div class="sm:col-span-4">
                    <label for="start_date" class="block text-xs font-semibold text-gray-700 mb-1">Tanggal Awal</label>
                    <input type="date" name="start_date" id="start_date" value="{{ $startDateStr }}" required
                        class="w-full rounded-md border border-gray-300 px-3 py-2 text-sm text-gray-900 focus:border-blue-500 focus:outline-none focus:ring-1 focus:ring-blue-500">
                </div>

                <div class="sm:col-span-4">
                    <label for="end_date" class="block text-xs font-semibold text-gray-700 mb-1">Tanggal Akhir</label>
                    <input type="date" name="end_date" id="end_date" value="{{ $endDateStr }}" required
                        class="w-full rounded-md border border-gray-300 px-3 py-2 text-sm text-gray-900 focus:border-blue-500 focus:outline-none focus:ring-1 focus:ring-blue-500">
                </div>

                <div class="sm:col-span-4 flex items-center space-x-2">
                    <button type="submit"
                        class="w-full py-2 px-4 bg-blue-600 hover:bg-blue-700 text-white text-sm font-semibold rounded-md shadow-sm transition-colors text-center">
                        Analisis Persediaan
                    </button>
                </div>
            </div>

            {{-- Preset Pintas disembunyikan sementara (tombol tidak tampil, kode tetap ada dan bisa diaktifkan lagi):
            <div class="flex items-center gap-2 pt-2 text-xs text-gray-500">
                <span class="font-medium text-gray-600">Pilihan Cepat:</span>
                <button type="button" onclick="setPreset('{{ Carbon\Carbon::now()->subYear()->toDateString() }}', '{{ Carbon\Carbon::now()->toDateString() }}')"
                    class="px-2.5 py-1 bg-gray-100 hover:bg-gray-200 text-gray-700 rounded border border-gray-300 transition-colors">
                    12 Bulan Terakhir
                </button>
                <button type="button" onclick="setPreset('{{ Carbon\Carbon::now()->subDays(90)->toDateString() }}', '{{ Carbon\Carbon::now()->toDateString() }}')"
                    class="px-2.5 py-1 bg-gray-100 hover:bg-gray-200 text-gray-700 rounded border border-gray-300 transition-colors">
                    90 Hari Terakhir
                </button>
            </div>
            --}}
        </form>
    </div>

    <!-- Kondisi: Tidak ada transaksi pada rentang tanggal -->
    @if (request()->has('process') && ! $hasTransactions)
        <div class="bg-yellow-50 border border-yellow-200 rounded-lg p-6 text-center">
            <h3 class="text-sm font-semibold text-yellow-900 mb-1">Tidak Ditemukan Data Transaksi</h3>
            <p class="text-xs text-yellow-700 max-w-lg mx-auto">
                Tidak ada catatan transaksi penjualan maupun pembelian pada rentang tanggal <strong>{{ Carbon\Carbon::parse($startDateStr)->translatedFormat('d F Y') }}</strong> s/d <strong>{{ Carbon\Carbon::parse($endDateStr)->translatedFormat('d F Y') }}</strong>. Silakan pilih rentang tanggal transaksi yang tersedia.
            </p>
        </div>
    @endif

    <!-- Kondisi: Hasil Analisis Berhasil Dihitung -->
    @if ($analysisResult)
        <!-- 3 Kartu Ringkasan Pergerakan Barang -->
        <div class="grid grid-cols-1 md:grid-cols-3 gap-5">
            <!-- Fast Moving -->
            <div class="bg-white rounded-lg border border-green-300 shadow-sm p-5">
                <span class="text-xs font-bold uppercase text-green-700 tracking-wider">Fast Moving (F)</span>
                <div class="text-2xl font-extrabold text-gray-900 mt-1">
                    {{ $analysisResult['summary']['fast_count'] }} <span class="text-xs font-normal text-gray-500">barang</span>
                </div>
                <p class="text-xs text-gray-500 mt-2">Barang yang cepat terjual.</p>
            </div>

            <!-- Slow Moving -->
            <div class="bg-white rounded-lg border border-yellow-300 shadow-sm p-5">
                <span class="text-xs font-bold uppercase text-yellow-700 tracking-wider">Slow Moving (S)</span>
                <div class="text-2xl font-extrabold text-gray-900 mt-1">
                    {{ $analysisResult['summary']['slow_count'] }} <span class="text-xs font-normal text-gray-500">barang</span>
                </div>
                <p class="text-xs text-gray-500 mt-2">Barang yang jarang terjual.</p>
            </div>

            <!-- Non Moving -->
            <div class="bg-white rounded-lg border border-gray-300 shadow-sm p-5">
                <span class="text-xs font-bold uppercase text-gray-700 tracking-wider">Non Moving (N)</span>
                <div class="text-2xl font-extrabold text-gray-900 mt-1">
                    {{ $analysisResult['summary']['non_count'] }} <span class="text-xs font-normal text-gray-500">barang</span>
                </div>
                <p class="text-xs text-gray-500 mt-2">Barang yang tidak terjual.</p>
            </div>
        </div>

        <!-- Tabel Hasil Analisis (tabel keputusan) -->
        <div class="bg-white rounded-lg border border-gray-200 shadow-sm overflow-hidden">
            <div class="p-5 border-b border-gray-200 flex flex-col sm:flex-row items-start sm:items-center justify-between gap-4">
                <div>
                    <h3 class="text-base font-semibold text-gray-900">Hasil Analisis Persediaan</h3>
                    <p class="text-xs text-gray-500 mt-0.5">
                        Periode: <strong>{{ $tglSingkat($startDateStr) }}</strong> – <strong>{{ $tglSingkat($endDateStr) }}</strong> ({{ $analysisResult['period']['days'] }} hari).
                    </p>
                </div>

                <!-- Tombol Simpan/Terapkan Hasil -->
                <form id="applyForm" action="{{ route('analysis.apply') }}" method="POST">
                    @csrf
                    <input type="hidden" name="start_date" value="{{ $startDateStr }}">
                    <input type="hidden" name="end_date" value="{{ $endDateStr }}">
                    <button type="button" onclick="openApplyModal()"
                        class="px-5 py-2.5 bg-gray-900 hover:bg-gray-800 text-white text-xs font-bold rounded-md shadow-sm transition-colors">
                        Terapkan Hasil Analisis
                    </button>
                </form>
            </div>

            <!-- Filter dan Pencarian -->
            <div class="p-4 border-b border-gray-200 flex flex-col sm:flex-row gap-3">
                <select id="filterPergerakan" onchange="filterHasil()"
                    class="rounded-md border border-gray-300 px-3 py-2 text-sm text-gray-900 focus:border-blue-500 focus:outline-none focus:ring-1 focus:ring-blue-500">
                    <option value="all">Semua</option>
                    <option value="F">Cepat</option>
                    <option value="S">Lambat</option>
                    <option value="N">Tidak Bergerak</option>
                </select>
                <input type="text" id="cariBarang" oninput="filterHasil()" placeholder="Cari nama barang..."
                    class="flex-1 rounded-md border border-gray-300 px-3 py-2 text-sm text-gray-900 placeholder-gray-400 focus:border-blue-500 focus:outline-none focus:ring-1 focus:ring-blue-500">
            </div>

            <div class="overflow-x-auto">
                <table class="w-full text-sm text-left text-gray-700">
                    <thead class="text-[11px] text-gray-500 uppercase bg-gray-50 border-b border-gray-200">
                        <tr>
                            <th class="py-3 px-3">Kode</th>
                            <th class="py-3 px-4">Barang</th>
                            <th class="py-3 px-3 text-center">Pergerakan</th>
                            <th class="py-3 px-3 text-center">Batas Pesan</th>
                            <th class="py-3 px-3 text-center">Status</th>
                        </tr>
                    </thead>
                    <tbody id="hasilBody" class="divide-y divide-gray-200">
                        @foreach ($analysisResult['items'] as $row)
                            <tr class="hasil-row cursor-pointer hover:bg-gray-50 transition-colors"
                                data-kategori="{{ $row['fsn_category'] }}"
                                data-nama="{{ strtolower($row['item_name']) }}"
                                data-detail='@json(array_merge($row, ['days' => $analysisResult['period']['days']]))'
                                onclick="openDetailModal(this)">
                                <td class="py-3 px-3 font-mono font-semibold text-gray-900">{{ $row['item_code'] }}</td>
                                <td class="py-3 px-4 font-medium text-gray-900">{{ $row['item_name'] }}</td>
                                <td class="py-3 px-3 text-center">
                                    @if ($row['fsn_category'] === 'F')
                                        <span class="inline-flex items-center gap-1.5"><span class="w-2.5 h-2.5 rounded-full bg-green-500"></span>Cepat</span>
                                    @elseif ($row['fsn_category'] === 'S')
                                        <span class="inline-flex items-center gap-1.5"><span class="w-2.5 h-2.5 rounded-full bg-yellow-500"></span>Lambat</span>
                                    @else
                                        <span class="inline-flex items-center gap-1.5"><span class="w-2.5 h-2.5 rounded-full bg-gray-400"></span>Tidak Bergerak</span>
                                    @endif
                                </td>
                                <td class="py-3 px-3 text-center font-mono font-bold text-gray-900">
                                    {{ $row['order_threshold'] > 0 ? $row['order_threshold'] : '—' }}
                                </td>
                                <td class="py-3 px-3 text-center">
                                    @if ($row['stock'] == 0)
                                        <span class="inline-flex items-center gap-1.5"><span class="w-2.5 h-2.5 rounded-full bg-red-500"></span>Habis</span>
                                    @elseif ($row['fsn_category'] !== 'N' && $row['is_low_stock'])
                                        <span class="inline-flex items-center gap-1.5"><span class="w-2.5 h-2.5 rounded-full bg-orange-500"></span>Menipis</span>
                                    @else
                                        <span class="inline-flex items-center gap-1.5"><span class="w-2.5 h-2.5 rounded-full bg-green-500"></span>Aman</span>
                                    @endif
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
                <p id="hasilKosong" class="hidden text-center text-sm text-gray-500 py-8">
                    Tidak ada barang yang cocok dengan filter atau pencarian.
                </p>
            </div>
            <p class="text-xs text-gray-400 px-5 py-3 border-t border-gray-200">
                Klik salah satu barang untuk melihat detail analisis dan cara hasil ini dihitung.
            </p>
        </div>
    @endif
</div>

    <!-- Modal Detail Barang -->
    <div id="detailModal" class="fixed inset-0 z-50 flex items-center justify-center p-4 hidden" role="dialog" aria-modal="true" aria-labelledby="detailModalTitle">
        <div class="fixed inset-0 bg-gray-900/60 backdrop-blur-md transition-opacity duration-300" onclick="closeDetailModal()"></div>

        <div class="relative bg-white rounded-2xl border border-gray-200 shadow-2xl w-full p-6 transform transition-all z-10 max-h-[90vh] overflow-y-auto" style="max-width: 32rem;">
            <div class="flex items-start justify-between gap-4">
                <div>
                    <h3 id="detailNama" class="text-xl font-bold text-gray-900 leading-snug"></h3>
                    <p id="detailKode" class="text-sm text-gray-500 mt-0.5"></p>
                </div>
                <button type="button" onclick="closeDetailModal()"
                    class="shrink-0 text-gray-400 hover:text-gray-600 p-1.5 rounded-lg hover:bg-gray-100 transition-colors focus:outline-none"
                    aria-label="Tutup Detail">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                    </svg>
                </button>
            </div>

            <div class="mt-3 flex flex-wrap items-center gap-2">
                <span id="detailBadge" class="px-2.5 py-1 rounded-full text-xs font-bold"></span>
                <span id="detailStatusPill"></span>
            </div>

            <div id="detailTakeaway" class="mt-4 rounded-xl px-4 py-3 text-sm font-medium"></div>

            <div class="mt-5 grid grid-cols-2 gap-3">
                <div class="bg-gray-50 border border-gray-200 rounded-xl p-3">
                    <p class="text-xs text-gray-500">Total terjual</p>
                    <p id="detailTerjual" class="mt-1 text-base font-bold text-gray-900"></p>
                </div>
                <div class="bg-gray-50 border border-gray-200 rounded-xl p-3">
                    <p class="text-xs text-gray-500">Stok saat ini</p>
                    <p id="detailStok" class="mt-1 text-base font-bold text-gray-900"></p>
                </div>
                <div class="bg-gray-50 border border-gray-200 rounded-xl p-3">
                    <p class="text-xs text-gray-500">Kecepatan perputaran</p>
                    <p id="detailTor" class="mt-1 text-base font-bold text-gray-900"></p>
                </div>
                <div class="bg-gray-50 border border-gray-200 rounded-xl p-3">
                    <p class="text-xs text-gray-500">Rata-rata stok</p>
                    <p id="detailRataStok" class="mt-1 text-base font-bold text-gray-900"></p>
                </div>
                <div id="detailPmaxTile" class="col-span-2 bg-gray-50 border border-gray-200 rounded-xl p-3">
                    <p class="text-xs text-gray-500">Penjualan tertinggi/hari</p>
                    <p id="detailPmax" class="mt-1 text-base font-bold text-gray-900"></p>
                </div>
            </div>

            <h4 class="text-xs font-bold uppercase tracking-wider text-gray-500 mt-6 mb-1">Pengendalian Stok</h4>
            <dl class="text-sm divide-y divide-gray-100">
                <div class="flex justify-between gap-4 py-2.5">
                    <dt class="text-gray-500">Stok pengaman</dt>
                    <dd id="detailSs" class="font-semibold text-gray-900"></dd>
                </div>
                <div class="flex justify-between gap-4 py-2.5">
                    <dt class="text-gray-500">Batas pesan</dt>
                    <dd id="detailBatas" class="font-semibold text-gray-900"></dd>
                </div>
                <div class="flex justify-between gap-4 py-2.5">
                    <dt class="text-gray-500">Waktu tunggu</dt>
                    <dd id="detailLead" class="font-semibold text-gray-900"></dd>
                </div>
            </dl>

            <details class="mt-6 rounded-lg border border-gray-200 bg-gray-50 p-4">
                <summary class="text-sm font-semibold text-gray-900 cursor-pointer">Bagaimana hasil ini dihitung?</summary>
                <div id="detailHitung" class="mt-3 space-y-1.5 text-xs text-gray-700 font-mono leading-relaxed"></div>
            </details>
        </div>
    </div>

    <!-- Modal Konfirmasi Terapkan Hasil Analisis -->
    <div id="applyConfirmModal" class="fixed inset-0 z-50 flex items-center justify-center p-4 hidden" role="dialog" aria-modal="true" aria-labelledby="applyModalTitle">
        <!-- Backdrop dengan Efek Blur -->
        <div class="fixed inset-0 bg-gray-900/60 backdrop-blur-md transition-opacity duration-300" onclick="closeApplyModal()"></div>

        <!-- Kartu Modal di Tengah Layar -->
        <div class="relative bg-white rounded-2xl border border-gray-200 shadow-2xl max-w-md w-full p-6 text-center transform transition-all z-10">
            <!-- Tombol Tutup X -->
            <button type="button" onclick="closeApplyModal()"
                class="absolute top-4 right-4 text-gray-400 hover:text-gray-600 p-1.5 rounded-lg hover:bg-gray-100 transition-colors focus:outline-none"
                aria-label="Tutup Konfirmasi">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                </svg>
            </button>

            <!-- Ikon Peringatan -->
            <div class="mx-auto w-14 h-14 rounded-full bg-amber-50 border border-amber-200 flex items-center justify-center text-amber-500 mb-4 shadow-sm">
                <svg class="w-8 h-8" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/>
                </svg>
            </div>

            <!-- Judul Modal -->
            <h3 id="applyModalTitle" class="text-lg font-bold text-gray-900 mb-2">
                Terapkan Hasil Analisis?
            </h3>

            <!-- Pesan Konfirmasi -->
            <div class="bg-amber-50/70 rounded-lg p-3.5 border border-amber-200/80 mb-5">
                <p class="text-sm text-gray-700 leading-relaxed">
                    Hasil analisis ini akan disimpan ke Data Barang dan dipakai sebagai ambang stok (kategori, stok pengaman, dan batas pesan). Ambang lama akan diganti.
                </p>
            </div>

            <!-- Tombol Aksi -->
            <div class="flex justify-center gap-3">
                <button type="button" onclick="closeApplyModal()"
                    class="px-5 py-2.5 bg-white border border-gray-300 hover:bg-gray-50 text-gray-700 text-sm font-semibold rounded-lg shadow-sm transition-colors focus:outline-none focus:ring-2 focus:ring-gray-400 focus:ring-offset-2">
                    Batal
                </button>
                <button type="button" onclick="confirmApply()" id="applyConfirmBtn"
                    class="px-5 py-2.5 bg-blue-600 hover:bg-blue-700 active:bg-blue-800 text-white text-sm font-semibold rounded-lg shadow-sm transition-colors focus:outline-none focus:ring-2 focus:ring-blue-500 focus:ring-offset-2">
                    Ya, Terapkan
                </button>
            </div>
        </div>
    </div>
@endsection

@push('scripts')
<script>
    function setPreset(start, end) {
        document.getElementById('start_date').value = start;
        document.getElementById('end_date').value = end;
    }

    // Modal Konfirmasi Terapkan Hasil Analisis
    function openApplyModal() {
        const modal = document.getElementById('applyConfirmModal');
        modal.classList.remove('hidden');
        const confirmBtn = document.getElementById('applyConfirmBtn');
        if (confirmBtn) confirmBtn.focus();
    }

    function closeApplyModal() {
        const modal = document.getElementById('applyConfirmModal');
        if (modal) {
            modal.classList.add('hidden');
        }
    }

    function confirmApply() {
        document.getElementById('applyForm').submit();
    }

    // Filter kategori + pencarian nama barang pada tabel hasil
    function filterHasil() {
        const kategori = document.getElementById('filterPergerakan').value;
        const cari = document.getElementById('cariBarang').value.trim().toLowerCase();
        let terlihat = 0;
        document.querySelectorAll('.hasil-row').forEach(function (row) {
            const cocokKategori = kategori === 'all' || row.dataset.kategori === kategori;
            const cocokNama = cari === '' || row.dataset.nama.indexOf(cari) !== -1;
            const tampil = cocokKategori && cocokNama;
            row.classList.toggle('hidden', !tampil);
            if (tampil) terlihat++;
        });
        document.getElementById('hasilKosong').classList.toggle('hidden', terlihat > 0);
    }

    // Format angka gaya Indonesia (koma desimal)
    function fmtID(n, desimal) {
        return Number(n).toFixed(desimal).replace('.', ',');
    }

    // Modal Detail Barang
    function openDetailModal(row) {
        const d = JSON.parse(row.dataset.detail);
        const badge = document.getElementById('detailBadge');
        document.getElementById('detailNama').textContent = d.item_name;
        document.getElementById('detailKode').textContent = d.code || '';
        if (d.fsn_category === 'F') {
            badge.textContent = 'FAST MOVING';
            badge.className = 'px-2.5 py-1 rounded-full text-xs font-bold bg-green-100 text-green-800';
        } else if (d.fsn_category === 'S') {
            badge.textContent = 'SLOW MOVING';
            badge.className = 'px-2.5 py-1 rounded-full text-xs font-bold bg-yellow-100 text-yellow-800';
        } else {
            badge.textContent = 'NON MOVING';
            badge.className = 'px-2.5 py-1 rounded-full text-xs font-bold bg-gray-100 text-gray-700';
        }
        document.getElementById('detailTerjual').textContent = d.p_pk + ' unit';
        document.getElementById('detailTor').textContent = fmtID(d.tor, 2) + ' kali/tahun';
        document.getElementById('detailRataStok').textContent = fmtID(d.p_rt, 1) + ' unit';
        document.getElementById('detailStok').textContent = d.stock + ' unit';
        const pmaxTile = document.getElementById('detailPmaxTile');
        if (d.fsn_category === 'F') {
            pmaxTile.classList.remove('hidden');
            document.getElementById('detailPmax').textContent = d.p_max + ' unit/hari';
        } else {
            pmaxTile.classList.add('hidden');
        }
        const statusPill = document.getElementById('detailStatusPill');
        const takeaway = document.getElementById('detailTakeaway');
        if (d.stock == 0) {
            statusPill.innerHTML = '<span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full text-xs font-bold bg-red-500 text-white"><span class="w-2 h-2 rounded-full bg-white"></span>Habis</span>';
            takeaway.textContent = (d.fsn_category === 'N' ? 'Stok kosong — pesan hanya saat ada permintaan.' : 'Stok kosong — barang ini tidak bisa dijual. Pesan segera.');
            takeaway.className = 'mt-4 rounded-xl px-4 py-3 text-sm font-medium bg-red-50 text-gray-900';
        } else if (d.fsn_category !== 'N' && d.is_low_stock) {
            statusPill.innerHTML = '<span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full text-xs font-bold bg-yellow-100 text-yellow-800"><span class="w-2 h-2 rounded-full bg-orange-500"></span>Menipis</span>';
            takeaway.textContent = 'Stok sudah di bawah batas pesan — segera pesan.';
            takeaway.className = 'mt-4 rounded-xl px-4 py-3 text-sm font-medium bg-yellow-100 text-yellow-800';
        } else if (d.fsn_category === 'N') {
            statusPill.innerHTML = '<span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full text-xs font-bold bg-green-100 text-green-800"><span class="w-2 h-2 rounded-full bg-green-500"></span>Aman</span>';
            takeaway.textContent = 'Barang on-demand — pesan hanya saat ada permintaan.';
            takeaway.className = 'mt-4 rounded-xl px-4 py-3 text-sm font-medium bg-gray-100 text-gray-700';
        } else {
            statusPill.innerHTML = '<span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full text-xs font-bold bg-green-100 text-green-800"><span class="w-2 h-2 rounded-full bg-green-500"></span>Aman</span>';
            takeaway.textContent = 'Stok masih di atas batas pesan — belum perlu pesan.';
            takeaway.className = 'mt-4 rounded-xl px-4 py-3 text-sm font-medium bg-green-100 text-green-800';
        }
        document.getElementById('detailSs').textContent = (d.safety_stock !== null ? d.safety_stock + ' unit' : '—');
        document.getElementById('detailBatas').textContent = (d.order_threshold > 0 ? d.order_threshold + ' unit' : '— (sesuai permintaan)');
        document.getElementById('detailLead').textContent = d.lead_time + ' hari';

        let hitung = '';
        hitung += 'Stok awal periode (P_aw) = ' + d.p_aw + '<br>';
        hitung += 'Pembelian periode (P_ms) = ' + d.p_ms + '<br>';
        hitung += 'Penjualan periode (P_pk) = ' + d.p_pk + '<br>';
        hitung += 'Stok akhir (P_ak) = P_aw + P_ms &minus; P_pk = ' + d.p_ak + '<br>';
        hitung += 'Rata-rata stok (P_rt) = (P_aw + P_ak) / 2 = ' + fmtID(d.p_rt, 1) + '<br>';
        hitung += 'TOR periode (TOR_p) = P_pk / P_rt = ' + fmtID(d.tor_p, 2) + '<br>';
        hitung += 'Waktu simpan (W_sp) = ' + d.days + ' / TOR_p = ' + fmtID(d.w_sp, 2) + ' hari<br>';
        hitung += 'TOR tahunan = 365 / W_sp = ' + fmtID(d.tor, 2) + ' &rarr; ' + (d.fsn_category === 'F' ? 'Fast Moving' : (d.fsn_category === 'S' ? 'Slow Moving' : 'Non Moving')) + '<br>';
        if (d.fsn_category === 'F' || d.fsn_category === 'S') {
            hitung += 'Rata-rata harian = P_pk / ' + d.days + ' = ' + fmtID(d.p_rt_daily, 2) + '<br>';
        }
        if (d.fsn_category === 'F') {
            hitung += 'Stok pengaman (SS) = (P_max &minus; rata-rata harian) x L = ' + d.safety_stock + '<br>';
            hitung += 'Batas pesan (ROP) = rata-rata harian x L + SS = ' + d.rop + '<br>';
        } else if (d.fsn_category === 'S') {
            hitung += 'Batas pesan (ROP) = ceil(rata-rata harian x L) = ' + d.rop + '<br>';
            hitung += 'Slow Moving tidak memakai Stok Pengaman.<br>';
        } else {
            hitung += 'Non Moving tidak memakai Stok Pengaman maupun Batas Pesan &mdash; pengadaan sesuai permintaan.<br>';
        }
        document.getElementById('detailHitung').innerHTML = hitung;

        document.getElementById('detailModal').classList.remove('hidden');
    }

    function closeDetailModal() {
        const modal = document.getElementById('detailModal');
        if (modal) {
            modal.classList.add('hidden');
        }
    }

    // Tutup popup jika tombol Escape ditekan
    document.addEventListener('keydown', function(e) {
        if (e.key === 'Escape') {
            closeApplyModal();
            closeDetailModal();
        }
    });
</script>
@endpush
