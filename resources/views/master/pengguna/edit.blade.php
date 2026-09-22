<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">Ubah Pengguna {{ $pengguna->name }}</h2>
    </x-slot>

    <div class="w-full">
        @if ($pengguna->id === auth()->id())
            <div class="mb-4 rounded-md border border-sky-200 bg-sky-50 px-4 py-3 text-sm text-sky-900">
                Ini akun Anda sendiri. Role dan statusnya tidak dapat diubah dari sini, sebagai penjaga agar
                Anda tidak terkunci di luar sistem.
            </div>
        @endif

        <div class="bg-white shadow-sm sm:rounded-lg p-6">
            <form method="POST" action="{{ route('master.pengguna.update', $pengguna) }}">
                @csrf
                @method('PUT')

                @include('master.pengguna.form')

                <div class="mt-6 flex items-center gap-3">
                    <x-primary-button>Perbarui</x-primary-button>
                    <a href="{{ route('master.pengguna.index') }}" class="text-sm text-gray-600 hover:text-gray-900">Batal</a>
                </div>
            </form>
        </div>
    </div>
</x-app-layout>
