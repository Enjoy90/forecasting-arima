<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">Faktur Penjualan Baru</h2>
    </x-slot>

    <div class="w-full">
        @if (session('gagal'))
            <div class="mb-4 rounded-md border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-800">{{ session('gagal') }}</div>
        @endif

        <div class="bg-white shadow-sm sm:rounded-lg p-6">
            <div class="mb-5 rounded-md border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-900">
                Menyimpan faktur <strong>langsung mengurangi stok</strong>. Bila stok salah satu barang tidak
                mencukupi, seluruh faktur akan ditolak.
            </div>

            <form method="POST" action="{{ route('penjualan.faktur.store') }}">
                @csrf

                @include('penjualan.form')

                <div class="mt-6 flex items-center gap-3">
                    <x-primary-button>Simpan Faktur</x-primary-button>
                    <a href="{{ route('penjualan.faktur.index') }}" class="text-sm text-gray-600 hover:text-gray-900">Batal</a>
                </div>
            </form>
        </div>
    </div>
</x-app-layout>
