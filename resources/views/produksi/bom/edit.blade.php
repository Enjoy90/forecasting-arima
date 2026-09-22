<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">Ubah Resep {{ $bom->kode_bom }}</h2>
    </x-slot>

    <div class="w-full">
        <div class="bg-white shadow-sm sm:rounded-lg p-6">
            <div class="mb-5 rounded-md border border-gray-200 bg-gray-50 px-4 py-3 text-sm text-gray-600">
                Perubahan resep <strong>tidak mengubah perintah produksi yang sudah dibuat</strong>. Setiap
                perintah menyalin rencana bahannya sendiri saat dibuat, jadi pekerjaan yang sedang berjalan
                tidak ikut bergeser.
            </div>

            <form method="POST" action="{{ route('produksi.bom.update', $bom) }}">
                @csrf
                @method('PUT')

                @include('produksi.bom.form')

                <div class="mt-6 flex items-center gap-3">
                    <x-primary-button>Perbarui Resep</x-primary-button>
                    <a href="{{ route('produksi.bom.show', $bom) }}" class="text-sm text-gray-600 hover:text-gray-900">Batal</a>
                </div>
            </form>
        </div>
    </div>
</x-app-layout>
