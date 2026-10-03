<?php

use App\Models\Instrument;
use App\Models\Trade;
use App\Support\TradeMath;
use Illuminate\Support\Facades\DB;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Computed;
use Livewire\Volt\Component;

new #[Layout('layouts.app')] class extends Component
{
    public Trade $trade;

    public ?int $instrumentId = null;

    public string $direction = Trade::DIRECTION_LONG;

    public string $lotSize = '';

    public string $entryPrice = '';

    public string $stopPrice = '';

    public string $exitPrice = '';

    public string $openedAt = '';

    public string $closedAt = '';

    public string $notes = '';

    public function mount(Trade $trade): void
    {
        // Route model binding sudah terscope global, jadi trade milik orang
        // lain berakhir 404 sebelum sampai sini. authorize() lapisan kedua.
        $this->authorize('update', $trade);

        $this->trade = $trade;
        $this->instrumentId = $trade->instrument_id;
        $this->direction = $trade->direction;
        $this->lotSize = (string) ($trade->lot_size ?? '');
        $this->entryPrice = (string) ($trade->entry_price ?? '');
        $this->stopPrice = (string) ($trade->stop_price ?? '');
        $this->exitPrice = (string) ($trade->exit_price ?? '');
        $this->openedAt = $trade->opened_at->format('Y-m-d\TH:i');
        $this->closedAt = $trade->closed_at?->format('Y-m-d\TH:i') ?? '';
        $this->notes = $trade->notes ?? '';
    }

    #[Computed]
    public function activeInstruments()
    {
        return Instrument::active()->orderBy('symbol')->get();
    }

    #[Computed]
    public function ruleChecks()
    {
        return $this->trade->ruleChecks()->orderBy('position')->orderBy('id')->get();
    }

    #[Computed]
    public function preview(): array
    {
        $ins = $this->instrumentId === null ? null : Instrument::active()->find($this->instrumentId);

        if ($ins === null || ! is_numeric($this->lotSize) || ! is_numeric($this->entryPrice)) {
            return ['pnl' => null, 'risk' => null, 'r' => null];
        }

        $ukuran = (float) $ins->contract_size;
        $lot = (float) $this->lotSize;
        $entry = (float) $this->entryPrice;

        $pnl = is_numeric($this->exitPrice)
            ? TradeMath::pnl($this->direction, $lot, $entry, (float) $this->exitPrice, $ukuran)
            : null;
        $risiko = is_numeric($this->stopPrice)
            ? TradeMath::risk($lot, $entry, (float) $this->stopPrice, $ukuran)
            : null;

        return [
            'pnl' => $pnl,
            'risk' => $risiko,
            'r' => ($pnl !== null && $risiko) ? round($pnl / $risiko, 2) : null,
        ];
    }

    /**
     * Dibandingkan sebagai angka, bukan string: aturan different melihat
     * '2000' dan '2000.00000000' sebagai nilai berbeda, padahal jaraknya nol.
     */
    protected function stopBerbedaDariEntry(): callable
    {
        return function (string $atribut, mixed $nilai, callable $gagal): void {
            if (is_numeric($nilai) && is_numeric($this->entryPrice)
                && (float) $nilai === (float) $this->entryPrice) {
                $gagal('Stop tidak boleh sama dengan harga entry: jaraknya nol, risikonya jadi tidak terdefinisi.');
            }
        };
    }

    public function save(): void
    {
        $this->authorize('update', $this->trade);

        $divalidasi = $this->validate([
            'instrumentId' => ['required', 'integer'],
            'direction' => ['required', 'in:'.implode(',', Trade::directions())],
            'lotSize' => ['required', 'numeric', 'min:0.0001', 'max:100000'],
            'entryPrice' => ['required', 'numeric', 'gt:0', 'max:9999999999'],
            'stopPrice' => ['required', 'numeric', 'gt:0', 'max:9999999999', $this->stopBerbedaDariEntry()],
            'exitPrice' => ['nullable', 'numeric', 'gt:0', 'max:9999999999', 'required_with:closedAt'],
            'openedAt' => ['required', 'date'],
            'closedAt' => ['nullable', 'date', 'after_or_equal:openedAt', 'required_with:exitPrice'],
            'notes' => ['nullable', 'string', 'max:2000'],
        ], [
            'instrumentId.required' => 'Pilih instrumen yang kamu trading.',
            'lotSize.required' => 'Isi ukuran lot-nya.',
            'entryPrice.required' => 'Isi harga entry.',
            'stopPrice.required' => 'Isi harga stop loss. Dari jarak inilah risiko dihitung.',
            'exitPrice.required_with' => 'Isi juga harga exit kalau trade sudah ditutup.',
            'closedAt.required_with' => 'Isi juga waktu tutupnya kalau trade sudah ada harga exit.',
            'closedAt.after_or_equal' => 'Waktu tutup tidak boleh mendahului waktu buka.',
        ]);

        DB::transaction(function () use ($divalidasi): void {
            $instrumen = Instrument::active()->lockForUpdate()->find($this->instrumentId);

            if ($instrumen === null) {
                throw new \RuntimeException('Instrumen sudah tidak aktif.');
            }

            $ukuran = (float) $instrumen->contract_size;
            $lot = (float) $divalidasi['lotSize'];
            $entry = (float) $divalidasi['entryPrice'];
            $exit = $divalidasi['exitPrice'] === '' || $divalidasi['exitPrice'] === null
                ? null
                : (float) $divalidasi['exitPrice'];

            $this->trade->fill([
                'direction' => $divalidasi['direction'],
                'lot_size' => $lot,
                'entry_price' => $entry,
                'stop_price' => $divalidasi['stopPrice'],
                'exit_price' => $exit,
                'opened_at' => $divalidasi['openedAt'],
                'closed_at' => $divalidasi['closedAt'] === '' ? null : $divalidasi['closedAt'],
                'notes' => $divalidasi['notes'] === '' ? null : $divalidasi['notes'],
            ]);

            $this->trade->symbol = $instrumen->symbol;
            $this->trade->instrument_id = $instrumen->id;
            $this->trade->contract_size = $ukuran;
            $this->trade->pnl_amount = TradeMath::pnl($divalidasi['direction'], $lot, $entry, $exit, $ukuran);
            $this->trade->risk_amount = TradeMath::risk($lot, $entry, (float) $divalidasi['stopPrice'], $ukuran);

            // compliance_score sengaja TIDAK disentuh. Checklist adalah
            // catatan apa yang terpenuhi saat entry; menghitung ulang di sini
            // berarti membiarkan skor diperbaiki setelah hasilnya kelihatan.
            $this->trade->save();
        });

        $this->dispatch('trade-updated');
    }
};
?>

