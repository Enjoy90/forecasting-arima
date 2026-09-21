<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">Order Pembelian Baru</h2>
    </x-slot>

    <div class="max-w-5xl">
        <div class="bg-white shadow-sm sm:rounded-lg p-6">
            <div class="mb-5 rounded-md border border-sky-200 bg-sky-50 px-4 py-3 text-sm text-sky-900">
                Membuat order <strong>belum menambah stok</strong>. Stok baru bertambah saat barang dinyatakan
                diterima pada halaman rincian order.
            </div>

            <form method="POST" action="{{ route('pembelian.order.store') }}">
                @csrf

                @include('pembelian.form')

                <div class="mt-6 flex items-center gap-3">
                    <x-primary-button>Simpan Order</x-primary-button>
                    <a href="{{ route('pembelian.order.index') }}" class="text-sm text-gray-600 hover:text-gray-900">Batal</a>
                </div>
            </form>
        </div>
    </div>
</x-app-layout>
