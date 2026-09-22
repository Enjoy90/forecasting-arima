<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">Tambah Barang</h2>
    </x-slot>

    <div class="w-full">
        <div class="bg-white shadow-sm sm:rounded-lg p-6">
            <div class="mb-5 rounded-md border border-sky-200 bg-sky-50 px-4 py-3 text-sm text-sky-900">
                Barang baru selalu dimulai dengan <strong>stok 0</strong>. Saldo awal dimasukkan lewat
                mutasi stok, bukan lewat form ini, supaya setiap angka stok punya jejak asal-usul.
            </div>

            <form method="POST" action="{{ route('master.barang.store') }}">
                @csrf

                @include('master.barang.form')

                <div class="mt-6 flex items-center gap-3">
                    <x-primary-button>Simpan</x-primary-button>
                    <a href="{{ route('master.barang.index') }}" class="text-sm text-gray-600 hover:text-gray-900">Batal</a>
                </div>
            </form>
        </div>
    </div>
</x-app-layout>
