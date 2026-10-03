<?php

use App\Models\Instrument;
use App\Models\SetupRule;
use App\Models\Trade;
use App\Models\TradeRuleCheck;
use App\Models\TradingSetup;
use App\Support\ComplianceScore;
use App\Support\TradeMath;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Livewire\Attributes\Computed;
use Livewire\Volt\Component;

new class extends Component
{
    public ?int $tradingSetupId = null;

    public ?int $instrumentId = null;

    public string $direction = Trade::DIRECTION_LONG;

    public string $lotSize = '';

    public string $entryPrice = '';

    public string $stopPrice = '';

    public string $exitPrice = '';

    public string $openedAt = '';

    public string $closedAt = '';

    public string $notes = '';

    /** @var array<int, bool> setup_rule_id => terpenuhi */
    public array $checks = [];

    public function mount(): void
    {
        $this->openedAt = now()->format('Y-m-d\TH:i');
    }

    #[Computed]
    public function activeInstruments()
    {
        return Instrument::active()->orderBy('symbol')->get();
    }

    #[Computed]
    public function selectedInstrument(): ?Instrument
    {
        return $this->instrumentId === null
            ? null
            : Instrument::active()->find($this->instrumentId);
    }

    /**
     * Pratinjau P&L dan risiko sambil mengetik. Angka yang BENAR-BENAR
     * disimpan dihitung ulang di dalam transaksi dari instrumen terkunci,
     * bukan dari pratinjau ini.
     */
    #[Computed]
    public function preview(): array
    {
        $ins = $this->selectedInstrument();

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

    #[Computed]
    public function activeSetups()
    {
        return TradingSetup::active()->orderBy('name')->get();
    }

    #[Computed]
    public function selectedSetup(): ?TradingSetup
    {
        if ($this->tradingSetupId === null) {
            return null;
        }

        return TradingSetup::active()->find($this->tradingSetupId);
    }

    #[Computed]
    public function rules()
    {
        if ($this->selectedSetup() === null) {
            return collect();
        }

        return SetupRule::active()
            ->where('trading_setup_id', $this->tradingSetupId)
            ->orderBy('position')
            ->orderBy('id')
            ->get();
    }

    #[Computed]
    public function score(): ComplianceScore
    {
        return ComplianceScore::from(
            $this->rules()->map(fn (SetupRule $rule) => [
                'weight' => $rule->weight,
                'met' => (bool) ($this->checks[$rule->id] ?? false),
                'required' => $rule->is_required,
            ])->all()
        );
    }

    public function threshold(): int
    {
        return (int) Auth::user()->compliance_threshold;
    }

    public function updatedTradingSetupId(): void
    {
        // Checklist selalu mulai dari keadaan belum tercentang. Mewarisi
        // centangan dari setup sebelumnya akan menghasilkan jawaban palsu.
        $this->checks = [];
        unset($this->selectedSetup, $this->rules, $this->score);
        $this->resetValidation();
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
        $setup = $this->selectedSetup();

        $validated = $this->validate([
            'tradingSetupId' => ['required', 'integer'],
            'instrumentId' => ['required', 'integer'],
            'direction' => ['required', 'in:'.implode(',', Trade::directions())],
            'lotSize' => ['required', 'numeric', 'min:0.0001', 'max:100000'],
            'entryPrice' => ['required', 'numeric', 'gt:0', 'max:9999999999'],
            'stopPrice' => ['required', 'numeric', 'gt:0', 'max:9999999999', $this->stopBerbedaDariEntry()],
            'exitPrice' => ['nullable', 'numeric', 'gt:0', 'max:9999999999', 'required_with:closedAt'],
            'openedAt' => ['required', 'date'],
            // Trade tertutup berarti waktu tutup DAN hasil sama-sama ada.
            // Mengisi salah satu saja menghasilkan trade setengah jadi yang
            // membuat laporan salah hitung.
            'closedAt' => ['nullable', 'date', 'after_or_equal:openedAt', 'required_with:exitPrice'],
            'notes' => ['nullable', 'string', 'max:2000'],
            'checks' => ['array', 'max:'.TradingSetup::MAX_ACTIVE_RULES],
            'checks.*' => ['boolean'],
        ], [
            'tradingSetupId.required' => 'Pilih setup yang kamu pakai.',
            'instrumentId.required' => 'Pilih instrumen yang kamu trading.',
            'direction.in' => 'Arah trade harus long atau short.',
            'lotSize.required' => 'Isi ukuran lot-nya.',
            'lotSize.min' => 'Lot minimal 0,0001.',
            'entryPrice.required' => 'Isi harga entry.',
            'stopPrice.required' => 'Isi harga stop loss. Dari jarak inilah risiko dihitung.',
            'closedAt.after_or_equal' => 'Waktu tutup tidak boleh mendahului waktu buka.',
            'closedAt.required_with' => 'Isi juga waktu tutupnya kalau trade sudah ada harga exit.',
            'exitPrice.required_with' => 'Isi juga harga exit kalau trade sudah ditutup.',
            'notes.max' => 'Catatan maksimal 2000 karakter.',
        ]);

        // Dicek setelah validate() supaya pesan "pilih setup" muncul lebih dulu
        // ketika memang belum dipilih.
        if ($setup === null) {
            $this->addError('tradingSetupId', 'Setup itu tidak tersedia.');

            return;
        }

        if ($this->selectedInstrument() === null) {
            $this->addError('instrumentId', 'Instrumen itu tidak tersedia.');

            return;
        }

        DB::transaction(function () use ($setup, $validated): void {
            // Rules dibaca SATU KALI di dalam transaksi, lalu skor dan baris
            // checklist dibangun dari daftar yang sama persis.
            //
            // Sebelumnya skor dan snapshot berasal dari dua query terpisah di
            // luar transaksi: kalau rule berubah di antaranya, skor tersimpan
            // menyimpang dari isi checklist tanpa ada yang tahu. Itu justru
            // invarian yang dijanjikan docblock Trade.
            // Setup dibaca ulang terkunci di dalam transaksi: yang diarsipkan
            // di sela antara render dan simpan tidak boleh lagi menerima trade.
            $terkunci = TradingSetup::active()->lockForUpdate()->find($setup->id);

            if ($terkunci === null) {
                throw new RuntimeException('Setup sudah tidak aktif.');
            }

            $rules = SetupRule::active()
                ->where('trading_setup_id', $terkunci->id)
                ->orderBy('position')
                ->orderBy('id')
                ->lockForUpdate()
                ->get();

            $snapshot = $rules->map(fn (SetupRule $rule) => [
                'setup_rule_id' => $rule->id,
                'is_met' => (bool) ($this->checks[$rule->id] ?? false),
                'rule_label' => $rule->label,
                'rule_weight' => $rule->weight,
                'rule_required' => $rule->is_required,
                'position' => $rule->position,
            ])->all();

            $score = ComplianceScore::from(array_map(fn (array $baris) => [
                'weight' => $baris['rule_weight'],
                'met' => $baris['is_met'],
                'required' => $baris['rule_required'],
            ], $snapshot));

            // Instrumen dibaca ulang terkunci di dalam transaksi: pengali
            // yang dipakai menghitung uang tidak boleh berubah di sela antara
            // pratinjau dan penyimpanan.
            $instrumen = Instrument::active()->lockForUpdate()->find($this->instrumentId);

            if ($instrumen === null) {
                throw new RuntimeException('Instrumen sudah tidak aktif.');
            }

            $ukuran = (float) $instrumen->contract_size;
            $lot = (float) $validated['lotSize'];
            $entry = (float) $validated['entryPrice'];
            $exit = $validated['exitPrice'] === '' || $validated['exitPrice'] === null
                ? null
                : (float) $validated['exitPrice'];

            $trade = new Trade([
                // Simbol disalin sebagai snapshot: instrumennya boleh diganti
                // nama, catatan trade lama tidak boleh ikut berubah.
                'symbol' => $instrumen->symbol,
                'direction' => $validated['direction'],
                'lot_size' => $lot,
                'entry_price' => $entry,
                'stop_price' => $validated['stopPrice'],
                'exit_price' => $exit,
                'opened_at' => $validated['openedAt'],
                'closed_at' => $validated['closedAt'] === '' ? null : $validated['closedAt'],
                'notes' => $validated['notes'] === '' ? null : $validated['notes'],
            ]);
            $trade->trading_setup_id = $terkunci->id;
            $trade->instrument_id = $instrumen->id;
            $trade->contract_size = $ukuran;

            // Dihitung di sini, bukan diterima dari klien. Keduanya tidak
            // fillable justru supaya jalur ini satu-satunya.
            $trade->pnl_amount = TradeMath::pnl($validated['direction'], $lot, $entry, $exit, $ukuran);
            $trade->risk_amount = TradeMath::risk($lot, $entry, (float) $validated['stopPrice'], $ukuran);
            $trade->compliance_score = $score->value;
            $trade->save();

            // Snapshot: label, bobot, dan status wajib disalin APA ADANYA saat
            // ini. Mengedit rule besok tidak boleh mengubah trade ini.
            foreach ($snapshot as $baris) {
                $check = new TradeRuleCheck([
                    'is_met' => $baris['is_met'],
                    'rule_label' => $baris['rule_label'],
                    'rule_weight' => $baris['rule_weight'],
                    'rule_required' => $baris['rule_required'],
                    'position' => $baris['position'],
                ]);
                $check->trade_id = $trade->id;
                $check->setup_rule_id = $baris['setup_rule_id'];
                $check->save();
            }
        });

        $this->reset('lotSize', 'entryPrice', 'stopPrice', 'exitPrice', 'closedAt', 'notes', 'checks');
        $this->openedAt = now()->format('Y-m-d\TH:i');
        unset($this->score);

        $this->dispatch('trade-recorded');
    }
};
?>

<div class="p-4 sm:p-8 bg-surface shadow sm:rounded-lg">
    <header>
        <h2 class="text-lg font-medium text-ink">Catat trade</h2>
        <p class="mt-1 text-sm text-ink-muted">
            Centang hanya kriteria yang benar-benar terpenuhi saat kamu entry. Jawaban jujur di sini yang membuat
            laporannya ada gunanya.
        </p>
    </header>

    <form wire:submit="save" class="mt-6 space-y-6 max-w-2xl">
        <div>
            <x-input-label for="tradingSetupId" value="Setup yang dipakai" />
            <select wire:model.live="tradingSetupId" id="tradingSetupId"
                class="mt-1 block w-full border-line-strong focus:border-accent focus:ring-accent rounded-md shadow-sm">
                <option value="">Pilih setup</option>
                @foreach ($this->activeSetups as $setup)
                    <option value="{{ $setup->id }}">{{ $setup->name }}</option>
                @endforeach
            </select>
            <x-input-error class="mt-2" :messages="$errors->get('tradingSetupId')" />

            @if ($this->activeSetups->isEmpty())
                <p class="mt-2 text-sm text-ink-faint">
                    Belum ada setup aktif. Buat dulu di halaman <a href="{{ route('setups') }}" class="underline" wire:navigate>Setup &amp; Rules</a>.
                </p>
            @endif
        </div>

        @if ($this->selectedSetup)
            <div class="border border-line rounded-md p-4 pop" wire:key="checklist-{{ $tradingSetupId }}">
                <h3 class="font-medium text-ink">Checklist</h3>

                @if ($this->rules->isEmpty())
                    <p class="mt-2 text-sm text-ink-faint">
                        Setup ini belum punya rule, jadi trade-nya tercatat tanpa skor kepatuhan.
                    </p>
                @else
                    <ul class="mt-3 space-y-2">
                        @foreach ($this->rules as $rule)
                            <li wire:key="rule-{{ $rule->id }}">
                                <label class="flex items-start gap-3">
                                    <input type="checkbox" wire:model.live="checks.{{ $rule->id }}"
                                        class="mt-1 rounded border-line-strong text-accent shadow-sm focus:ring-accent">
                                    <span class="text-sm text-ink">
                                        {{ $rule->label }}
                                        <span class="ms-1 text-xs text-ink-faint">bobot {{ $rule->weight }}</span>
                                        @if ($rule->is_required)
                                            <span class="ms-1 text-xs font-medium text-negative">wajib</span>
                                        @endif
                                    </span>
                                </label>
                            </li>
                        @endforeach
                    </ul>

                    <div class="mt-4 text-sm text-ink-muted">
                        Skor kepatuhan:
                        <span class="tabular font-semibold {{ $this->score->needsWarning($this->threshold()) ? 'text-warn' : 'text-ink' }}"
                              wire:key="skor-{{ $this->score->value }}">
                            {{ $this->score->isUnscored() ? 'belum dinilai' : $this->score->value.'%' }}
                        </span>
                    </div>

                    @if ($this->score->needsWarning($this->threshold()))
                        <div class="mt-3 border border-warn-line bg-warn-soft rounded-md p-3 text-sm text-warn pop">
                            @if ($this->score->hasUnmetRequired())
                                <p>{{ $this->score->unmetRequiredCount }} rule wajib tidak terpenuhi.</p>
                            @endif
                            @if ($this->score->isBelow($this->threshold()))
                                <p>Skor di bawah ambangmu ({{ $this->threshold() }}%).</p>
                            @endif
                            <p class="mt-1">
                                Trade ini tetap bisa disimpan. Justru trade seperti inilah yang paling perlu dicatat:
                                tanpa datanya, tidak ada yang bisa dipelajari.
                            </p>
                        </div>
                    @endif
                @endif
            </div>
        @endif

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

                @if ($this->activeInstruments->isEmpty())
                    <p class="mt-2 text-sm text-ink-faint">
                        Belum ada instrumen. Tambahkan dulu di
                        <a href="{{ route('instruments') }}" class="underline" wire:navigate>Instrumen</a>,
                        lengkap dengan ukuran kontraknya.
                    </p>
                @elseif ($this->selectedInstrument)
                    <p class="mt-2 text-xs text-ink-faint tabular">
                        1 lot = {{ rtrim(rtrim(number_format((float) $this->selectedInstrument->contract_size, 4, '.', ','), '0'), '.') }} unit
                    </p>
                @endif
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
                <x-text-input wire:model.live.debounce.400ms="lotSize" id="lotSize" type="number" step="0.01"
                              class="mt-1 block w-full tabular" placeholder="0.50" />
                <x-input-error class="mt-2" :messages="$errors->get('lotSize')" />
            </div>

            <div>
                <x-input-label for="entryPrice" value="Harga entry" />
                <x-text-input wire:model.live.debounce.400ms="entryPrice" id="entryPrice" type="number" step="any"
                              class="mt-1 block w-full tabular" placeholder="2000.00" />
                <x-input-error class="mt-2" :messages="$errors->get('entryPrice')" />
            </div>

            <div>
                <x-input-label for="stopPrice" value="Harga stop loss" />
                <x-text-input wire:model.live.debounce.400ms="stopPrice" id="stopPrice" type="number" step="any"
                              class="mt-1 block w-full tabular" placeholder="1990.00" />
                <x-input-error class="mt-2" :messages="$errors->get('stopPrice')" />
                <p class="mt-1 text-xs text-ink-faint">Risiko dihitung dari jarak entry ke stop.</p>
            </div>

            <div>
                <x-input-label for="exitPrice" value="Harga exit (kosongkan bila belum ditutup)" />
                <x-text-input wire:model.live.debounce.400ms="exitPrice" id="exitPrice" type="number" step="any"
                              class="mt-1 block w-full tabular" placeholder="2015.00" />
                <x-input-error class="mt-2" :messages="$errors->get('exitPrice')" />
            </div>

            <div>
                <x-input-label for="openedAt" value="Waktu buka" />
                <x-text-input wire:model="openedAt" id="openedAt" type="datetime-local" class="mt-1 block w-full" />
                <x-input-error class="mt-2" :messages="$errors->get('openedAt')" />
            </div>

            <div>
                <x-input-label for="closedAt" value="Waktu tutup (opsional)" />
                <x-text-input wire:model="closedAt" id="closedAt" type="datetime-local" class="mt-1 block w-full" />
                <x-input-error class="mt-2" :messages="$errors->get('closedAt')" />
            </div>
        </div>

        @php($pratinjau = $this->preview)
        @if ($pratinjau['risk'] !== null || $pratinjau['pnl'] !== null)
            <div class="border border-line rounded-md p-4 grid grid-cols-2 sm:grid-cols-3 gap-4 text-sm pop">
                <div>
                    <div class="text-ink-faint text-xs">Risiko</div>
                    <div class="mt-1 tabular font-semibold text-ink">
                        {{ $pratinjau['risk'] === null ? 'belum ada' : number_format($pratinjau['risk'], 2) }}
                    </div>
                </div>
                <div>
                    <div class="text-ink-faint text-xs">Hasil</div>
                    <div class="mt-1 tabular font-semibold {{ $pratinjau['pnl'] === null ? 'text-ink-faint' : ($pratinjau['pnl'] > 0 ? 'text-viz-positive' : ($pratinjau['pnl'] < 0 ? 'text-viz-negative' : 'text-ink')) }}">
                        {{ $pratinjau['pnl'] === null ? 'posisi terbuka' : ($pratinjau['pnl'] < 0 ? '-' : '').number_format(abs($pratinjau['pnl']), 2) }}
                    </div>
                </div>
                <div>
                    <div class="text-ink-faint text-xs">R-multiple</div>
                    <div class="mt-1 tabular font-semibold {{ $pratinjau['r'] === null ? 'text-ink-faint' : ($pratinjau['r'] > 0 ? 'text-viz-positive' : ($pratinjau['r'] < 0 ? 'text-viz-negative' : 'text-ink')) }}">
                        {{ $pratinjau['r'] === null ? 'belum ada' : number_format($pratinjau['r'], 2).'R' }}
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
                <span wire:loading.remove wire:target="save">Simpan trade</span>
                <span wire:loading wire:target="save">Menyimpan</span>
            </x-primary-button>
            <x-action-message class="me-3" on="trade-recorded">Tersimpan.</x-action-message>
        </div>
    </form>
</div>
