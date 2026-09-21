<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">Tambah Tahapan Produksi</h2>
    </x-slot>

    <div class="max-w-3xl">
        <div class="bg-white shadow-sm sm:rounded-lg p-6">
            <form method="POST" action="{{ route('master.tahapan-produksi.store') }}">
                @csrf

                @include('master.tahapan-produksi.form')

                <div class="mt-6 flex items-center gap-3">
                    <x-primary-button>Simpan</x-primary-button>
                    <a href="{{ route('master.tahapan-produksi.index') }}" class="text-sm text-gray-600 hover:text-gray-900">Batal</a>
                </div>
            </form>
        </div>
    </div>
</x-app-layout>
