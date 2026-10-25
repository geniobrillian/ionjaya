@extends('layouts.app')

@section('title', 'Transaksi Penjualan')
@section('page_title', 'Transaksi Penjualan')

@section('content')
<div class="space-y-6 sm:space-y-8">
    <!-- Model Kasir 2 Kolom (Responsif Desktop, Tab, Mobile) -->
    <div class="grid grid-cols-1 lg:grid-cols-12 gap-5 lg:gap-6 items-start">
        <!-- Kolom Keranjang Penjualan (7 cols desktop, full width mobile/tab) -->
        <div class="lg:col-span-7 bg-white rounded-lg border border-gray-200 shadow-sm p-4 sm:p-6">
            <div class="flex items-center justify-between pb-3 sm:pb-4 border-b border-gray-200 mb-4">
                <h2 class="text-base font-semibold text-gray-900">Keranjang Penjualan</h2>
                <span class="text-xs text-gray-500" id="cartItemCount">0 item dipilih</span>
            </div>

            <form action="{{ route('sales.store') }}" method="POST" id="salesForm">
                @csrf
                <div class="overflow-x-auto min-h-[180px] sm:min-h-[220px]">
                    <table class="w-full table-fixed text-xs sm:text-sm text-left text-gray-700">
                        <thead class="text-xs text-gray-500 uppercase bg-gray-50 border-b border-gray-200">
                            <tr>
                                <th class="py-2.5 px-3">Barang</th>
                                <th class="py-2.5 px-3 text-right">Harga</th>
                                <th class="py-2.5 px-3 text-center w-28">Jumlah</th>
                                <th class="py-2.5 px-3 text-right w-32">Subtotal</th>
                                <th class="py-2.5 px-2 text-center w-10">Aksi</th>
                            </tr>
                        </thead>
                        <tbody id="cartTableBody" class="divide-y divide-gray-200">
                            <tr id="emptyCartRow">
                                <td colspan="5" class="py-8 text-center text-gray-400 text-sm">
                                    Keranjang masih kosong. Pilih sparepart dari katalog di bawah atau di samping.
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <!-- Total & Tombol Simpan -->
                <div class="mt-5 sm:mt-6 pt-4 border-t border-gray-200 flex flex-col sm:flex-row items-stretch sm:items-center justify-between gap-4">
                    <div>
                        <span class="text-xs text-gray-500 uppercase font-semibold">Total Pembayaran</span>
                        <div class="text-xl sm:text-2xl font-bold text-gray-900" id="cartTotalText">Rp 0</div>
                    </div>
                    <button type="submit" id="submitBtn" disabled
                        class="w-full sm:w-auto px-6 py-2.5 bg-blue-600 hover:bg-blue-700 disabled:bg-gray-300 disabled:cursor-not-allowed text-white text-sm font-medium rounded-md transition-colors text-center">
                        Simpan Transaksi Penjualan
                    </button>
                </div>
            </form>
        </div>

        <!-- Kolom Katalog Barang (5 cols desktop, full width mobile/tab) -->
        <div class="lg:col-span-5 bg-white rounded-lg border border-gray-200 shadow-sm p-4 sm:p-6">
            <div class="pb-3 sm:pb-4 border-b border-gray-200 mb-4">
                <h2 class="text-base font-semibold text-gray-900 mb-2">Katalog Sparepart</h2>
                <input type="text" id="catalogSearch" placeholder="Cari nama atau kode barang..."
                    class="w-full rounded-md border border-gray-300 px-3 py-2 text-sm text-gray-900 placeholder-gray-400 focus:border-blue-500 focus:outline-none focus:ring-1 focus:ring-blue-500">
            </div>

            <div class="overflow-y-auto max-h-[360px] sm:max-h-[440px] divide-y divide-gray-100">
                @foreach ($items as $item)
                    <div class="py-3 flex items-center justify-between catalog-item"
                        data-name="{{ strtolower($item->item_name) }}"
                        data-code="{{ strtolower($item->item_code) }}">
                        <div class="pr-3">
                            <p class="text-sm font-medium text-gray-900 leading-snug">{{ $item->item_name }}</p>
                            <div class="flex items-center gap-2 mt-0.5 text-xs text-gray-500">
                                <span>{{ $item->item_code }}</span>
                                <span>&bull;</span>
                                <span class="font-semibold text-gray-700">Rp {{ number_format($item->selling_price, 0, ',', '.') }}</span>
                                <span>&bull;</span>
                                <span class="{{ $item->stock <= 0 ? 'text-red-600 font-bold' : ($item->is_low_stock ? 'text-red-500 font-medium' : 'text-gray-600') }}">
                                    Stok: {{ $item->stock }} {{ $item->unit }}
                                </span>
                            </div>
                        </div>
                        <div>
                            @if ($item->stock > 0)
                                <button type="button"
                                    onclick="addToCart({{ $item->id }}, '{{ addslashes($item->item_name) }}', {{ $item->selling_price }}, {{ $item->stock }}, '{{ $item->unit }}')"
                                    class="px-3 py-1.5 bg-gray-100 hover:bg-blue-600 hover:text-white text-gray-700 rounded text-xs font-semibold transition-colors">
                                    + Tambah
                                </button>
                            @else
                                <span class="px-2 py-1 bg-red-100 text-red-700 rounded text-xs font-medium">Habis</span>
                            @endif
                        </div>
                    </div>
                @endforeach
            </div>
        </div>
    </div>

    <!-- Riwayat Transaksi Penjualan Terakhir -->
    <div class="bg-white rounded-lg border border-gray-200 shadow-sm p-4 sm:p-6">
        <h2 class="text-base font-semibold text-gray-900 mb-4">Riwayat Penjualan Terakhir</h2>
        <div class="overflow-x-auto">
            <table class="w-full text-sm text-left text-gray-700">
                <thead class="text-xs text-gray-500 uppercase bg-gray-50 border-b border-gray-200">
                    <tr>
                        <th class="py-3 px-4">No</th>
                        <th class="py-3 px-4">Tanggal</th>
                        <th class="py-3 px-4">Kasir</th>
                        <th class="py-3 px-4">Item Terjual</th>
                        <th class="py-3 px-4 text-right">Total Transaksi</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-200">
                    @forelse ($recentSales as $index => $sale)
                        <tr class="hover:bg-gray-50">
                            <td class="py-3 px-4 font-mono text-xs text-gray-500">TRX-{{ str_pad($sale->id, 5, '0', STR_PAD_LEFT) }}</td>
                            <td class="py-3 px-4">{{ Carbon\Carbon::parse($sale->date)->translatedFormat('d M Y') }}</td>
                            <td class="py-3 px-4 font-medium text-gray-900">{{ $sale->user->name ?? 'Kasir' }}</td>
                            <td class="py-3 px-4">
                                <span class="text-xs text-gray-600">
                                    {{ $sale->details->pluck('item.item_name')->filter()->join(', ') }}
                                </span>
                            </td>
                            <td class="py-3 px-4 text-right font-bold text-gray-900">
                                Rp {{ number_format($sale->total, 0, ',', '.') }}
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="5" class="py-6 text-center text-gray-400">Belum ada riwayat transaksi penjualan.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    <!-- Modal Peringatan Stok Habis / Batas Maksimal (Tengah & Samping Blur) -->
    <div id="stockLimitModal" class="fixed inset-0 z-50 flex items-center justify-center p-4 hidden" role="dialog" aria-modal="true" aria-labelledby="stockModalTitle">
        <!-- Backdrop dengan Efek Blur Samping / Latar Belakang -->
        <div class="fixed inset-0 bg-gray-900/60 backdrop-blur-md transition-opacity duration-300" onclick="closeStockLimitModal()"></div>

        <!-- Kartu Modal di Tengah Layar -->
        <div class="relative bg-white rounded-2xl border border-gray-200 shadow-2xl max-w-md w-full p-6 text-center transform transition-all z-10">
            <!-- Tombol Tutup X -->
            <button type="button" onclick="closeStockLimitModal()"
                class="absolute top-4 right-4 text-gray-400 hover:text-gray-600 p-1.5 rounded-lg hover:bg-gray-100 transition-colors focus:outline-none"
                aria-label="Tutup Peringatan">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                </svg>
            </button>

            <!-- Ikon Peringatan Stok -->
            <div class="mx-auto w-14 h-14 rounded-full bg-amber-50 border border-amber-200 flex items-center justify-center text-amber-500 mb-4 shadow-sm">
                <svg class="w-8 h-8" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/>
                </svg>
            </div>

            <!-- Judul Modal -->
            <h3 id="stockModalTitle" class="text-lg font-bold text-gray-900 mb-2">
                Stok Tidak Mencukupi
            </h3>

            <!-- Pesan Peringatan Dinamis -->
            <div class="bg-amber-50/70 rounded-lg p-3.5 border border-amber-200/80 mb-5">
                <p id="stockModalMessage" class="text-sm text-gray-700 leading-relaxed">
                    Stok barang ini sudah habis atau seluruhnya telah dimasukkan ke keranjang penjualan.
                </p>
            </div>

            <!-- Tombol Konfirmasi -->
            <div class="flex justify-center">
                <button type="button" onclick="closeStockLimitModal()" id="stockModalOkBtn"
                    class="w-full sm:w-auto min-w-[130px] px-5 py-2.5 bg-blue-600 hover:bg-blue-700 active:bg-blue-800 text-white text-sm font-semibold rounded-lg shadow-sm transition-colors focus:outline-none focus:ring-2 focus:ring-blue-500 focus:ring-offset-2">
                    Mengerti
                </button>
            </div>
        </div>
    </div>
