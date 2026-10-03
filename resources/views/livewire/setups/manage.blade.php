<?php

use App\Models\SetupRule;
use App\Models\TradingSetup;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;
use Livewire\Attributes\Computed;
use Livewire\Volt\Component;

new class extends Component
{
    public string $name = '';

    public string $description = '';

    public ?int $selectedSetupId = null;

    public string $ruleLabel = '';

    public int $ruleWeight = 1;

    public bool $ruleRequired = false;

    /*
    |----------------------------------------------------------------------
    | Semua query di bawah memakai model Eloquent, bukan where('user_id').
    | Global scope yang membatasi ke pengguna yang masuk. Lihat CLAUDE.md.
    |----------------------------------------------------------------------
    */

    #[Computed]
    public function activeSetups()
    {
        return TradingSetup::active()
            ->withCount(['rules as active_rules_count' => fn ($q) => $q->whereNull('archived_at')])
            ->orderBy('name')
            ->get();
    }

    #[Computed]
    public function archivedSetups()
    {
        return TradingSetup::archived()->orderBy('name')->get();
    }

    #[Computed]
    public function selectedSetup(): ?TradingSetup
    {
        if ($this->selectedSetupId === null) {
            return null;
        }

        // active(): properti publik bisa di-set langsung oleh klien, jadi setup
        // yang sudah diarsipkan tidak boleh lolos ke panel rules.
        return TradingSetup::active()->find($this->selectedSetupId);
    }

    #[Computed]
    public function activeRules()
    {
        if ($this->selectedSetupId === null) {
            return collect();
        }

        return SetupRule::active()
            ->where('trading_setup_id', $this->selectedSetupId)
            ->orderBy('position')
            ->orderBy('id')
            ->get();
    }

    public function createSetup(): void
    {
        // Livewire tidak melewati middleware TrimStrings, jadi "Order Block "
        // dan "Order Block" akan lolos sebagai dua nama berbeda.
        $this->name = trim($this->name);
        $this->description = trim($this->description);

        $validated = $this->validate([
            'name' => ['required', 'string', 'max:80', $this->uniqueNameRule()],
            'description' => ['nullable', 'string', 'max:1000'],
        ], [
            'name.required' => 'Nama setup wajib diisi.',
            'name.max' => 'Nama setup maksimal 80 karakter.',
            'description.max' => 'Deskripsi maksimal 1000 karakter.',
        ]);

        try {
            $setup = TradingSetup::create($validated);
        } catch (UniqueConstraintViolationException) {
            // Dua permintaan bersamaan bisa sama-sama lolos cek exists() di
            // atas. Unique index adalah penjaga terakhirnya; tampilkan sebagai
            // pesan validasi, bukan halaman 500.
            $this->addError('name', 'Kamu sudah punya setup dengan nama itu.');

            return;
        }

        $this->reset('name', 'description');
        $this->selectedSetupId = $setup->id;
        unset($this->activeSetups);

        $this->dispatch('setup-created');
    }

    public function selectSetup(int $id): void
    {
        // Terscope dan hanya yang aktif: ID milik pengguna lain maupun setup
        // yang sudah diarsipkan menghasilkan null, bukan data.
        $this->selectedSetupId = TradingSetup::active()->find($id)?->id;

        $this->reset('ruleLabel', 'ruleWeight', 'ruleRequired');
        $this->resetValidation();
    }

    public function archiveSetup(int $id): void
    {
        $setup = TradingSetup::findOrFail($id);
        $this->authorize('update', $setup);

        $setup->archived_at = now();
        $setup->save();

        if ($this->selectedSetupId === $id) {
            $this->selectedSetupId = null;
        }

        unset($this->activeSetups, $this->archivedSetups);
        $this->dispatch('setup-archived');
    }

    public function restoreSetup(int $id): void
    {
        $setup = TradingSetup::findOrFail($id);
        $this->authorize('update', $setup);

        $setup->archived_at = null;
        $setup->save();

        unset($this->activeSetups, $this->archivedSetups);
        $this->dispatch('setup-restored');
    }

    public function addRule(): void
    {
        $setup = $this->selectedSetup();

        if ($setup === null) {
            return;
        }

        $this->authorize('update', $setup);

        $this->validate([
            'ruleLabel' => ['required', 'string', 'max:120'],
            'ruleWeight' => ['required', 'integer', 'min:'.SetupRule::MIN_WEIGHT, 'max:'.SetupRule::MAX_WEIGHT],
            'ruleRequired' => ['boolean'],
        ], [
            'ruleLabel.required' => 'Isi kriteria entry-nya.',
            'ruleLabel.max' => 'Kriteria maksimal 120 karakter.',
            'ruleWeight.min' => 'Bobot antara '.SetupRule::MIN_WEIGHT.' sampai '.SetupRule::MAX_WEIGHT.'.',
            'ruleWeight.max' => 'Bobot antara '.SetupRule::MIN_WEIGHT.' sampai '.SetupRule::MAX_WEIGHT.'.',
        ]);

        // Checklist yang terlalu panjang membuat pengguna berhenti mencatat,
        // risiko Tinggi di PRD. Batasnya konstanta, bukan angka ajaib.
        //
        // Cek-lalu-simpan tidak atomik: dua tab yang menekan tombol bersamaan
        // saat rule aktif berjumlah 14 sama-sama lolos. Transaksi dan baris
        // setup yang dikunci membuat hitungannya tidak bisa saling mendahului.
        $tersimpan = DB::transaction(function () use ($setup): bool {
            TradingSetup::whereKey($setup->id)->lockForUpdate()->first();

            $jumlahAktif = SetupRule::active()
                ->where('trading_setup_id', $setup->id)
                ->count();

            if ($jumlahAktif >= TradingSetup::MAX_ACTIVE_RULES) {
                return false;
            }

            // max+1, bukan count: setelah ada rule diarsipkan, count() akan
            // menghasilkan posisi kembar dengan rule yang masih ada.
            $posisiTerakhir = (int) SetupRule::where('trading_setup_id', $setup->id)->max('position');

            $rule = new SetupRule([
                'label' => $this->ruleLabel,
                'weight' => $this->ruleWeight,
                'is_required' => $this->ruleRequired,
                'position' => $posisiTerakhir + 1,
            ]);
            $rule->trading_setup_id = $setup->id;
            $rule->save();

            return true;
        });

        if (! $tersimpan) {
            $this->addError('ruleLabel', 'Maksimal '.TradingSetup::MAX_ACTIVE_RULES.' rule aktif per setup. Arsipkan yang sudah tidak dipakai.');

            return;
        }

        $this->reset('ruleLabel', 'ruleWeight', 'ruleRequired');
        unset($this->activeRules, $this->activeSetups);

        $this->dispatch('rule-added');
    }

    public function archiveRule(int $id): void
    {
        $rule = SetupRule::findOrFail($id);
        $this->authorize('update', $rule);

        // Idempoten: panggilan kedua tidak boleh menggeser waktu arsipnya.
        if (! $rule->isArchived()) {
            $rule->archived_at = now();
            $rule->save();
        }

        unset($this->activeRules, $this->activeSetups);
        $this->dispatch('rule-archived');
    }

    /**
     * Keunikan nama dicek lewat model, bukan Rule::unique, supaya global scope
     * ikut berlaku. Rule::unique menembak query builder mentah dan akan melihat
     * nama milik pengguna lain.
     */
    protected function uniqueNameRule(): callable
    {
        return function (string $attribute, mixed $value, callable $fail): void {
            if (TradingSetup::where('name', $value)->exists()) {
                $fail('Kamu sudah punya setup dengan nama itu.');
            }
        };
    }
};
?>

