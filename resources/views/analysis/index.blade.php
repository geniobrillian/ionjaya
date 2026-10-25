@extends('layouts.app')

@section('title', 'Analisis FSN & ROP')
@section('page_title', 'Analisis FSN & ROP')

@section('content')
<div class="space-y-6">
    <!-- Kartu Pemilihan Periode Analisis -->
    <div class="bg-white rounded-lg border border-gray-200 shadow-sm p-6">
        <div class="mb-4">
            <h2 class="text-base font-semibold text-gray-900">Periode Pengamatan Transaksi</h2>
            <p class="text-xs text-gray-500 mt-0.5">
                Pilih rentang tanggal riwayat transaksi yang akan digunakan untuk menghitung perputaran barang (TOR), klasifikasi FSN, serta batas Safety Stock dan Reorder Point.
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
                        Proses Perhitungan
                    </button>
                </div>
            </div>

            <!-- Preset Pintas -->
            <div class="flex items-center gap-2 pt-2 text-xs text-gray-500">
                <span class="font-medium text-gray-600">Pilihan Cepat:</span>
                <button type="button" onclick="setPreset('2025-01-01', '2025-12-31')"
                    class="px-2.5 py-1 bg-gray-100 hover:bg-gray-200 text-gray-700 rounded border border-gray-300 transition-colors">
                    Tahun 2025 (Data Penelitian)
                </button>
                <button type="button" onclick="setPreset('{{ Carbon\Carbon::now()->subDays(90)->toDateString() }}', '{{ Carbon\Carbon::now()->toDateString() }}')"
                    class="px-2.5 py-1 bg-gray-100 hover:bg-gray-200 text-gray-700 rounded border border-gray-300 transition-colors">
                    90 Hari Terakhir
                </button>
            </div>
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
        <!-- 3 Kartu Ringkasan Kategori FSN -->
        <div class="grid grid-cols-1 md:grid-cols-3 gap-5">
            <!-- Fast Moving -->
            <div class="bg-white rounded-lg border border-green-300 shadow-sm p-5">
                <div class="flex items-center justify-between">
                    <div>
                        <span class="text-xs font-bold uppercase text-green-700 tracking-wider">Fast Moving (F)</span>
                        <div class="text-2xl font-extrabold text-gray-900 mt-1">
                            {{ $analysisResult['summary']['fast_count'] }} <span class="text-xs font-normal text-gray-500">barang</span>
                        </div>
                    </div>
                    <span class="px-2.5 py-1 rounded bg-green-100 text-green-800 text-xs font-bold">TOR > 3</span>
                </div>
                <p class="text-xs text-gray-500 mt-2">Dikelola dengan Safety Stock & ROP dinamis.</p>
            </div>

            <!-- Slow Moving -->
            <div class="bg-white rounded-lg border border-yellow-300 shadow-sm p-5">
                <div class="flex items-center justify-between">
                    <div>
                        <span class="text-xs font-bold uppercase text-yellow-700 tracking-wider">Slow Moving (S)</span>
                        <div class="text-2xl font-extrabold text-gray-900 mt-1">
                            {{ $analysisResult['summary']['slow_count'] }} <span class="text-xs font-normal text-gray-500">barang</span>
                        </div>
                    </div>
                    <span class="px-2.5 py-1 rounded bg-yellow-100 text-yellow-800 text-xs font-bold">1 &le; TOR &le; 3</span>
                </div>
                <p class="text-xs text-gray-500 mt-2">Dikelola dengan batas stok minimum statis.</p>
            </div>

            <!-- Non Moving -->
            <div class="bg-white rounded-lg border border-gray-300 shadow-sm p-5">
                <div class="flex items-center justify-between">
                    <div>
                        <span class="text-xs font-bold uppercase text-gray-700 tracking-wider">Non Moving (N)</span>
                        <div class="text-2xl font-extrabold text-gray-900 mt-1">
                            {{ $analysisResult['summary']['non_count'] }} <span class="text-xs font-normal text-gray-500">barang</span>
                        </div>
                    </div>
                    <span class="px-2.5 py-1 rounded bg-gray-100 text-gray-700 text-xs font-bold">TOR < 1</span>
                </div>
                <p class="text-xs text-gray-500 mt-2">Pengadaan murni sesuai permintaan (on-demand).</p>
            </div>
        </div>

        <!-- Tabel Hasil Perhitungan Lengkap -->
        <div class="bg-white rounded-lg border border-gray-200 shadow-sm overflow-hidden">
            <div class="p-5 border-b border-gray-200 flex flex-col sm:flex-row items-start sm:items-center justify-between gap-4">
                <div>
                    <h3 class="text-base font-semibold text-gray-900">Rincian Hasil Perhitungan Batch</h3>
                    <p class="text-xs text-gray-500 mt-0.5">
                        Periode: <strong>{{ Carbon\Carbon::parse($startDateStr)->translatedFormat('d M Y') }}</strong> s/d <strong>{{ Carbon\Carbon::parse($endDateStr)->translatedFormat('d M Y') }}</strong> ({{ $analysisResult['period']['days'] }} hari).
                    </p>
                </div>

                <!-- Tombol Simpan/Terapkan Hasil -->
                <form id="applyForm" action="{{ route('analysis.apply') }}" method="POST">
                    @csrf
                    <input type="hidden" name="start_date" value="{{ $startDateStr }}">
                    <input type="hidden" name="end_date" value="{{ $endDateStr }}">
                    <button type="button" onclick="openApplyModal()"
                        class="px-5 py-2.5 bg-gray-900 hover:bg-gray-800 text-white text-xs font-bold rounded-md shadow-sm transition-colors">
                        Terapkan Hasil ke Data Barang
                    </button>
                </form>
            </div>

            <div class="overflow-x-auto">
                <table class="w-full text-xs text-left text-gray-700">
                    <thead class="text-[11px] text-gray-500 uppercase bg-gray-50 border-b border-gray-200">
                        <tr>
                            <th class="py-3 px-3 text-center">No</th>
                            <th class="py-3 px-3">Kode</th>
                            <th class="py-3 px-4">Nama Barang</th>
                            <th class="py-3 px-3 text-right">Penjualan (P_pk)</th>
                            <th class="py-3 px-3 text-right">Rata-rata (P_rt)</th>
                            <th class="py-3 px-3 text-right">Nilai TOR</th>
                            <th class="py-3 px-3 text-center">Kategori</th>
                            <th class="py-3 px-3 text-center">P_max</th>
                            <th class="py-3 px-3 text-center">Lead Time</th>
                            <th class="py-3 px-3 text-center">Safety Stock</th>
                            <th class="py-3 px-3 text-center">ROP</th>
                            <th class="py-3 px-3 text-center">Batas Pesan</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-200">
                        @foreach ($analysisResult['items'] as $row)
                            <tr class="hover:bg-gray-50 transition-colors">
                                <td class="py-2.5 px-3 text-center text-gray-500 font-mono">{{ $loop->iteration }}</td>
                                <td class="py-2.5 px-3 font-mono font-semibold text-gray-900">{{ $row['item_code'] }}</td>
                                <td class="py-2.5 px-4 font-medium text-gray-900">
                                    {{ $row['item_name'] }}
                                    <span class="text-[10px] text-gray-400 block">{{ $row['unit'] }}</span>
                                </td>
                                <td class="py-2.5 px-3 text-right font-mono">{{ $row['p_pk'] }}</td>
                                <td class="py-2.5 px-3 text-right font-mono text-gray-600">{{ number_format($row['p_rt'], 1) }}</td>
                                <td class="py-2.5 px-3 text-right font-mono font-bold text-gray-900">{{ number_format($row['tor'], 2) }}</td>
                                <td class="py-2.5 px-3 text-center">
                                    @if ($row['fsn_category'] === 'F')
                                        <span class="px-2 py-0.5 rounded font-bold bg-green-100 text-green-800">F (Fast)</span>
                                    @elseif ($row['fsn_category'] === 'S')
                                        <span class="px-2 py-0.5 rounded font-bold bg-yellow-100 text-yellow-800">S (Slow)</span>
                                    @else
                                        <span class="px-2 py-0.5 rounded font-bold bg-gray-100 text-gray-700">N (Non)</span>
                                    @endif
                                </td>
                                <td class="py-2.5 px-3 text-center font-mono">{{ $row['p_max'] > 0 ? $row['p_max'] : '-' }}</td>
                                <td class="py-2.5 px-3 text-center font-mono text-gray-600">{{ $row['lead_time'] }} hr</td>
                                <td class="py-2.5 px-3 text-center font-mono {{ $row['safety_stock'] !== null ? 'font-semibold text-gray-900' : 'text-gray-400' }}">
                                    {{ $row['safety_stock'] !== null ? $row['safety_stock'] : '-' }}
                                </td>
                                <td class="py-2.5 px-3 text-center font-mono {{ $row['rop'] !== null ? 'font-bold text-blue-700' : 'text-gray-400' }}">
                                    {{ $row['rop'] !== null ? $row['rop'] : '-' }}
                                </td>
                                <td class="py-2.5 px-3 text-center font-mono">
                                    @if ($row['fsn_category'] === 'F')
                                        <span class="font-bold text-gray-900">{{ $row['order_threshold'] }}</span>
                                        <span class="text-[9px] text-gray-400 block">(ROP)</span>
                                    @elseif ($row['fsn_category'] === 'S')
                                        <span class="font-bold text-gray-900">{{ $row['order_threshold'] }}</span>
                                        <span class="text-[9px] text-gray-400 block">(Statis)</span>
                                    @else
                                        <span class="text-gray-400">0 (On-Demand)</span>
                                    @endif
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>
    @endif
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
                    Apakah Anda yakin ingin menerapkan hasil analisis FSN dan parameter ROP ini ke master data barang?
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

    // Tutup popup jika tombol Escape ditekan
    document.addEventListener('keydown', function(e) {
        if (e.key === 'Escape') {
            closeApplyModal();
        }
    });
</script>
@endpush
