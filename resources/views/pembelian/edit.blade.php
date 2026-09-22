<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">
            Ubah Order {{ $pembelian->no_pembelian }}
        </h2>
    </x-slot>

    <div class="w-full">
        <div class="bg-white shadow-sm sm:rounded-lg p-6">
            <form method="POST" action="{{ route('pembelian.order.update', $pembelian) }}">
                @csrf
                @method('PUT')

                @include('pembelian.form')

                <div class="mt-6 flex items-center gap-3">
                    <x-primary-button>Perbarui Order</x-primary-button>
                    <a href="{{ route('pembelian.order.show', $pembelian) }}" class="text-sm text-gray-600 hover:text-gray-900">Batal</a>
                </div>
            </form>
        </div>
    </div>
</x-app-layout>
