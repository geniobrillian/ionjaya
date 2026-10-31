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
    <div class="bg-white rounded-lg border border-gray-200 shadow-sm p-4 sm:p-5">
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
        </form>
    </div>

    <!-- Kondisi: Tidak ada transaksi pada rentang tanggal -->
    @if (request()->has('process') && ! $hasTransactions)
        <div class="bg-yellow-50 border border-yellow-200 rounded-lg p-5 text-center">
            <h3 class="text-sm font-semibold text-yellow-900 mb-1">Tidak Ditemukan Data Transaksi</h3>
            <p class="text-xs text-yellow-700 max-w-lg mx-auto">
                Tidak ada catatan transaksi penjualan maupun pembelian pada rentang tanggal <strong>{{ Carbon\Carbon::parse($startDateStr)->translatedFormat('d F Y') }}</strong> s/d <strong>{{ Carbon\Carbon::parse($endDateStr)->translatedFormat('d F Y') }}</strong>. Silakan pilih rentang tanggal transaksi yang tersedia.
            </p>
        </div>
    @endif

    <!-- Kondisi: Hasil Analisis Berhasil Dihitung -->
    @if ($analysisResult)
        <!-- 3 Kartu Ringkasan Pergerakan Barang -->
        <div class="grid grid-cols-1 md:grid-cols-3 gap-4 sm:gap-5">
            <!-- Fast Moving -->
            <div class="bg-white rounded-lg border border-gray-200 shadow-sm p-4 sm:p-5">
                <span class="text-xs font-bold uppercase text-green-700 tracking-wider">Fast Moving (F)</span>
                <div class="text-xl sm:text-2xl font-extrabold text-gray-900 mt-1">
                    {{ $analysisResult['summary']['fast_count'] }} <span class="text-xs font-normal text-gray-500">barang</span>
                </div>
                <p class="text-xs text-gray-500 mt-2">Barang yang cepat terjual.</p>
            </div>

            <!-- Slow Moving -->
            <div class="bg-white rounded-lg border border-gray-200 shadow-sm p-4 sm:p-5">
                <span class="text-xs font-bold uppercase text-yellow-700 tracking-wider">Slow Moving (S)</span>
                <div class="text-xl sm:text-2xl font-extrabold text-gray-900 mt-1">
                    {{ $analysisResult['summary']['slow_count'] }} <span class="text-xs font-normal text-gray-500">barang</span>
                </div>
                <p class="text-xs text-gray-500 mt-2">Barang yang jarang terjual.</p>
            </div>

            <!-- Non Moving -->
            <div class="bg-white rounded-lg border border-gray-200 shadow-sm p-4 sm:p-5">
                <span class="text-xs font-bold uppercase text-gray-700 tracking-wider">Non Moving (N)</span>
                <div class="text-xl sm:text-2xl font-extrabold text-gray-900 mt-1">
                    {{ $analysisResult['summary']['non_count'] }} <span class="text-xs font-normal text-gray-500">barang</span>
                </div>
                <p class="text-xs text-gray-500 mt-2">Barang yang tidak terjual.</p>
            </div>
        </div>

        <!-- Tabel Hasil Analisis -->
        <div class="bg-white rounded-lg border border-gray-200 shadow-sm overflow-hidden">
            <div class="p-4 sm:p-5 border-b border-gray-200 flex flex-col sm:flex-row items-start sm:items-center justify-between gap-4">
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
                        class="w-full sm:w-auto px-4 py-2 bg-blue-600 hover:bg-blue-700 text-white text-xs font-semibold rounded-md shadow-sm transition-colors text-center whitespace-nowrap">
                        Terapkan Hasil Analisis
                    </button>
                </form>
            </div>

            <!-- Filter dan Pencarian -->
            <div class="p-4 border-b border-gray-200 flex flex-col sm:flex-row gap-3">
                <div class="w-full sm:w-48 shrink-0">
                    <select id="filterPergerakan" onchange="filterHasil()"
                        class="w-full rounded-md border border-gray-300 px-3 py-2 text-sm text-gray-900 focus:border-blue-500 focus:outline-none focus:ring-1 focus:ring-blue-500">
                        <option value="all">Semua</option>
                        <option value="F">Cepat</option>
                        <option value="S">Lambat</option>
                        <option value="N">Tidak Bergerak</option>
                    </select>
                </div>
                <div class="w-full flex-1">
                    <input type="text" id="cariBarang" oninput="filterHasil()" placeholder="Cari nama barang..."
                        class="w-full rounded-md border border-gray-300 px-3 py-2 text-sm text-gray-900 placeholder-gray-400 focus:border-blue-500 focus:outline-none focus:ring-1 focus:ring-blue-500">
                </div>
            </div>

            <div class="overflow-x-auto">
                <table class="w-full text-sm text-left text-gray-700">
                    <thead class="text-xs text-gray-500 uppercase bg-gray-50 border-b border-gray-200">
                        <tr>
                            <th class="py-3.5 px-4 w-28">Kode</th>
                            <th class="py-3.5 px-4">Barang</th>
                            <th class="py-3.5 px-4 text-center w-36">Pergerakan</th>
                            <th class="py-3.5 px-4 text-center w-32">Batas Pesan</th>
                            <th class="py-3.5 px-4 text-center w-32">Status</th>
                        </tr>
                    </thead>
                    <tbody id="hasilBody" class="divide-y divide-gray-200">
                        @foreach ($analysisResult['items'] as $row)
                            <tr class="hasil-row cursor-pointer hover:bg-gray-50 transition-colors"
                                data-kategori="{{ $row['fsn_category'] }}"
                                data-nama="{{ strtolower($row['item_name']) }}"
                                data-detail='@json(array_merge($row, ['days' => $analysisResult['period']['days']]))'
                                onclick="openDetailModal(this)">
                                <td class="py-3.5 px-4 font-mono text-xs font-semibold text-gray-900">{{ $row['item_code'] }}</td>
                                <td class="py-3.5 px-4 font-medium text-gray-900">{{ $row['item_name'] }}</td>
                                <td class="py-3.5 px-4 text-center">
                                    @if ($row['fsn_category'] === 'F')
                                        <span class="px-2.5 py-0.5 rounded-full text-xs font-semibold bg-green-100 text-green-800">Cepat</span>
                                    @elseif ($row['fsn_category'] === 'S')
                                        <span class="px-2.5 py-0.5 rounded-full text-xs font-semibold bg-yellow-100 text-yellow-800">Lambat</span>
                                    @else
                                        <span class="px-2.5 py-0.5 rounded-full text-xs font-semibold bg-gray-100 text-gray-700">Tidak Bergerak</span>
                                    @endif
                                </td>
                                <td class="py-3.5 px-4 text-center font-mono font-bold text-gray-900">
                                    {{ $row['order_threshold'] > 0 ? $row['order_threshold'] : '—' }}
                                </td>
                                <td class="py-3.5 px-4 text-center">
                                    @if ($row['stock'] == 0)
                                        <span class="px-2.5 py-0.5 rounded-full text-xs font-semibold bg-red-200 text-red-900">Habis</span>
                                    @elseif ($row['fsn_category'] !== 'N' && $row['is_low_stock'])
                                        <span class="px-2.5 py-0.5 rounded-full text-xs font-semibold bg-red-100 text-red-700">Menipis</span>
                                    @else
                                        <span class="px-2.5 py-0.5 rounded-full text-xs font-semibold bg-green-100 text-green-700">Aman</span>
                                    @endif
                                </td>
                            </tr>
                        @endforeach
                        <tr id="hasilKosong" class="hidden">
                            <td colspan="5" class="py-8 text-center text-gray-400 text-sm">
                                Tidak ada barang yang cocok dengan filter atau pencarian.
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
            <p class="text-xs text-gray-400 px-4 sm:px-5 py-3 border-t border-gray-200">
                Klik salah satu barang untuk melihat detail analisis dan cara hasil ini dihitung.
            </p>
        </div>
    @endif
