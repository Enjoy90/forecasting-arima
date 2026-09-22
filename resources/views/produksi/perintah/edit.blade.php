<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">
            Ubah Perintah {{ $produksi->no_produksi }}
        </h2>
    </x-slot>

    <div class="w-full">
        <div class="bg-white shadow-sm sm:rounded-lg p-6">
            <div class="mb-5 rounded-md border border-gray-200 bg-gray-50 px-4 py-3 text-sm text-gray-600">
                Mengubah resep atau jumlah target akan <strong>menghitung ulang rencana bahan</strong> dari awal.
                Perintah hanya dapat diubah selama masih berstatus Draft.
            </div>

            <form method="POST" action="{{ route('produksi.perintah.update', [$tahapan->kode_tahapan, $produksi]) }}">
                @csrf
                @method('PUT')

                @include('produksi.perintah.form')

                <div class="mt-6 flex items-center gap-3">
                    <x-primary-button>Perbarui Perintah</x-primary-button>
                    <a href="{{ route('produksi.perintah.show', [$tahapan->kode_tahapan, $produksi]) }}"
                       class="text-sm text-gray-600 hover:text-gray-900">Batal</a>
                </div>
            </form>
        </div>
    </div>
</x-app-layout>