<div class="space-y-6">
    {{-- Buat setup baru --}}
    <div class="blok p-4 sm:p-8">
        <div class="max-w-xl">
            <header>
                <h2 class="text-lg font-medium text-ink">Setup baru</h2>
                <p class="mt-1 text-sm text-ink-muted">
                    Satu setup adalah satu pola entry yang kamu kenali, misalnya Break of Structure atau Order Block.
                </p>
            </header>

            <form wire:submit="createSetup" class="mt-6 space-y-6">
                <div>
                    <x-input-label for="name" value="Nama setup" />
                    <x-text-input wire:model="name" id="name" type="text" class="mt-1 block w-full" placeholder="Break of Structure" />
                    <x-input-error class="mt-2" :messages="$errors->get('name')" />
                </div>

                <div>
                    <x-input-label for="description" value="Catatan (opsional)" />
                    <textarea wire:model="description" id="description" rows="2"
                        class="mt-1 block w-full border-line-strong focus:border-accent focus:ring-accent rounded-md shadow-sm"></textarea>
                    <x-input-error class="mt-2" :messages="$errors->get('description')" />
                </div>

                <div class="flex items-center gap-4">
                    <x-primary-button wire:loading.attr="disabled" wire:target="createSetup">
                        <span wire:loading.remove wire:target="createSetup">Simpan setup</span>
                        <span wire:loading wire:target="createSetup">Menyimpan</span>
                    </x-primary-button>
                    <x-action-message class="me-3" on="setup-created">Tersimpan.</x-action-message>
                </div>
            </form>
        </div>
    </div>

    {{-- Daftar setup aktif --}}
    <div class="blok p-4 sm:p-8">
        <header>
            <h2 class="text-lg font-medium text-ink">Setup aktif</h2>
            <p class="mt-1 text-sm text-ink-muted">Pilih satu setup untuk mengelola rules-nya.</p>
        </header>

        @if ($this->activeSetups->isEmpty())
            <p class="mt-4 text-sm text-ink-faint">Belum ada setup. Buat satu di atas.</p>
        @else
            <ul class="mt-4 divide-y divide-line">
                @foreach ($this->activeSetups as $setup)
                    <li class="py-3 flex items-center justify-between gap-4" wire:key="setup-{{ $setup->id }}">
                        <button type="button" wire:click="selectSetup({{ $setup->id }})" class="text-left flex-1">
                            <span class="font-medium text-ink {{ $selectedSetupId === $setup->id ? 'underline' : '' }}">
                                {{ $setup->name }}
                            </span>
                            <span class="block text-sm text-ink-faint">
                                {{ $setup->active_rules_count }} rule aktif
                                @if ($setup->description) &middot; {{ $setup->description }} @endif
                            </span>
                        </button>
                        <x-secondary-button wire:click="archiveSetup({{ $setup->id }})" wire:confirm="Arsipkan setup ini?" class="gap-2">
                            <x-phosphor-archive class="h-4 w-4" aria-hidden="true" />
                            Arsipkan
                        </x-secondary-button>
                    </li>
                @endforeach
            </ul>
        @endif
    </div>

    {{-- Rules untuk setup terpilih --}}
    @if ($this->selectedSetup)
        {{-- Panel: di halaman ini, menyusun rules adalah pekerjaannya. Dua
             blok di atas cuma jalan menuju ke sini. --}}
        <div class="panel spotlight pop p-4 sm:p-8" wire:key="panel-{{ $selectedSetupId }}">
            <header>
                <h2 class="text-lg font-medium text-ink">Rules: {{ $this->selectedSetup->name }}</h2>
                <p class="mt-1 text-sm text-ink-muted">
                    Kriteria yang harus terpenuhi sebelum entry. Bobot menentukan seberapa penting aturan itu
                    ({{ \App\Models\SetupRule::MIN_WEIGHT }}–{{ \App\Models\SetupRule::MAX_WEIGHT }}).
                </p>
            </header>

            @if ($this->activeRules->isNotEmpty())
                <ul class="mt-4 divide-y divide-line">
                    @foreach ($this->activeRules as $rule)
                        <li class="py-3 flex items-center justify-between gap-4" wire:key="rule-{{ $rule->id }}">
                            <div>
                                <span class="text-ink">{{ $rule->label }}</span>
                                <span class="ms-2 text-xs text-ink-faint">bobot {{ $rule->weight }}</span>
                                @if ($rule->is_required)
                                    <span class="ms-2 text-xs font-medium text-negative">wajib</span>
                                @endif
                            </div>
                            <x-secondary-button wire:click="archiveRule({{ $rule->id }})" class="gap-2">
                                <x-phosphor-archive class="h-4 w-4" aria-hidden="true" />
                                Arsipkan
                            </x-secondary-button>
                        </li>
                    @endforeach
                </ul>
            @else
                <p class="mt-4 text-sm text-ink-faint">Belum ada rule untuk setup ini.</p>
            @endif

            <form wire:submit="addRule" class="mt-6 space-y-4 max-w-xl">
                <div>
                    <x-input-label for="ruleLabel" value="Kriteria entry" />
                    <x-text-input wire:model="ruleLabel" id="ruleLabel" type="text" class="mt-1 block w-full" placeholder="Ada BOS di timeframe H4" />
                    <x-input-error class="mt-2" :messages="$errors->get('ruleLabel')" />
                </div>

                <div class="flex items-end gap-4">
                    <div>
                        <x-input-label for="ruleWeight" value="Bobot" />
                        <select wire:model="ruleWeight" id="ruleWeight"
                            class="mt-1 border-line-strong focus:border-accent focus:ring-accent rounded-md shadow-sm">
                            @for ($i = \App\Models\SetupRule::MIN_WEIGHT; $i <= \App\Models\SetupRule::MAX_WEIGHT; $i++)
                                <option value="{{ $i }}">{{ $i }}</option>
                            @endfor
                        </select>
                        <x-input-error class="mt-2" :messages="$errors->get('ruleWeight')" />
                    </div>

                    <label class="flex items-center gap-2 pb-2">
                        <input type="checkbox" wire:model="ruleRequired"
                            class="rounded border-line-strong text-accent shadow-sm focus:ring-accent">
                        <span class="text-sm text-ink-muted">Wajib terpenuhi</span>
                    </label>
                </div>

                <div class="flex items-center gap-4">
                    <x-primary-button wire:loading.attr="disabled" wire:target="addRule">
                        <span wire:loading.remove wire:target="addRule">Tambah rule</span>
                        <span wire:loading wire:target="addRule">Menambah</span>
                    </x-primary-button>
                    <x-action-message class="me-3" on="rule-added">Tersimpan.</x-action-message>
                </div>
            </form>
        </div>
    @endif

    {{-- Arsip --}}
    @if ($this->archivedSetups->isNotEmpty())
        {{-- Tingkat ketiga: tanpa permukaan. --}}
        <div class="border-t border-line px-4 pt-8 sm:px-8">
            <header>
                <h2 class="text-lg font-medium text-ink-muted">Arsip</h2>
                <p class="mt-1 text-sm text-ink-muted">
                    Setup yang diarsipkan tidak muncul saat mencatat trade, tapi trade lama yang memakainya tetap utuh.
                </p>
            </header>

            <ul class="mt-4 divide-y divide-line">
                @foreach ($this->archivedSetups as $setup)
                    <li class="py-3 flex items-center justify-between gap-4" wire:key="archived-{{ $setup->id }}">
                        <span class="text-ink-muted">{{ $setup->name }}</span>
                        <x-secondary-button wire:click="restoreSetup({{ $setup->id }})" class="gap-2">
                            <x-phosphor-arrow-u-up-left class="h-4 w-4" aria-hidden="true" />
                            Pulihkan
                        </x-secondary-button>
                    </li>
                @endforeach
            </ul>
        </div>
    @endif
</div>
