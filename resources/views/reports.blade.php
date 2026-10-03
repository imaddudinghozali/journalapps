<x-app-layout title="Laporan" description="Perbandingan hasil saat kamu patuh pada rules dan saat tidak.">
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-ink leading-tight">
            {{ __('Laporan') }}
        </h2>
    </x-slot>

    <div class="py-12 enter">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
            <livewire:reports.compliance />
        </div>
    </div>
</x-app-layout>
