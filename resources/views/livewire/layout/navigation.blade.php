<?php

use App\Livewire\Actions\Logout;
use Livewire\Volt\Component;

new class extends Component
{
    /**
     * Log the current user out of the application.
     */
    public function logout(Logout $logout): void
    {
        $logout();

        $this->redirect('/', navigate: true);
    }
}; ?>

{{-- Bilah mengambang, bukan menempel di tepi atas. Inilah yang membuat kaca
     terbaca sebagai kaca: ia butuh sesuatu di belakangnya untuk dikaburkan,
     dan konten yang lewat di bawahnya memberi itu. Menempel rata di tepi,
     yang di belakangnya cuma latar halaman - hasilnya tak beda dari panel
     buram. --}}
<nav x-data="{ open: false }" class="sticky top-0 z-50 px-3 pt-3 sm:px-4 sm:pt-4">
  {{-- overflow-hidden supaya menu responsif yang membuka di dalamnya ikut
       mengikuti lengkung bilahnya, bukan menyembul bersudut siku. --}}
  <div class="kaca mx-auto max-w-7xl overflow-hidden rounded-[1.75rem]">
    <!-- Primary Navigation Menu -->
    <div class="px-4 sm:px-6">
        <div class="flex justify-between h-16">
            <div class="flex">
                <!-- Logo -->
                <div class="shrink-0 flex items-center">
                    <a href="{{ route('dashboard') }}" wire:navigate>
                        <x-application-logo class="block h-9 w-auto fill-current text-ink" />
                    </a>
                </div>

                <!-- Navigation Links -->
                {{-- Ikon Phosphor berat reguler, satu keluarga di seluruh
                     aplikasi. Teksnya tetap ada: ikon mempercepat pemindaian,
                     ia tidak menggantikan label. --}}
                <div class="hidden space-x-8 sm:-my-px sm:ms-10 sm:flex">
                    <x-nav-link :href="route('dashboard')" :active="request()->routeIs('dashboard')" class="gap-2" wire:navigate>
                        <x-phosphor-gauge class="h-4 w-4 shrink-0" aria-hidden="true" />
                        {{ __('Dashboard') }}
                    </x-nav-link>
                    <x-nav-link :href="route('trades')" :active="request()->routeIs('trades')" class="gap-2" wire:navigate>
                        <x-phosphor-notebook class="h-4 w-4 shrink-0" aria-hidden="true" />
                        {{ __('Jurnal Trade') }}
                    </x-nav-link>
                    <x-nav-link :href="route('setups')" :active="request()->routeIs('setups')" class="gap-2" wire:navigate>
                        <x-phosphor-list-checks class="h-4 w-4 shrink-0" aria-hidden="true" />
                        {{ __('Setup & Rules') }}
                    </x-nav-link>
                    <x-nav-link :href="route('instruments')" :active="request()->routeIs('instruments')" class="gap-2" wire:navigate>
                        <x-phosphor-coins class="h-4 w-4 shrink-0" aria-hidden="true" />
                        {{ __('Instrumen') }}
                    </x-nav-link>
                    <x-nav-link :href="route('reports')" :active="request()->routeIs('reports')" class="gap-2" wire:navigate>
                        <x-phosphor-chart-bar class="h-4 w-4 shrink-0" aria-hidden="true" />
                        {{ __('Laporan') }}
                    </x-nav-link>
                </div>
            </div>

            <!-- Settings Dropdown -->
            <div class="hidden sm:flex sm:items-center sm:ms-6">
                <x-dropdown align="right" width="48">
                    <x-slot name="trigger">
                        <button class="inline-flex items-center px-3 py-2 border border-transparent text-sm leading-4 font-medium rounded-md text-ink-faint bg-surface hover:text-ink-muted focus:outline-none transition ease-in-out duration-150">
                            <div x-data="{{ json_encode(['name' => auth()->user()->name]) }}" x-text="name" x-on:profile-updated.window="name = $event.detail.name"></div>

                            <x-phosphor-caret-down class="ms-1 h-4 w-4" aria-hidden="true" />
                        </button>
                    </x-slot>

                    <x-slot name="content">
                        <x-dropdown-link :href="route('profile')" wire:navigate>
                            {{ __('Profile') }}
                        </x-dropdown-link>

                        <!-- Authentication -->
                        <button wire:click="logout" class="w-full text-start">
                            <x-dropdown-link>
                                {{ __('Log Out') }}
                            </x-dropdown-link>
                        </button>
                    </x-slot>
                </x-dropdown>
            </div>

            <!-- Hamburger -->
            <div class="-me-2 flex items-center sm:hidden">
                {{-- Dua ikon yang saling bergantian, bukan satu svg dua path:
                     path buatan tangan tadi berasal dari keluarga ikon yang
                     berbeda dan tebal garisnya tidak cocok dengan Phosphor. --}}
                <button @click="open = ! open" :aria-expanded="open ? 'true' : 'false'"
                        class="inline-flex items-center justify-center p-2 rounded-md text-ink-faint hover:text-ink hover:bg-surface-sunken focus:outline-none focus:bg-surface-sunken focus:text-ink transition duration-150 ease-in-out">
                    <span class="sr-only">Buka menu navigasi</span>
                    <x-phosphor-list class="h-6 w-6" ::class="{ 'hidden': open }" aria-hidden="true" />
                    <x-phosphor-x class="h-6 w-6 hidden" ::class="{ 'hidden': ! open }" aria-hidden="true" />
                </button>
            </div>
        </div>
    </div>

    <!-- Responsive Navigation Menu -->
    <div :class="{'block': open, 'hidden': ! open}" class="hidden sm:hidden">
        <div class="pt-2 pb-3 space-y-1">
            <x-responsive-nav-link :href="route('dashboard')" :active="request()->routeIs('dashboard')" wire:navigate>
                <span class="flex items-center gap-3">
                    <x-phosphor-gauge class="h-5 w-5 shrink-0" aria-hidden="true" />
                    {{ __('Dashboard') }}
                </span>
            </x-responsive-nav-link>
            <x-responsive-nav-link :href="route('trades')" :active="request()->routeIs('trades')" wire:navigate>
                <span class="flex items-center gap-3">
                    <x-phosphor-notebook class="h-5 w-5 shrink-0" aria-hidden="true" />
                    {{ __('Jurnal Trade') }}
                </span>
            </x-responsive-nav-link>
            <x-responsive-nav-link :href="route('setups')" :active="request()->routeIs('setups')" wire:navigate>
                <span class="flex items-center gap-3">
                    <x-phosphor-list-checks class="h-5 w-5 shrink-0" aria-hidden="true" />
                    {{ __('Setup & Rules') }}
                </span>
            </x-responsive-nav-link>
            <x-responsive-nav-link :href="route('instruments')" :active="request()->routeIs('instruments')" wire:navigate>
                <span class="flex items-center gap-3">
                    <x-phosphor-coins class="h-5 w-5 shrink-0" aria-hidden="true" />
                    {{ __('Instrumen') }}
                </span>
            </x-responsive-nav-link>
            <x-responsive-nav-link :href="route('reports')" :active="request()->routeIs('reports')" wire:navigate>
                <span class="flex items-center gap-3">
                    <x-phosphor-chart-bar class="h-5 w-5 shrink-0" aria-hidden="true" />
                    {{ __('Laporan') }}
                </span>
            </x-responsive-nav-link>
        </div>

        <!-- Responsive Settings Options -->
        <div class="pt-4 pb-1 border-t border-line">
            <div class="px-4">
                <div class="font-medium text-base text-ink" x-data="{{ json_encode(['name' => auth()->user()->name]) }}" x-text="name" x-on:profile-updated.window="name = $event.detail.name"></div>
                <div class="font-medium text-sm text-ink-faint">{{ auth()->user()->email }}</div>
            </div>

            <div class="mt-3 space-y-1">
                <x-responsive-nav-link :href="route('profile')" wire:navigate>
                    {{ __('Profile') }}
                </x-responsive-nav-link>

                <!-- Authentication -->
                <button wire:click="logout" class="w-full text-start">
                    <x-responsive-nav-link>
                        {{ __('Log Out') }}
                    </x-responsive-nav-link>
                </button>
            </div>
        </div>
    </div>
  </div>
</nav>
