@extends('layouts.app')

@section('title', 'Transaksi Pembelian')
@section('page_title', 'Transaksi Pembelian')

@section('content')
<div class="space-y-6 sm:space-y-8">
    <!-- Model Pencatatan Pembelian 2 Kolom (Responsif Desktop, Tab, Mobile) -->
    <div class="grid grid-cols-1 lg:grid-cols-12 gap-5 lg:gap-6 items-start">
        <!-- Kolom Keranjang Pembelian (7 cols desktop, full width mobile/tab) -->
        <div class="lg:col-span-7 bg-white rounded-lg border border-gray-200 shadow-sm p-4 sm:p-6">
            <div class="flex items-center justify-between pb-3 sm:pb-4 border-b border-gray-200 mb-4">
                <div>
                    <h2 class="text-base font-semibold text-gray-900">Daftar Barang Masuk</h2>
                    <p class="text-xs text-gray-500">Mencatat barang yang diterima dari pemasok untuk menambah stok toko.</p>
                </div>
                <span class="text-xs text-gray-500" id="cartItemCount">0 item dipilih</span>
            </div>

            <form action="{{ route('purchases.store') }}" method="POST" id="purchaseForm">
                @csrf
                <!-- Pemasok transaksi: satu transaksi = satu pemasok.
                     Katalog di samping difilter menurut pemasok ini. -->
                <div class="mb-4">
                    <label for="supplierSelect" class="block text-xs font-semibold text-gray-600 mb-1">Pemasok</label>
                    <select name="supplier_id" id="supplierSelect" required onchange="onSupplierChange()"
                        class="w-full rounded-md border border-gray-300 px-3 py-2 text-sm text-gray-900 focus:border-blue-500 focus:outline-none focus:ring-1 focus:ring-blue-500">
                        <option value="">— Pilih Pemasok —</option>
                        @foreach ($suppliers as $supplier)
                            <option value="{{ $supplier->id }}" {{ old('supplier_id') == $supplier->id ? 'selected' : '' }}>
                                {{ $supplier->supplier_name }} ({{ $supplier->supplier_code }})
                            </option>
                        @endforeach
                    </select>
                    <p class="text-[11px] text-gray-500 mt-1">Seluruh barang dalam transaksi ini dicatat dibeli dari pemasok ini. Mengganti pemasok mengosongkan keranjang.</p>
                    @if ($suppliers->isEmpty())
                        <p class="text-[11px] text-red-600 mt-1">Belum ada pemasok. Buat dulu di Data Pemasok sebelum mencatat pembelian.</p>
                    @endif
                </div>
                <div class="overflow-x-auto min-h-[180px] sm:min-h-[220px]">
                    <table class="w-full table-fixed text-xs sm:text-sm text-left text-gray-700">
                        <thead class="text-xs text-gray-500 uppercase bg-gray-50 border-b border-gray-200">
                            <tr>
                                <th class="py-2.5 px-3">Barang</th>
                                <th class="py-2.5 px-3 text-right w-36">Harga Beli (Rp)</th>
                                <th class="py-2.5 px-3 text-center w-28">Jumlah</th>
                                <th class="py-2.5 px-3 text-right w-32">Subtotal</th>
                                <th class="py-2.5 px-2 text-center w-10">Aksi</th>
                            </tr>
                        </thead>
                        <tbody id="cartTableBody" class="divide-y divide-gray-200">
                            <tr id="emptyCartRow">
                                <td colspan="5" class="py-8 text-center text-gray-400 text-sm">
                                    Daftar pembelian masih kosong. Pilih barang dari katalog di bawah atau di samping.
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <!-- Total & Tombol Simpan -->
                <div class="mt-5 sm:mt-6 pt-4 border-t border-gray-200 flex flex-col sm:flex-row items-stretch sm:items-center justify-between gap-4">
                    <div>
                        <span class="text-xs text-gray-500 uppercase font-semibold">Total Nilai Pembelian</span>
                        <div class="text-xl sm:text-2xl font-bold text-gray-900" id="cartTotalText">Rp 0</div>
                    </div>
                    <button type="submit" id="submitBtn" disabled
                        class="w-full sm:w-auto px-6 py-2.5 bg-blue-600 hover:bg-blue-700 disabled:bg-gray-300 disabled:cursor-not-allowed text-white text-sm font-medium rounded-md transition-colors text-center">
                        Simpan Transaksi Pembelian
                    </button>
                </div>
            </form>
        </div>

        <!-- Kolom Katalog Barang & Pemasok (5 cols desktop, full width mobile/tab) -->
        <div class="lg:col-span-5 bg-white rounded-lg border border-gray-200 shadow-sm p-4 sm:p-6">
            <div class="pb-3 sm:pb-4 border-b border-gray-200 mb-4">
                <h2 class="text-base font-semibold text-gray-900 mb-2">Pilih Barang</h2>
                <input type="text" id="catalogSearch" placeholder="Cari nama atau kode barang..."
                    class="w-full rounded-md border border-gray-300 px-3 py-2 text-sm text-gray-900 placeholder-gray-400 focus:border-blue-500 focus:outline-none focus:ring-1 focus:ring-blue-500">
                <select id="catalogNeedFilter" onchange="applyCatalogFilter()"
                    class="w-full rounded-md border border-gray-300 px-3 py-2 text-sm text-gray-900 focus:border-blue-500 focus:outline-none focus:ring-1 focus:ring-blue-500 mt-2">
                    <option value="all">Semua barang</option>
                    <option value="need">Perlu dibeli</option>
                </select>
                <p id="catalogNeedHint" class="text-[11px] text-gray-500 mt-1">Menampilkan semua barang dari pemasok yang dipilih.</p>
                <p class="text-[11px] text-gray-500 mt-2">Tidak menemukan barang? <a href="{{ route('items.index') }}" class="text-blue-600 hover:underline font-medium">Kelola pemasok barang di Data Barang &rarr;</a></p>
            </div>

            <div id="catalogPlaceholder" class="py-8 text-center text-gray-400 text-sm">
                Pilih pemasok untuk menampilkan barang yang tersedia.
            </div>
            <div id="catalogEmpty" class="py-8 text-center text-gray-400 text-sm" style="display: none;">
                Tidak ada barang dari pemasok ini yang cocok dengan filter.
            </div>
            <div id="catalogList" class="overflow-y-auto max-h-[440px] divide-y divide-gray-100">
                @foreach ($items as $item)
                    <div class="py-3 flex items-center justify-between catalog-item"
                        data-name="{{ strtolower($item->item_name) }}"
                        data-code="{{ strtolower($item->item_code) }}"
                        data-supplier-ids="{{ $item->suppliers->pluck('id')->join(',') }}"
                        data-need="{{ ($item->fsn_category !== null && $item->order_threshold !== null && $item->stock <= $item->order_threshold) ? '1' : '0' }}">
                        <div class="pr-3">
                            <p class="text-sm font-medium text-gray-900 leading-snug">{{ $item->item_name }}</p>
                            <div class="text-xs text-gray-500 mt-0.5">
                                {{ $item->item_code }}
                            </div>
                            <div class="text-xs text-gray-600 mt-0.5">
                                Harga beli: <span class="font-semibold text-gray-800">Rp {{ number_format($item->purchase_price, 0, ',', '.') }}</span>
                                &bull; Stok saat ini: {{ $item->stock }}
                                &bull; Batas pesan: {{ ($item->order_threshold ?? 0) > 0 ? $item->order_threshold : '—' }}
                            </div>
                        </div>
                        <div>
                            <button type="button"
                                onclick="addToPurchase({{ $item->id }}, '{{ addslashes($item->item_name) }}', {{ $item->purchase_price }})"
                                class="px-3 py-1.5 bg-gray-100 hover:bg-blue-600 hover:text-white text-gray-700 rounded text-xs font-semibold transition-colors">
                                + Tambah
                            </button>
                        </div>
                    </div>
                @endforeach
            </div>
        </div>
    </div>

    <!-- Riwayat Transaksi Pembelian Terakhir -->
    <div class="bg-white rounded-lg border border-gray-200 shadow-sm p-4 sm:p-6">
        <h2 class="text-base font-semibold text-gray-900 mb-4">Riwayat Pembelian Terakhir</h2>
        <div class="overflow-x-auto">
            <table class="w-full text-sm text-left text-gray-700">
                <thead class="text-xs text-gray-500 uppercase bg-gray-50 border-b border-gray-200">
                    <tr>
                        <th class="py-3 px-4">No Transaksi</th>
                        <th class="py-3 px-4">Tanggal &amp; Jam</th>
                        <th class="py-3 px-4">Pemasok</th>
                        <th class="py-3 px-4">Petugas</th>
                        <th class="py-3 px-4">Barang Masuk</th>
                        <th class="py-3 px-4 text-right">Total Pembelian</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-200">
                    @forelse ($recentPurchases as $purchase)
                        <tr class="hover:bg-gray-50">
                            <td class="py-3 px-4 font-mono text-xs text-gray-500">BELI-{{ str_pad($purchase->id, 5, '0', STR_PAD_LEFT) }}</td>
                            <td class="py-3 px-4">
                                {{ Carbon\Carbon::parse($purchase->date)->translatedFormat('d M Y') }}
                                @if ($purchase->time)
                                    <span class="text-xs text-gray-400 block">{{ Carbon\Carbon::parse($purchase->time)->format('H:i') }}</span>
                                @endif
                            </td>
                            <td class="py-3 px-4">{{ $purchase->supplier->supplier_name ?? '-' }}</td>
                            <td class="py-3 px-4 font-medium text-gray-900">{{ $purchase->user->name ?? 'Kasir' }}</td>
                            <td class="py-3 px-4">
                                <span class="text-xs text-gray-600">
                                    {{ $purchase->details->map(fn ($d) => ($d->item->item_name ?? '-') . ' ×' . $d->quantity)->join(', ') }}
                                </span>
                            </td>
                            <td class="py-3 px-4 text-right font-bold text-gray-900">
                                Rp {{ number_format($purchase->total, 0, ',', '.') }}
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6" class="py-6 text-center text-gray-400">Belum ada riwayat transaksi pembelian.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>
@endsection

