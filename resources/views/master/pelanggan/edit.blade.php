<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">
            Ubah Pelanggan {{ $pelanggan->kode_pelanggan }}
        </h2>
    </x-slot>

    <div class="max-w-3xl">
        <div class="bg-white shadow-sm sm:rounded-lg p-6">
            <form method="POST" action="{{ route('master.pelanggan.update', $pelanggan) }}">
                @csrf
                @method('PUT')

                @include('master.pelanggan.form')

                <div class="mt-6 flex items-center gap-3">
                    <x-primary-button>Perbarui</x-primary-button>
                    <a href="{{ route('master.pelanggan.index') }}" class="text-sm text-gray-600 hover:text-gray-900">Batal</a>
                </div>
            </form>
        </div>
    </div>
</x-app-layout>