</div>

<!-- Modal Detail Barang -->
<div id="detailModal" class="fixed inset-0 z-50 hidden bg-gray-900/50 backdrop-blur-none flex items-center justify-center p-4">
    <div class="bg-white rounded-lg border border-gray-200 shadow-lg max-w-lg w-full p-6 max-h-[90vh] overflow-y-auto">
        <div class="flex items-start justify-between pb-3 border-b border-gray-200 mb-4">
            <div>
                <h3 id="detailNama" class="text-base font-semibold text-gray-900"></h3>
                <p id="detailKode" class="text-xs text-gray-500 font-mono mt-0.5"></p>
            </div>
            <button type="button" onclick="closeDetailModal()" class="text-gray-400 hover:text-gray-600 text-lg font-bold">&times;</button>
        </div>

        <div class="flex items-center gap-2 mb-3">
            <span id="detailBadge" class="px-2.5 py-0.5 rounded-full text-xs font-semibold"></span>
            <span id="detailStatusPill"></span>
        </div>

        <div id="detailTakeaway" class="rounded-md px-3.5 py-2.5 text-xs font-medium mb-4"></div>

        <div class="grid grid-cols-2 gap-3 mb-4">
            <div class="bg-gray-50 border border-gray-200 rounded-md p-3">
                <p class="text-xs text-gray-500 font-medium">Total terjual</p>
                <p id="detailTerjual" class="mt-1 text-sm font-bold text-gray-900 font-mono"></p>
            </div>
            <div class="bg-gray-50 border border-gray-200 rounded-md p-3">
                <p class="text-xs text-gray-500 font-medium">Stok saat ini</p>
                <p id="detailStok" class="mt-1 text-sm font-bold text-gray-900 font-mono"></p>
            </div>
            <div class="bg-gray-50 border border-gray-200 rounded-md p-3">
                <p class="text-xs text-gray-500 font-medium">Kecepatan perputaran</p>
                <p id="detailTor" class="mt-1 text-sm font-bold text-gray-900 font-mono"></p>
            </div>
            <div class="bg-gray-50 border border-gray-200 rounded-md p-3">
                <p class="text-xs text-gray-500 font-medium">Rata-rata stok</p>
                <p id="detailRataStok" class="mt-1 text-sm font-bold text-gray-900 font-mono"></p>
            </div>
            <div id="detailPmaxTile" class="col-span-2 bg-gray-50 border border-gray-200 rounded-md p-3">
                <p class="text-xs text-gray-500 font-medium">Penjualan tertinggi/hari</p>
                <p id="detailPmax" class="mt-1 text-sm font-bold text-gray-900 font-mono"></p>
            </div>
        </div>

        <div class="border-t border-gray-200 pt-3">
            <h4 class="text-xs font-bold uppercase tracking-wider text-gray-500 mb-2">Pengendalian Stok</h4>
            <dl class="text-xs divide-y divide-gray-100 bg-gray-50 rounded-md border border-gray-200 px-3">
                <div class="flex justify-between py-2">
                    <dt class="text-gray-500">Stok pengaman</dt>
                    <dd id="detailSs" class="font-semibold text-gray-900 font-mono"></dd>
                </div>
                <div class="flex justify-between py-2">
                    <dt class="text-gray-500">Batas pesan</dt>
                    <dd id="detailBatas" class="font-semibold text-gray-900 font-mono"></dd>
                </div>
                <div class="flex justify-between py-2">
                    <dt class="text-gray-500">Waktu tunggu</dt>
                    <dd id="detailLead" class="font-semibold text-gray-900 font-mono"></dd>
                </div>
            </dl>
        </div>

        <details class="mt-4 rounded-md border border-gray-200 bg-gray-50 p-3">
            <summary class="text-xs font-semibold text-gray-900 cursor-pointer select-none">Bagaimana hasil ini dihitung?</summary>
            <div id="detailHitung" class="mt-2.5 space-y-1 text-xs text-gray-700 font-mono leading-relaxed bg-white p-3 rounded border border-gray-200"></div>
        </details>

        <div class="mt-5 pt-3 border-t border-gray-200 flex justify-end">
            <button type="button" onclick="closeDetailModal()"
                class="px-4 py-2 border border-gray-300 rounded-md text-sm text-gray-700 hover:bg-gray-50 font-medium">
                Tutup
            </button>
        </div>
    </div>