</div>
@endsection

@push('scripts')
<script>
    // State Keranjang Belanja
    let cart = {};

    function formatRupiah(num) {
        return 'Rp ' + Math.round(num).toString().replace(/\B(?=(\d{3})+(?!\d))/g, ".");
    }

    function escapeHtml(str) {
        if (!str) return '';
        return String(str)
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;')
            .replace(/'/g, '&#039;');
    }

    // Tampilkan Popup Modal Peringatan Stok
    function showStockLimitModal(itemName, maxStock, unit) {
        const modal = document.getElementById('stockLimitModal');
        const messageEl = document.getElementById('stockModalMessage');
        const okBtn = document.getElementById('stockModalOkBtn');

        if (messageEl) {
            messageEl.innerHTML = `Barang <span class="font-bold text-gray-900">"${escapeHtml(itemName)}"</span> hanya tersisa <span class="font-bold text-amber-700">${maxStock} ${escapeHtml(unit)}</span> dan seluruh stok yang ada sudah dimasukkan ke keranjang penjualan. Anda tidak dapat menambahkan barang ini lagi.`;
        }

        modal.classList.remove('hidden');
        if (okBtn) okBtn.focus();
    }

    // Tutup Popup Modal Peringatan Stok
    function closeStockLimitModal() {
        const modal = document.getElementById('stockLimitModal');
        if (modal) {
            modal.classList.add('hidden');
        }
    }

    // Tutup popup jika tombol Escape ditekan
    document.addEventListener('keydown', function(e) {
        if (e.key === 'Escape') {
            closeStockLimitModal();
        }
    });

    function addToCart(id, name, price, maxStock, unit) {
        if (cart[id]) {
            if (cart[id].qty < maxStock) {
                cart[id].qty++;
            } else {
                showStockLimitModal(name, maxStock, unit);
                return;
            }
        } else {
            if (maxStock <= 0) {
                showStockLimitModal(name, 0, unit);
                return;
            }
            cart[id] = { id, name, price, qty: 1, maxStock, unit };
        }
        renderCart();
    }

    function updateQty(id, newQty) {
        if (!cart[id]) return;
        newQty = parseInt(newQty);
        if (isNaN(newQty) || newQty <= 0) {
            delete cart[id];
        } else if (newQty > cart[id].maxStock) {
            const item = cart[id];
            cart[id].qty = cart[id].maxStock;
            renderCart();
            showStockLimitModal(item.name, item.maxStock, item.unit);
            return;
        } else {
            cart[id].qty = newQty;
        }
        renderCart();
    }

    function removeFromCart(id) {
        delete cart[id];
        renderCart();
    }

    function renderCart() {
        const tbody = document.getElementById('cartTableBody');
        const countText = document.getElementById('cartItemCount');
        const totalText = document.getElementById('cartTotalText');
        const submitBtn = document.getElementById('submitBtn');

        const keys = Object.keys(cart);
        if (keys.length === 0) {
            tbody.innerHTML = `
                <tr id="emptyCartRow">
                    <td colspan="5" class="py-8 text-center text-gray-400 text-sm">
                        Keranjang masih kosong. Klik tombol "+ Tambah" pada katalog di sebelah kanan.
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
            const item = cart[id];
            const subtotal = item.qty * item.price;
            total += subtotal;

            html += `
                <tr>
                    <td class="py-3 px-3">
                        <div class="font-medium text-gray-900">${item.name}</div>
                        <input type="hidden" name="items[${index}][id]" value="${item.id}">
                    </td>
                    <td class="py-3 px-3 text-right font-mono text-xs text-gray-600">
                        ${formatRupiah(item.price)}
                    </td>
                    <td class="py-3 px-3 text-center">
                        <div class="flex items-center justify-center space-x-1">
                            <button type="button" onclick="updateQty(${item.id}, ${item.qty - 1})"
                                class="w-6 h-6 rounded bg-gray-100 hover:bg-gray-200 text-gray-700 font-bold text-xs">-</button>
                            <input type="number" name="items[${index}][quantity]" value="${item.qty}" min="1" max="${item.maxStock}"
                                onchange="updateQty(${item.id}, this.value)"
                                class="w-12 text-center text-xs border border-gray-300 rounded py-1 focus:outline-none focus:border-blue-500">
                            <button type="button" onclick="updateQty(${item.id}, ${item.qty + 1})"
                                class="w-6 h-6 rounded bg-gray-100 hover:bg-gray-200 text-gray-700 font-bold text-xs">+</button>
                        </div>
                    </td>
                    <td class="py-3 px-3 text-right font-bold text-gray-900 whitespace-nowrap">
                        ${formatRupiah(subtotal)}
                    </td>
                    <td class="py-3 px-2 text-center">
                        <button type="button" onclick="removeFromCart(${item.id})"
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
        submitBtn.disabled = false;
    }

    // Filter Pencarian Katalog
    document.getElementById('catalogSearch').addEventListener('input', function(e) {
        const query = e.target.value.toLowerCase().trim();
        const items = document.querySelectorAll('.catalog-item');

        items.forEach(item => {
            const name = item.getAttribute('data-name');
            const code = item.getAttribute('data-code');
            if (name.includes(query) || code.includes(query)) {
                item.style.display = 'flex';
            } else {
                item.style.display = 'none';
            }
        });
    });
</script>
@endpush
