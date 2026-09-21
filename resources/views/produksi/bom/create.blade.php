<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">Resep BOM Baru</h2>
    </x-slot>

    <div class="max-w-5xl">
        <div class="bg-white shadow-sm sm:rounded-lg p-6">
            <form method="POST" action="{{ route('produksi.bom.store') }}">
                @csrf

                @include('produksi.bom.form')

                <div class="mt-6 flex items-center gap-3">
                    <x-primary-button>Simpan Resep</x-primary-button>
                    <a href="{{ route('produksi.bom.index') }}" class="text-sm text-gray-600 hover:text-gray-900">Batal</a>
                </div>
            </form>
        </div>
    </div>
</x-app-layout>
