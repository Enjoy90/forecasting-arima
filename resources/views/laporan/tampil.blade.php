{{--
    Tampilan layar untuk keempat laporan operasional.

    Variabel yang diharapkan:
      $judul      nama laporan
      $dari, $sampai  rentang tanggal (Carbon)
      $laporan    array{kolom, baris, ringkasan, perataan?}
      $penyaring  penyaring tambahan khas tiap laporan
      $rute       nama route laporan yang sedang dibuka
--}}
<x-app-layout>
    <x-slot name="header">
        <div class="flex items-center justify-between gap-4">
            <div>
                <h2 class="font-semibold text-xl text-gray-800 leading-tight">{{ $judul }}</h2>
                <p class="text-sm text-gray-500 mt-0.5">
                    Periode {{ $dari->translatedFormat('d F Y') }} &ndash; {{ $sampai->translatedFormat('d F Y') }}
                </p>
            </div>

            <div class="flex items-center gap-2">
                <a href="{{ request()->fullUrlWithQuery(['unduh' => 'pdf']) }}"
                   class="inline-flex items-center px-4 py-2 bg-white border border-gray-300 rounded-md font-semibold text-xs text-gray-700 uppercase tracking-widest hover:bg-gray-50">
                    Unduh PDF
                </a>
                <a href="{{ request()->fullUrlWithQuery(['unduh' => 'excel']) }}"
                   class="inline-flex items-center px-4 py-2 bg-gray-800 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-gray-700">
                    Unduh Excel
                </a>
            </div>
        </div>
    </x-slot>

    <div class="w-full space-y-4">
        {{-- Penyaring --}}
        <div class="bg-white shadow-sm sm:rounded-lg px-4 py-4">
            <form method="GET" action="{{ route($rute) }}" class="flex flex-wrap items-end gap-3">
                <div>
                    <label for="dari" class="block text-xs font-medium text-gray-600 mb-1">Dari tanggal</label>
                    <x-text-input id="dari" type="date" name="dari" value="{{ $dari->format('Y-m-d') }}" class="text-sm" />
                </div>

                <div>
                    <label for="sampai" class="block text-xs font-medium text-gray-600 mb-1">Sampai tanggal</label>
                    <x-text-input id="sampai" type="date" name="sampai" value="{{ $sampai->format('Y-m-d') }}" class="text-sm" />
                </div>

                @foreach ($penyaring as $nama => $saring)
                    <div>
                        <label for="{{ $nama }}" class="block text-xs font-medium text-gray-600 mb-1">{{ $saring['label'] }}</label>
                        <select id="{{ $nama }}" name="{{ $nama }}"
                                class="text-sm border-gray-300 focus:border-indigo-500 focus:ring-indigo-500 rounded-md shadow-sm">
                            @foreach ($saring['pilihan'] as $nilai => $label)
                                <option value="{{ $nilai }}" @selected((string) $saring['nilai'] === (string) $nilai)>{{ $label }}</option>
                            @endforeach
                        </select>
                    </div>
                @endforeach

                <x-primary-button>Tampilkan</x-primary-button>
                <a href="{{ route($rute) }}" class="text-sm text-gray-500 hover:text-gray-700 pb-2">Reset</a>
            </form>
        </div>

        {{-- Ringkasan --}}
        @if ($laporan['ringkasan'] !== [])
            <div class="grid grid-cols-2 lg:grid-cols-4 gap-4">
                @foreach ($laporan['ringkasan'] as $label => $nilai)
                    <div class="bg-white shadow-sm sm:rounded-lg p-4">
                        <p class="text-xs font-semibold uppercase tracking-wide text-gray-500">{{ $label }}</p>
                        <p class="mt-1 text-xl font-semibold text-gray-900">{{ $nilai }}</p>
                    </div>
                @endforeach
            </div>
        @endif

        {{-- Tabel --}}
        <div class="bg-white shadow-sm sm:rounded-lg">
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-gray-200 text-sm">
                    <thead class="bg-gray-50">
                        <tr class="text-left text-xs font-semibold uppercase tracking-wide text-gray-500">
                            <th class="px-4 py-3 w-12">No</th>
                            @foreach ($laporan['kolom'] as $i => $kolom)
                                <th @class([
                                    'px-4 py-3',
                                    'text-right' => ($laporan['perataan'][$i] ?? 'left') === 'right',
                                ])>{{ $kolom }}</th>
                            @endforeach
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100">
                        @forelse ($laporan['baris'] as $n => $baris)
                            <tr class="hover:bg-gray-50">
                                <td class="px-4 py-3 text-gray-500">{{ $n + 1 }}</td>
                                @foreach ($baris as $i => $sel)
                                    <td @class([
                                        'px-4 py-3',
                                        'text-right text-gray-900' => ($laporan['perataan'][$i] ?? 'left') === 'right',
                                        'text-gray-700' => ($laporan['perataan'][$i] ?? 'left') !== 'right',
                                    ])>{{ $sel }}</td>
                                @endforeach
                            </tr>
                        @empty
                            <tr>
                                <td colspan="{{ count($laporan['kolom']) + 1 }}" class="px-4 py-10 text-center text-gray-500">
                                    Tidak ada data pada rentang tanggal ini.
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            @if ($laporan['baris'] !== [])
                <p class="px-4 py-3 border-t border-gray-200 text-xs text-gray-500">
                    {{ count($laporan['baris']) }} baris data.
                </p>
            @endif
        </div>
    </div>
</x-app-layout>
