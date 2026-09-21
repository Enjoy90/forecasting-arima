{{--
    Kerangka sidebar.

    JANGAN menambah menu di file ini.

    Proyek dikerjakan dua orang, jadi menu dipecah menjadi dua partial
    terpisah supaya tidak pernah ada konflik saat menggabungkan pekerjaan:

        sidebar-operasional.blade.php  -> Modul A
        sidebar-analisis.blade.php     -> Modul B

    Tambahkan menu baru ke partial modul masing-masing.
--}}

<!-- Overlay mobile, menutup sidebar saat area gelap di luar sidebar disentuh -->
<div x-show="sidebarOpen"
     x-transition:enter="transition-opacity ease-linear duration-200"
     x-transition:enter-start="opacity-0"
     x-transition:enter-end="opacity-100"
     x-transition:leave="transition-opacity ease-linear duration-150"
     x-transition:leave-start="opacity-100"
     x-transition:leave-end="opacity-0"
     @click="sidebarOpen = false"
     class="fixed inset-0 z-30 bg-gray-900/50 lg:hidden"
     style="display: none;"
></div>

<aside
    :class="sidebarOpen ? 'translate-x-0' : '-translate-x-full'"
    class="fixed inset-y-0 left-0 z-40 flex w-64 shrink-0 flex-col bg-gray-900 transition-transform duration-200 ease-in-out lg:static lg:translate-x-0"
>
    <div class="flex items-center gap-2 px-5 py-5">
        <div class="flex h-9 w-9 shrink-0 items-center justify-center rounded-lg bg-indigo-600 text-sm font-bold text-white">
            PS
        </div>
        <div class="min-w-0">
            <p class="truncate text-sm font-semibold leading-tight text-white">CV. Pande Sejahtera</p>
            <p class="truncate text-xs text-gray-400">Peramalan &amp; Perencanaan Stok</p>
        </div>
    </div>

    <nav id="sidebar-scroll" class="flex-1 overflow-y-auto px-3 pb-4 text-sm">
        <div class="space-y-0.5 pb-4">
            <a href="{{ route('dashboard') }}"
               @class([
                   'flex items-center gap-2.5 rounded-md px-3 py-2 font-medium transition',
                   'bg-indigo-600 text-white' => request()->routeIs('dashboard'),
                   'text-gray-300 hover:bg-gray-800 hover:text-white' => ! request()->routeIs('dashboard'),
               ])>
                <svg class="h-4.5 w-4.5 shrink-0" viewBox="0 0 20 20" fill="currentColor" style="width:1.125rem;height:1.125rem">
                    <path d="M9.293 2.293a1 1 0 011.414 0l7 7A1 1 0 0117 11h-1v6a1 1 0 01-1 1h-3a1 1 0 01-1-1v-3H9v3a1 1 0 01-1 1H5a1 1 0 01-1-1v-6H3a1 1 0 01-.707-1.707l7-7z" />
                </svg>
                Dashboard
            </a>
        </div>

        <div class="space-y-6">
            @include('partials.sidebar-operasional')

            @include('partials.sidebar-analisis')
        </div>
    </nav>

    {{--
        Sidebar reload penuh tiap ganti halaman (bukan SPA), jadi posisi scroll
        perlu disimpan manual. Tanpa ini, menu yang letaknya jauh di bawah
        (mis. Peramalan) selalu balik ke atas tiap kali diklik.

        Skrip ini SENGAJA inline & synchronous (bukan lewat app.js) supaya
        posisi scroll langsung diterapkan saat elemen nav baru saja di-parse,
        sebelum browser sempat menggambar sidebar di posisi atas. Kalau
        dipasang lewat app.js (dimuat belakangan), sidebar akan sempat
        kelihatan di atas dulu baru "meloncat", yang terlihat seperti kedip.
    --}}
    <script>
        (function () {
            var nav = document.getElementById('sidebar-scroll');
            if (! nav) return;

            var posisiTersimpan = sessionStorage.getItem('sidebarScroll');
            if (posisiTersimpan !== null) {
                nav.scrollTop = parseInt(posisiTersimpan, 10);
            }

            nav.addEventListener('scroll', function () {
                sessionStorage.setItem('sidebarScroll', String(nav.scrollTop));
            });
        })();
    </script>

    <div class="border-t border-gray-800 px-4 py-4">
        <div class="flex items-center gap-2.5">
            <div class="flex h-8 w-8 shrink-0 items-center justify-center rounded-full bg-gray-700 text-xs font-semibold text-white">
                {{ strtoupper(substr(auth()->user()->name ?? '?', 0, 1)) }}
            </div>
            <div class="min-w-0">
                <p class="truncate text-xs font-medium text-white">{{ auth()->user()->name }}</p>
                <p class="truncate text-xs text-gray-400">{{ ucfirst(auth()->user()->role ?? '') }}</p>
            </div>
        </div>
    </div>
</aside>