@push('scripts')
<script>
    let purchaseCart = {};

    function formatRupiah(num) {
        return 'Rp ' + Math.round(num).toString().replace(/\B(?=(\d{3})+(?!\d))/g, ".");
    }

    function addToPurchase(id, name, purchasePrice) {
        if (purchaseCart[id]) {
            purchaseCart[id].qty++;
        } else {
            purchaseCart[id] = {
                id: id,
                name: name,
                price: purchasePrice,
                qty: 1
            };
        }
        renderPurchaseCart();
    }

    function updatePurchaseQty(id, newQty) {
        if (!purchaseCart[id]) return;
        newQty = parseInt(newQty);
        if (isNaN(newQty) || newQty <= 0) {
            delete purchaseCart[id];
        } else {
            purchaseCart[id].qty = newQty;
        }
        renderPurchaseCart();
    }

    function removePurchaseItem(id) {
        delete purchaseCart[id];
        renderPurchaseCart();
    }

    function renderPurchaseCart() {
        const tbody = document.getElementById('cartTableBody');
        const countText = document.getElementById('cartItemCount');
        const totalText = document.getElementById('cartTotalText');
        const submitBtn = document.getElementById('submitBtn');

        const keys = Object.keys(purchaseCart);
        if (keys.length === 0) {
            tbody.innerHTML = `
                <tr id="emptyCartRow">
                    <td colspan="5" class="py-8 text-center text-gray-400 text-sm">
                        Daftar pembelian masih kosong. Klik tombol "+ Tambah" pada katalog di sebelah kanan.
                    </td>
                </tr>`;
            countText.innerText = '0 item dipilih';
            totalText.innerText = 'Rp 0';
            submitBtn.disabled = true;
            return;
        }

        let total = 0;
        let html = '';

        keys.forEach((id, index) => {
            const item = purchaseCart[id];
            const subtotal = item.qty * item.price;
            total += subtotal;

            html += `
                <tr>
                    <td class="py-3 px-3">
                        <div class="font-medium text-gray-900">${item.name}</div>
                        <input type="hidden" name="items[${index}][id]" value="${item.id}">
                    </td>
                    <td class="py-3 px-3 text-right">
                        <input type="number" name="items[${index}][purchase_price]" value="${item.price}" min="0" step="500" readonly
                            class="w-28 text-right text-xs border border-gray-300 bg-gray-100 text-gray-500 rounded py-1 px-2 focus:outline-none cursor-not-allowed font-mono">
                    </td>
                    <td class="py-3 px-3 text-center">
                        <div class="flex items-center justify-center space-x-1">
                            <button type="button" onclick="updatePurchaseQty(${item.id}, ${item.qty - 1})"
                                class="w-6 h-6 rounded bg-gray-100 hover:bg-gray-200 text-gray-700 font-bold text-xs">-</button>
                            <input type="number" name="items[${index}][quantity]" value="${item.qty}" min="1"
                                onchange="updatePurchaseQty(${item.id}, this.value)"
                                class="w-14 text-center text-xs border border-gray-300 rounded py-1 focus:outline-none focus:border-blue-500">
                            <button type="button" onclick="updatePurchaseQty(${item.id}, ${item.qty + 1})"
                                class="w-6 h-6 rounded bg-gray-100 hover:bg-gray-200 text-gray-700 font-bold text-xs">+</button>
                        </div>
                    </td>
                    <td class="py-3 px-3 text-right font-bold text-gray-900 font-mono whitespace-nowrap">
                        ${formatRupiah(subtotal)}
                    </td>
                    <td class="py-3 px-2 text-center">
                        <button type="button" onclick="removePurchaseItem(${item.id})"
                            class="text-red-500 hover:text-red-700 text-xs font-semibold">
                            Hapus
                        </button>
                    </td>
                </tr>
            `;
        });

        tbody.innerHTML = html;
        countText.innerText = keys.length + ' item dipilih';
        totalText.innerText = formatRupiah(total);
        // Tombol aktif hanya bila keranjang terisi DAN pemasok sudah dipilih.
        const supplierSelect = document.getElementById('supplierSelect');
        submitBtn.disabled = keys.length === 0 || !supplierSelect.value;
    }

    // Filter katalog gabungan: barang tampil hanya bila (a) cocok dengan
    // pencarian DAN (b) punya relasi dengan pemasok yang dipilih DAN (c)
    // lolos filter Perlu dibeli (bila dipilih: stok sudah mencapai atau di
    // bawah batas pesan; barang yang belum dianalisis tidak punya batas
    // sehingga tidak lolos filter ini).
    // Sebelum pemasok dipilih, semua baris disembunyikan dan placeholder tampil.
    function applyCatalogFilter() {
        const query = document.getElementById('catalogSearch').value.toLowerCase().trim();
        const supplierId = document.getElementById('supplierSelect').value;
        const needFilter = document.getElementById('catalogNeedFilter').value;
        // Keterangan di bawah dropdown mengikuti pilihan filter.
        const needHint = document.getElementById('catalogNeedHint');
        if (needHint) {
            needHint.textContent = needFilter === 'need'
                ? 'Menampilkan barang yang sudah waktunya dibeli.'
                : 'Menampilkan semua barang dari pemasok yang dipilih.';
        }
        const items = document.querySelectorAll('.catalog-item');
        const placeholder = document.getElementById('catalogPlaceholder');
        const emptyState = document.getElementById('catalogEmpty');
        let visibleCount = 0;

        items.forEach(item => {
            const name = item.getAttribute('data-name');
            const code = item.getAttribute('data-code');
            const supplierIds = (item.getAttribute('data-supplier-ids') || '').split(',').filter(Boolean);
            const matchesSearch = name.includes(query) || code.includes(query);
            const matchesSupplier = supplierId !== '' && supplierIds.includes(supplierId);
            const matchesNeed = needFilter === 'all' || item.getAttribute('data-need') === '1';
            if (matchesSearch && matchesSupplier && matchesNeed) {
                item.style.display = 'flex';
                visibleCount++;
            } else {
                item.style.display = 'none';
            }
        });

        placeholder.style.display = supplierId === '' ? 'block' : 'none';
        emptyState.style.display = (supplierId !== '' && visibleCount === 0) ? 'block' : 'none';
    }

    // Ganti pemasok di tengah transaksi: keranjang dikosongkan
    // (satu transaksi = satu pemasok; barang pilihan pemasok lama tidak
    // boleh terbawa diam-diam ke transaksi pemasok baru).
    function onSupplierChange() {
        purchaseCart = {};
        renderPurchaseCart();
        applyCatalogFilter();
    }

    // Filter Pencarian Katalog Pembelian
    document.getElementById('catalogSearch').addEventListener('input', applyCatalogFilter);

    // Terapkan filter saat halaman dibuka (menangani old('supplier_id')
    // setelah validasi gagal: katalog langsung sesuai pemasok lama).
    applyCatalogFilter();
</script>
@endpush
