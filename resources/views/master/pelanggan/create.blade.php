<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">Tambah Pelanggan</h2>
    </x-slot>

    <div class="w-full">
        <div class="bg-white shadow-sm sm:rounded-lg p-6">
            <form method="POST" action="{{ route('master.pelanggan.store') }}">
                @csrf

                @include('master.pelanggan.form')

                <div class="mt-6 flex items-center gap-3">
                    <x-primary-button>Simpan</x-primary-button>
                    <a href="{{ route('master.pelanggan.index') }}" class="text-sm text-gray-600 hover:text-gray-900">Batal</a>
                </div>
            </form>
        </div>
    </div>
</x-app-layout>
