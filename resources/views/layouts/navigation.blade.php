<header class="flex h-16 shrink-0 items-center gap-4 border-b border-gray-200 bg-white px-4 sm:px-6 lg:px-8">
    <!-- Tombol buka sidebar, hanya tampil di layar kecil -->
    <button @click="sidebarOpen = true" class="text-gray-500 hover:text-gray-700 lg:hidden">
        <svg class="h-6 w-6" stroke="currentColor" fill="none" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16" />
        </svg>
    </button>

    <div class="min-w-0 flex-1">
        @isset($header)
            {{ $header }}
        @else
            <p class="truncate text-sm font-semibold text-gray-700">{{ config('app.name') }}</p>
        @endisset
    </div>

    <!-- Menu pengguna -->
    <x-dropdown align="right" width="48">
        <x-slot name="trigger">
            <button class="flex items-center gap-2 rounded-md px-2 py-1.5 text-sm hover:bg-gray-100">
                <div class="flex h-8 w-8 items-center justify-center rounded-full bg-indigo-600 text-xs font-semibold text-white">
                    {{ strtoupper(substr(Auth::user()->name ?? '?', 0, 1)) }}
                </div>
                <span class="hidden text-gray-700 sm:block">{{ Auth::user()->name }}</span>
                <svg class="h-4 w-4 text-gray-400" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor">
                    <path fill-rule="evenodd" d="M5.293 7.293a1 1 0 011.414 0L10 10.586l3.293-3.293a1 1 0 111.414 1.414l-4 4a1 1 0 01-1.414 0l-4-4a1 1 0 010-1.414z" clip-rule="evenodd" />
                </svg>
            </button>
        </x-slot>

        <x-slot name="content">
            <x-dropdown-link :href="route('profile.edit')">
                {{ __('Profile') }}
            </x-dropdown-link>

            <form method="POST" action="{{ route('logout') }}">
                @csrf

                <x-dropdown-link :href="route('logout')"
                        onclick="event.preventDefault();
                                    this.closest('form').submit();">
                    {{ __('Log Out') }}
                </x-dropdown-link>
            </form>
        </x-slot>
    </x-dropdown>
</header>
