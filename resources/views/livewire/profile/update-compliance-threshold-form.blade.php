<?php

use Illuminate\Support\Facades\Auth;
use Livewire\Volt\Component;

new class extends Component
{
    public int $complianceThreshold = 80;

    public function mount(): void
    {
        $this->complianceThreshold = (int) Auth::user()->compliance_threshold;
    }

    public function updateComplianceThreshold(): void
    {
        $validated = $this->validate([
            'complianceThreshold' => ['required', 'integer', 'min:0', 'max:100'],
        ], [
            'complianceThreshold.required' => 'Isi ambang peringatannya.',
            'complianceThreshold.min' => 'Ambang berada antara 0 sampai 100.',
            'complianceThreshold.max' => 'Ambang berada antara 0 sampai 100.',
        ]);

        $user = Auth::user();
        $user->compliance_threshold = $validated['complianceThreshold'];
        $user->save();

        $this->dispatch('threshold-updated');
    }
};
?>

<section>
    <header>
        <h2 class="text-lg font-medium text-ink">Ambang peringatan kepatuhan</h2>
        <p class="mt-1 text-sm text-ink-muted">
            Saat mencatat trade, kamu diperingatkan bila skor kepatuhan di bawah angka ini. Peringatan tidak pernah
            memblokir penyimpanan. Trade yang melanggar aturan justru yang paling perlu tercatat.
        </p>
    </header>

    <form wire:submit="updateComplianceThreshold" class="mt-6 space-y-6">
        <div>
            <x-input-label for="complianceThreshold" value="Ambang (%)" />
            <x-text-input wire:model="complianceThreshold" id="complianceThreshold" type="number" min="0" max="100"
                class="mt-1 block w-32" />
            <x-input-error class="mt-2" :messages="$errors->get('complianceThreshold')" />
        </div>

        <div class="flex items-center gap-4">
            <x-primary-button>Simpan</x-primary-button>
            <x-action-message class="me-3" on="threshold-updated">Tersimpan.</x-action-message>
        </div>
    </form>
</section>
