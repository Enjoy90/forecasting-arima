<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">
            Perintah Baru &mdash; {{ $tahapan->nama_tahapan }}
        </h2>
    </x-slot>

    <div class="w-full">
        <div class="bg-white shadow-sm sm:rounded-lg p-6">
            <div class="mb-5 rounded-md border border-sky-200 bg-sky-50 px-4 py-3 text-sm text-sky-900">
                Membuat perintah <strong>belum menyentuh stok</strong>. Sistem hanya menyalin kebutuhan bahan
                dari resep sebagai rencana. Stok baru bergerak saat perintah dinyatakan selesai.
            </div>

            <form method="POST" action="{{ route('produksi.perintah.store', $tahapan->kode_tahapan) }}">
                @csrf

                @include('produksi.perintah.form')

                <div class="mt-6 flex items-center gap-3">
                    <x-primary-button>Simpan Perintah</x-primary-button>
                    <a href="{{ route('produksi.perintah.index', $tahapan->kode_tahapan) }}"
                       class="text-sm text-gray-600 hover:text-gray-900">Batal</a>
                </div>
            </form>
        </div>
    </div>
</x-app-layout>