</div>

<!-- Modal Konfirmasi Terapkan Hasil Analisis -->
<div id="applyConfirmModal" class="fixed inset-0 z-50 hidden bg-gray-900/50 backdrop-blur-none flex items-center justify-center p-4">
    <div class="bg-white rounded-lg border border-gray-200 shadow-lg max-w-md w-full p-6 text-center">
        <div class="mx-auto w-12 h-12 rounded-full bg-amber-50 border border-amber-200 flex items-center justify-center text-amber-500 mb-3">
            <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/>
            </svg>
        </div>

        <h3 id="applyModalTitle" class="text-base font-semibold text-gray-900 mb-2">
            Terapkan Hasil Analisis?
        </h3>

        <div class="bg-amber-50 rounded-md p-3 border border-amber-200 mb-5 text-left">
            <p class="text-xs text-gray-700 leading-relaxed">
                Hasil analisis ini akan disimpan ke Data Barang dan dipakai sebagai ambang stok (kategori, stok pengaman, dan batas pesan). Ambang lama akan diganti.
            </p>
        </div>

        <div class="flex justify-end gap-2 pt-3 border-t border-gray-200">
            <button type="button" onclick="closeApplyModal()"
                class="px-4 py-2 bg-white border border-gray-300 hover:bg-gray-50 text-gray-700 text-sm font-medium rounded-md shadow-sm transition-colors">
                Batal
            </button>
            <button type="button" onclick="confirmApply()" id="applyConfirmBtn"
                class="px-4 py-2 bg-blue-600 hover:bg-blue-700 text-white text-sm font-medium rounded-md shadow-sm transition-colors">
                Ya, Terapkan
            </button>
        </div>
    </div>
