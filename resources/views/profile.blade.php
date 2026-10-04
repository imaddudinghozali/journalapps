<x-app-layout title="Profil" description="Ambang peringatan kepatuhan dan pengaturan akun.">
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-ink leading-tight">
            {{ __('Profil') }}
        </h2>
    </x-slot>

    <div class="py-12">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8 space-y-6">
            {{-- Ambang kepatuhan naik ke atas: ini satu-satunya pengaturan yang
                 mengubah perilaku aplikasi. Nama, email, dan sandi adalah
                 kelengkapan akun yang jarang disentuh. --}}
            <div class="kaca panel p-4 sm:p-8">
                <div class="max-w-xl">
                    <livewire:profile.update-compliance-threshold-form />
                </div>
            </div>

            <div class="blok p-4 sm:p-8">
                <div class="max-w-xl">
                    <livewire:profile.update-profile-information-form />
                </div>
            </div>

            <div class="blok p-4 sm:p-8">
                <div class="max-w-xl">
                    <livewire:profile.update-password-form />
                </div>
            </div>

            {{-- Tingkat ketiga, dan sengaja tidak diberi permukaan: menghapus
                 akun bukan sesuatu yang perlu dibuat mengundang. --}}
            <div class="border-t border-line px-4 pt-8 sm:px-8">
                <div class="max-w-xl">
                    <livewire:profile.delete-user-form />
                </div>
            </div>
        </div>
    </div>
</x-app-layout>
