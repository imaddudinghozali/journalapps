<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-ink leading-tight">
            {{ __('Dashboard') }}
        </h2>
    </x-slot>

    <div class="py-12 enter">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
            <livewire:dashboard.overview />
        </div>
    </div>
</x-app-layout>