</div>
@endsection

@push('scripts')
<script>
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
        document.getElementById('detailKode').textContent = d.item_code || '';
        if (d.fsn_category === 'F') {
            badge.textContent = 'FAST MOVING';
            badge.className = 'px-2.5 py-0.5 rounded-full text-xs font-semibold bg-green-100 text-green-800';
        } else if (d.fsn_category === 'S') {
            badge.textContent = 'SLOW MOVING';
            badge.className = 'px-2.5 py-0.5 rounded-full text-xs font-semibold bg-yellow-100 text-yellow-800';
        } else {
            badge.textContent = 'NON MOVING';
            badge.className = 'px-2.5 py-0.5 rounded-full text-xs font-semibold bg-gray-100 text-gray-700';
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
            statusPill.innerHTML = '<span class="px-2.5 py-0.5 rounded-full text-xs font-semibold bg-red-200 text-red-900">Habis</span>';
            takeaway.textContent = (d.fsn_category === 'N' ? 'Stok kosong — pesan hanya saat ada permintaan.' : 'Stok kosong — barang ini tidak bisa dijual. Pesan segera.');
            takeaway.className = 'rounded-md px-3.5 py-2.5 text-xs font-medium bg-red-50 text-red-900 border border-red-200 mb-4';
        } else if (d.fsn_category !== 'N' && d.is_low_stock) {
            statusPill.innerHTML = '<span class="px-2.5 py-0.5 rounded-full text-xs font-semibold bg-red-100 text-red-700">Menipis</span>';
            takeaway.textContent = 'Stok sudah di bawah batas pesan — segera pesan.';
            takeaway.className = 'rounded-md px-3.5 py-2.5 text-xs font-medium bg-yellow-50 text-yellow-900 border border-yellow-200 mb-4';
        } else if (d.fsn_category === 'N') {
            statusPill.innerHTML = '<span class="px-2.5 py-0.5 rounded-full text-xs font-semibold bg-gray-100 text-gray-700">Aman</span>';
            takeaway.textContent = 'Barang on-demand — pesan hanya saat ada permintaan.';
            takeaway.className = 'rounded-md px-3.5 py-2.5 text-xs font-medium bg-gray-50 text-gray-700 border border-gray-200 mb-4';
        } else {
            statusPill.innerHTML = '<span class="px-2.5 py-0.5 rounded-full text-xs font-semibold bg-green-100 text-green-700">Aman</span>';
            takeaway.textContent = 'Stok masih di atas batas pesan — belum perlu pesan.';
            takeaway.className = 'rounded-md px-3.5 py-2.5 text-xs font-medium bg-green-50 text-green-900 border border-green-200 mb-4';
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
