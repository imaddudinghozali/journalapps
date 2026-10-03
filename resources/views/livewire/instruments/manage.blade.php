<?php

use App\Models\Instrument;
use Illuminate\Database\UniqueConstraintViolationException;
use Livewire\Attributes\Computed;
use Livewire\Volt\Component;

new class extends Component
{
    public string $symbol = '';

    public string $contractSize = '';

    #[Computed]
    public function instruments()
    {
        return Instrument::active()->withCount('trades')->orderBy('symbol')->get();
    }

    #[Computed]
    public function archived()
    {
        return Instrument::whereNotNull('archived_at')->orderBy('symbol')->get();
    }

    public function updatedSymbol(): void
    {
        // Pengali lazim diisikan sebagai saran, bukan dipaksakan. Pengguna
        // tetap bisa menimpanya kalau brokernya memakai angka berbeda.
        $kunci = strtoupper(trim($this->symbol));

        if ($this->contractSize === '' && isset(Instrument::SARAN[$kunci])) {
            $this->contractSize = (string) Instrument::SARAN[$kunci];
        }
    }

    public function create(): void
    {
        $this->symbol = strtoupper(trim($this->symbol));

        $divalidasi = $this->validate([
            'symbol' => ['required', 'string', 'max:20', $this->uniqueSymbolRule()],
            'contractSize' => ['required', 'numeric', 'gt:0', 'max:100000000'],
        ], [
            'symbol.required' => 'Isi simbol instrumennya.',
            'contractSize.required' => 'Isi ukuran kontraknya.',
            'contractSize.gt' => 'Ukuran kontrak harus lebih besar dari nol.',
        ]);

        try {
            Instrument::create([
                'symbol' => $divalidasi['symbol'],
                'contract_size' => $divalidasi['contractSize'],
            ]);
        } catch (UniqueConstraintViolationException) {
            $this->addError('symbol', 'Kamu sudah punya instrumen dengan simbol itu.');

            return;
        }

        $this->reset('symbol', 'contractSize');
        unset($this->instruments);
        $this->dispatch('instrument-created');
    }

    public function archive(int $id): void
    {
        $i = Instrument::findOrFail($id);
        $this->authorize('update', $i);

        if (! $i->isArchived()) {
            $i->archived_at = now();
            $i->save();
        }

        unset($this->instruments, $this->archived);
    }

    public function restore(int $id): void
    {
        $i = Instrument::findOrFail($id);
        $this->authorize('update', $i);

        $i->archived_at = null;
        $i->save();

        unset($this->instruments, $this->archived);
    }

    /** Keunikan dicek lewat model supaya global scope ikut berlaku. */
    protected function uniqueSymbolRule(): callable
    {
        return function (string $atribut, mixed $nilai, callable $gagal): void {
            if (Instrument::where('symbol', $nilai)->exists()) {
                $gagal('Kamu sudah punya instrumen dengan simbol itu.');
            }
        };
    }
};
?>

@php
    $unit = fn ($n) => rtrim(rtrim(number_format((float) $n, 4, '.', ','), '0'), '.');
@endphp

<div class="space-y-6">
    <div class="p-4 sm:p-8 bg-surface shadow-soft sm:rounded-xl spotlight transition-shadow hover:shadow-lift">
        <div class="max-w-xl">
            <header>
                <h2 class="text-lg font-medium text-ink">Instrumen baru</h2>
                <p class="mt-1 text-sm text-ink-muted">
                    Ukuran kontrak adalah pengali yang mengubah pergerakan harga menjadi uang. XAUUSD 100 oz per lot,
                    EURUSD 100.000 unit, BTCUSD 1. Salah di sini membuat seluruh P&amp;L instrumen itu salah.
                </p>
            </header>

            <form wire:submit="create" class="mt-6 space-y-6">
                <div>
                    <x-input-label for="symbol" value="Simbol" />
                    <x-text-input wire:model.live.debounce.500ms="symbol" id="symbol" type="text"
                                  class="mt-1 block w-full" placeholder="XAUUSD" />
                    <x-input-error class="mt-2" :messages="$errors->get('symbol')" />
                </div>

                <div>
                    <x-input-label for="contractSize" value="Ukuran kontrak (unit per lot)" />
                    <x-text-input wire:model="contractSize" id="contractSize" type="number" step="any"
                                  class="mt-1 block w-full tabular" placeholder="100" />
                    <x-input-error class="mt-2" :messages="$errors->get('contractSize')" />
                    <p class="mt-1 text-xs text-ink-faint">
                        Terisi sendiri untuk simbol yang umum, dan tetap bisa kamu ubah.
                    </p>
                </div>

                <div class="flex items-center gap-4">
                    <x-primary-button wire:loading.attr="disabled" wire:target="create">
                        <span wire:loading.remove wire:target="create">Simpan instrumen</span>
                        <span wire:loading wire:target="create">Menyimpan</span>
                    </x-primary-button>
                    <x-action-message class="me-3" on="instrument-created">Tersimpan.</x-action-message>
                </div>
            </form>
        </div>
    </div>

    <div class="p-4 sm:p-8 bg-surface shadow-soft sm:rounded-xl spotlight transition-shadow hover:shadow-lift">
        <header>
            <h2 class="text-lg font-medium text-ink">Instrumen aktif</h2>
        </header>

        @if ($this->instruments->isEmpty())
            <p class="mt-4 text-sm text-ink-faint">Belum ada instrumen. Tambahkan satu di atas.</p>
        @else
            <ul class="mt-4 divide-y divide-line">
                @foreach ($this->instruments as $ins)
                    <li class="py-3 flex items-center justify-between gap-4" wire:key="ins-{{ $ins->id }}">
                        <div>
                            <span class="font-medium text-ink">{{ $ins->symbol }}</span>
                            <span class="block text-sm text-ink-faint tabular">
                                1 lot = {{ $unit($ins->contract_size) }} unit &middot; {{ $ins->trades_count }} trade
                            </span>
                        </div>
                        <x-secondary-button wire:click="archive({{ $ins->id }})">Arsipkan</x-secondary-button>
                    </li>
                @endforeach
            </ul>
        @endif
    </div>

    @if ($this->archived->isNotEmpty())
        <div class="p-4 sm:p-8 bg-surface shadow-soft sm:rounded-xl spotlight transition-shadow hover:shadow-lift">
            <header>
                <h2 class="text-lg font-medium text-ink">Arsip</h2>
                <p class="mt-1 text-sm text-ink-muted">
                    Tidak muncul saat mencatat trade, tapi trade lama yang memakainya tetap utuh.
                </p>
            </header>

            <ul class="mt-4 divide-y divide-line">
                @foreach ($this->archived as $ins)
                    <li class="py-3 flex items-center justify-between gap-4" wire:key="arsip-{{ $ins->id }}">
                        <span class="text-ink-muted">{{ $ins->symbol }}</span>
                        <x-secondary-button wire:click="restore({{ $ins->id }})">Pulihkan</x-secondary-button>
                    </li>
                @endforeach
            </ul>
        </div>
    @endif
</div>