@php
    $p = $this->preview;
    $nada = fn (?float $v) => $v === null ? 'text-ink-faint' : ($v > 0 ? 'text-viz-positive' : ($v < 0 ? 'text-viz-negative' : 'text-ink'));
    $uang = fn (?float $v) => $v === null ? null : ($v < 0 ? '-' : '').number_format(abs($v), 2);
@endphp

<div class="space-y-6">
    <div class="p-4 sm:p-8 bg-surface shadow sm:rounded-lg">
        <header>
            <h2 class="text-lg font-medium text-ink">Ubah trade</h2>
            <p class="mt-1 text-sm text-ink-muted">
                @if ($trade->isClosed())
                    Trade ini sudah ditutup. Mengubah harga akan menghitung ulang hasil dan R-nya.
                @else
                    Posisi masih terbuka. Isi harga exit dan waktu tutup untuk menutupnya.
                @endif
            </p>
        </header>

        <form wire:submit="save" class="mt-6 space-y-6 max-w-2xl">
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                <div>
                    <x-input-label for="instrumentId" value="Instrumen" />
                    <select wire:model.live="instrumentId" id="instrumentId"
                        class="mt-1 block w-full border-line-strong focus:border-accent focus:ring-accent rounded-md shadow-sm">
                        <option value="">Pilih instrumen</option>
                        @foreach ($this->activeInstruments as $ins)
                            <option value="{{ $ins->id }}">{{ $ins->symbol }}</option>
                        @endforeach
                    </select>
                    <x-input-error class="mt-2" :messages="$errors->get('instrumentId')" />
                </div>

                <div>
                    <x-input-label for="direction" value="Arah" />
                    <select wire:model.live="direction" id="direction"
                        class="mt-1 block w-full border-line-strong focus:border-accent focus:ring-accent rounded-md shadow-sm">
                        @foreach (\App\Models\Trade::directions() as $arah)
                            <option value="{{ $arah }}">{{ $arah }}</option>
                        @endforeach
                    </select>
                    <x-input-error class="mt-2" :messages="$errors->get('direction')" />
                </div>

                <div>
                    <x-input-label for="lotSize" value="Ukuran lot" />
                    <x-text-input wire:model.live.debounce.400ms="lotSize" id="lotSize" type="number" step="0.01" class="mt-1 block w-full tabular" />
                    <x-input-error class="mt-2" :messages="$errors->get('lotSize')" />
                </div>

                <div>
                    <x-input-label for="entryPrice" value="Harga entry" />
                    <x-text-input wire:model.live.debounce.400ms="entryPrice" id="entryPrice" type="number" step="any" class="mt-1 block w-full tabular" />
                    <x-input-error class="mt-2" :messages="$errors->get('entryPrice')" />
                </div>

                <div>
                    <x-input-label for="stopPrice" value="Harga stop loss" />
                    <x-text-input wire:model.live.debounce.400ms="stopPrice" id="stopPrice" type="number" step="any" class="mt-1 block w-full tabular" />
                    <x-input-error class="mt-2" :messages="$errors->get('stopPrice')" />
                </div>

                <div>
                    <x-input-label for="exitPrice" value="Harga exit" />
                    <x-text-input wire:model.live.debounce.400ms="exitPrice" id="exitPrice" type="number" step="any" class="mt-1 block w-full tabular" />
                    <x-input-error class="mt-2" :messages="$errors->get('exitPrice')" />
                </div>

                <div>
                    <x-input-label for="openedAt" value="Waktu buka" />
                    <x-text-input wire:model="openedAt" id="openedAt" type="datetime-local" class="mt-1 block w-full" />
                    <x-input-error class="mt-2" :messages="$errors->get('openedAt')" />
                </div>

                <div>
                    <x-input-label for="closedAt" value="Waktu tutup" />
                    <x-text-input wire:model="closedAt" id="closedAt" type="datetime-local" class="mt-1 block w-full" />
                    <x-input-error class="mt-2" :messages="$errors->get('closedAt')" />
                </div>
            </div>

            @if ($p['risk'] !== null || $p['pnl'] !== null)
                <div class="border border-line rounded-md p-4 grid grid-cols-3 gap-4 text-sm pop">
                    <div>
                        <div class="text-ink-faint text-xs">Risiko</div>
                        <div class="mt-1 tabular font-semibold text-ink">{{ $uang($p['risk']) ?? 'belum ada' }}</div>
                    </div>
                    <div>
                        <div class="text-ink-faint text-xs">Hasil</div>
                        <div class="mt-1 tabular font-semibold {{ $nada($p['pnl']) }}">{{ $uang($p['pnl']) ?? 'posisi terbuka' }}</div>
                    </div>
                    <div>
                        <div class="text-ink-faint text-xs">R-multiple</div>
                        <div class="mt-1 tabular font-semibold {{ $nada($p['r']) }}">
                            {{ $p['r'] === null ? 'belum ada' : number_format($p['r'], 2).'R' }}
                        </div>
                    </div>
                </div>
            @endif

            <div>
                <x-input-label for="notes" value="Catatan (opsional)" />
                <textarea wire:model="notes" id="notes" rows="3"
                    class="mt-1 block w-full border-line-strong focus:border-accent focus:ring-accent rounded-md shadow-sm"></textarea>
                <x-input-error class="mt-2" :messages="$errors->get('notes')" />
            </div>

            <div class="flex items-center gap-4">
                <x-primary-button wire:loading.attr="disabled" wire:target="save">
                    <span wire:loading.remove wire:target="save">Simpan perubahan</span>
                    <span wire:loading wire:target="save">Menyimpan</span>
                </x-primary-button>
                <a href="{{ route('trades') }}" class="text-sm text-ink-muted hover:text-ink underline" wire:navigate>Kembali</a>
                <x-action-message class="me-3" on="trade-updated">Tersimpan.</x-action-message>
            </div>
        </form>
    </div>

    {{-- Checklist ditampilkan, tidak bisa diubah. --}}
    <div class="p-4 sm:p-8 bg-surface shadow sm:rounded-lg">
        <header>
            <h2 class="text-lg font-medium text-ink">Checklist saat entry</h2>
            <p class="mt-1 text-sm text-ink-muted max-w-[65ch]">
                Terkunci dengan sengaja. Ini catatan kriteria apa yang terpenuhi saat kamu masuk posisi. Kalau bisa
                diubah setelah hasilnya kelihatan, skor kepatuhan berhenti berarti apa-apa.
            </p>
        </header>

        @if ($this->ruleChecks->isEmpty())
            <p class="mt-4 text-sm text-ink-faint">Setup ini belum punya rule saat trade dicatat.</p>
        @else
            <ul class="mt-4 divide-y divide-line">
                @foreach ($this->ruleChecks as $check)
                    <li class="py-3 flex items-start justify-between gap-4" wire:key="check-{{ $check->id }}">
                        <span class="text-sm text-ink">
                            {{ $check->rule_label }}
                            <span class="ms-1 text-xs text-ink-faint">bobot {{ $check->rule_weight }}</span>
                            @if ($check->rule_required)
                                <span class="ms-1 text-xs font-medium text-negative">wajib</span>
                            @endif
                        </span>
                        <span class="text-sm {{ $check->is_met ? 'text-viz-positive' : 'text-ink-faint' }}">
                            {{ $check->is_met ? 'terpenuhi' : 'tidak' }}
                        </span>
                    </li>
                @endforeach
            </ul>

            <p class="mt-4 text-sm text-ink-muted tabular">
                Skor kepatuhan: {{ $trade->isUnscored() ? 'belum dinilai' : $trade->compliance_score.'%' }}
            </p>
        @endif
    </div>
</div>
