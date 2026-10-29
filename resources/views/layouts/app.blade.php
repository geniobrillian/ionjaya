<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', 'Sistem Persediaan') — UD Ion Jaya Motor</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="bg-gray-50 text-gray-900 antialiased h-screen flex overflow-hidden print:h-auto print:overflow-visible">
    <!-- Backdrop Overlay untuk Mobile & Tablet -->
    <div id="sidebarBackdrop" onclick="toggleSidebar()" class="fixed inset-0 bg-gray-900/60 z-40 hidden lg:hidden transition-opacity"></div>

    <!-- Sidebar Navigasi Responsif (Drawer di Mobile/Tablet, Tetap di Desktop) -->
    <aside id="sidebar"
        class="fixed inset-y-0 left-0 z-50 w-64 bg-gray-900 text-white h-screen flex flex-col justify-between shrink-0 select-none print:hidden transform -translate-x-full lg:translate-x-0 lg:static lg:inset-auto transition-transform duration-200 ease-in-out shadow-xl lg:shadow-none">
        <div class="flex-1 flex flex-col min-h-0">
            <!-- Header Sidebar -->
            <div class="p-5 sm:p-6 border-b border-gray-800 shrink-0 flex items-center justify-between">
                <a href="{{ Auth::user()->isSuperAdmin() ? route('dashboard') : route('sales.index') }}" class="block">
                    <span class="text-lg font-bold tracking-tight text-white block">ION JAYA MOTOR</span>
                    <span class="text-xs text-gray-400 block mt-0.5">Sistem Persediaan</span>
                </a>
                <!-- Tombol Tutup di Mobile / Tablet -->
                <button type="button" onclick="toggleSidebar()"
                    class="lg:hidden text-gray-400 hover:text-white p-1 rounded-md border border-gray-700 hover:border-gray-500 transition-colors"
                    title="Tutup Menu">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                    </svg>
                </button>
            </div>

            <!-- Daftar Menu -->
            <nav class="p-4 space-y-1 text-sm flex-1">
                @if (Auth::user()->isSuperAdmin())
                    <a href="{{ route('dashboard') }}"
                        class="flex items-center px-3 py-2 rounded-md font-medium transition-colors {{ request()->routeIs('dashboard') ? 'bg-blue-600 text-white' : 'text-gray-300 hover:bg-gray-800 hover:text-white' }}">
                        Dashboard
                    </a>
                @endif

                <p class="px-3 pt-3 pb-1 text-[10px] font-bold uppercase tracking-wider text-gray-500">Transaksi</p>
                <a href="{{ route('sales.index') }}"
                    class="flex items-center px-3 py-2 rounded-md font-medium transition-colors {{ request()->routeIs('sales.*') ? 'bg-blue-600 text-white' : 'text-gray-300 hover:bg-gray-800 hover:text-white' }}">
                    Transaksi Penjualan
                </a>
                <a href="{{ route('purchases.index') }}"
                    class="flex items-center px-3 py-2 rounded-md font-medium transition-colors {{ request()->routeIs('purchases.*') ? 'bg-blue-600 text-white' : 'text-gray-300 hover:bg-gray-800 hover:text-white' }}">
                    Transaksi Pembelian
                </a>

                <p class="px-3 pt-3 pb-1 text-[10px] font-bold uppercase tracking-wider text-gray-500">Stok</p>
                <a href="{{ route('stock.index') }}"
                    class="flex items-center px-3 py-2 rounded-md font-medium transition-colors {{ request()->routeIs('stock.*') ? 'bg-blue-600 text-white' : 'text-gray-300 hover:bg-gray-800 hover:text-white' }}">
                    Stok Barang
                </a>
                <a href="{{ route('recommendations.index') }}"
                    class="flex items-center px-3 py-2 rounded-md font-medium transition-colors {{ request()->routeIs('recommendations.*') ? 'bg-blue-600 text-white' : 'text-gray-300 hover:bg-gray-800 hover:text-white' }}">
                    Rekomendasi Pengadaan
                </a>

                @if (Auth::user()->isSuperAdmin())
                    <p class="px-3 pt-3 pb-1 text-[10px] font-bold uppercase tracking-wider text-gray-500">Analisis</p>
                    <a href="{{ route('analysis.index') }}"
                        class="flex items-center px-3 py-2 rounded-md font-medium transition-colors {{ request()->routeIs('analysis.*') ? 'bg-blue-600 text-white' : 'text-gray-300 hover:bg-gray-800 hover:text-white' }}">
                        Analisis FSN & ROP
                    </a>
                    <a href="{{ route('reports.index') }}"
                        class="flex items-center px-3 py-2 rounded-md font-medium transition-colors {{ request()->routeIs('reports.*') ? 'bg-blue-600 text-white' : 'text-gray-300 hover:bg-gray-800 hover:text-white' }}">
                        Laporan
                    </a>

                    <p class="px-3 pt-3 pb-1 text-[10px] font-bold uppercase tracking-wider text-gray-500">Master Data</p>
                    <a href="{{ route('items.index') }}"
                        class="flex items-center px-3 py-2 rounded-md font-medium transition-colors {{ request()->routeIs('items.*') ? 'bg-blue-600 text-white' : 'text-gray-300 hover:bg-gray-800 hover:text-white' }}">
                        Data Barang
                    </a>
                    <a href="{{ route('suppliers.index') }}"
                        class="flex items-center px-3 py-2 rounded-md font-medium transition-colors {{ request()->routeIs('suppliers.*') ? 'bg-blue-600 text-white' : 'text-gray-300 hover:bg-gray-800 hover:text-white' }}">
                        Data Pemasok
                    </a>
                    <a href="{{ route('imports.index') }}"
                        class="flex items-center px-3 py-2 rounded-md font-medium transition-colors {{ request()->routeIs('imports.*') ? 'bg-blue-600 text-white' : 'text-gray-300 hover:bg-gray-800 hover:text-white' }}">
                        Impor Data
                    </a>
                @endif
            </nav>
        </div>

        <!-- Profil & Tombol Keluar (Bagian Bawah Sidebar - Tetap) -->
        <div class="p-4 border-t border-gray-800 bg-gray-950/40 shrink-0">
            <div class="flex items-center justify-between gap-2">
                <div class="min-w-0">
                    <p class="text-sm font-medium text-white truncate">{{ Auth::user()->name }}</p>
                    <span class="inline-block mt-0.5 px-2 py-0.5 text-xs rounded font-medium {{ Auth::user()->isSuperAdmin() ? 'bg-blue-900 text-blue-200' : 'bg-gray-800 text-gray-300' }}">
                        {{ Auth::user()->isSuperAdmin() ? 'Super Admin' : 'Admin Kasir' }}
                    </span>
                </div>
                <form action="{{ route('logout') }}" method="POST" class="shrink-0">
                    @csrf
                    <button type="submit"
                        class="text-xs font-medium text-gray-400 hover:text-red-400 px-2.5 py-1.5 rounded border border-gray-700 hover:border-red-500 transition-colors"
                        title="Keluar dari sistem">
                        Keluar
                    </button>
                </form>
            </div>
        </div>
    </aside>

    <!-- Konten Utama (Area Scroll Mandiri) -->
    <div class="flex-1 flex flex-col min-w-0 h-screen overflow-hidden print:h-auto print:overflow-visible">
        <!-- Topbar Responsif (Tetap di atas) -->
        <header class="h-16 bg-white border-b border-gray-200 px-4 sm:px-6 lg:px-8 flex items-center justify-between shrink-0 print:hidden">
            <div class="flex items-center min-w-0">
                <!-- Tombol Hamburger Menu (Mobile & Tablet) -->
                <button type="button" onclick="toggleSidebar()"
                    class="lg:hidden mr-3 p-1.5 text-gray-600 hover:text-gray-900 rounded-md border border-gray-300 hover:bg-gray-100 transition-colors focus:outline-none"
                    aria-label="Buka Menu">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16"/>
                    </svg>
                </button>
                <h1 class="text-lg sm:text-xl font-semibold text-gray-900 truncate">
                    @yield('page_title', 'Halaman')
                </h1>
            </div>
            <div class="flex items-center space-x-3 text-xs text-gray-500 shrink-0">
                <span class="hidden md:inline">{{ Carbon\Carbon::now()->translatedFormat('l, d F Y') }}</span>
                <span class="hidden md:inline text-gray-300">|</span>
                <span class="font-medium text-gray-700 bg-gray-100 px-2 py-1 rounded">{{ Auth::user()->username }}</span>
            </div>
        </header>

        <!-- Area Isi Halaman (Scrollable) -->
        <main class="flex-1 p-4 sm:p-6 lg:p-8 bg-gray-50 overflow-y-auto print:p-0 print:overflow-visible">
            <!-- Flash Message Alerts -->
            @if (session('success'))
                <div class="mb-5 rounded-md bg-green-50 p-4 border border-green-200 flex items-center justify-between">
                    <p class="text-sm font-medium text-green-800">{{ session('success') }}</p>
                    <button type="button" onclick="this.parentElement.remove()" class="text-green-600 hover:text-green-800 text-sm font-bold">&times;</button>
                </div>
            @endif

            @if (session('error'))
                <div class="mb-5 rounded-md bg-red-50 p-4 border border-red-200 flex items-center justify-between">
                    <p class="text-sm font-medium text-red-800">{{ session('error') }}</p>
                    <button type="button" onclick="this.parentElement.remove()" class="text-red-600 hover:text-red-800 text-sm font-bold">&times;</button>
                </div>
            @endif

            @if ($errors->any())
                <div class="mb-5 rounded-md bg-red-50 p-4 border border-red-200">
                    <p class="text-sm font-bold text-red-800 mb-1">Terjadi kesalahan input:</p>
                    <ul class="list-disc list-inside text-sm text-red-700">
                        @foreach ($errors->all() as $error)
                            <li>{{ $error }}</li>
                        @endforeach
                    </ul>
                </div>
            @endif

            @yield('content')
        </main>

        <!-- Footer -->
        <footer class="bg-white border-t border-gray-200 py-3 sm:py-4 px-4 sm:px-8 text-center text-[11px] sm:text-xs text-gray-500 shrink-0 print:hidden">
            UD Ion Jaya Motor — Sistem Persediaan Sparepart Motor Menggunakan Metode FSN dan ROP
        </footer>
    </div>

    <!-- Script Kontrol Drawer Responsif -->
    <script>
        function toggleSidebar() {
            const sidebar = document.getElementById('sidebar');
            const backdrop = document.getElementById('sidebarBackdrop');
            if (sidebar.classList.contains('-translate-x-full')) {
                sidebar.classList.remove('-translate-x-full');
                backdrop.classList.remove('hidden');
            } else {
                sidebar.classList.add('-translate-x-full');
                backdrop.classList.add('hidden');
            }
        }
    </script>

    @stack('scripts')
</body>
</html>
