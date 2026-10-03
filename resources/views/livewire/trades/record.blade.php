<?php

use App\Models\SetupRule;
use App\Models\Trade;
use App\Models\TradeRuleCheck;
use App\Models\TradingSetup;
use App\Support\ComplianceScore;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Livewire\Attributes\Computed;
use Livewire\Volt\Component;

new class extends Component
{
    public ?int $tradingSetupId = null;

    public string $symbol = '';

    public string $direction = Trade::DIRECTION_LONG;

    public string $riskAmount = '';

    public string $pnlAmount = '';

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

    public function save(): void
    {
        $setup = $this->selectedSetup();

        $validated = $this->validate([
            'tradingSetupId' => ['required', 'integer'],
            'symbol' => ['required', 'string', 'max:20'],
            'direction' => ['required', 'in:'.implode(',', Trade::directions())],
            // min dan max menjaga kolom decimal(18,2): tanpa itu 1e30 lolos
            // validasi lalu gagal di database, dan 0.001 tersimpan jadi 0.00.
            'riskAmount' => ['required', 'numeric', 'min:0.01', 'max:9999999999999999'],
            'pnlAmount' => ['nullable', 'numeric', 'min:-9999999999999999', 'max:9999999999999999', 'required_with:closedAt'],
            'openedAt' => ['required', 'date'],
            // Trade tertutup berarti waktu tutup DAN hasil sama-sama ada.
            // Mengisi salah satu saja menghasilkan trade setengah jadi yang
            // membuat laporan salah hitung.
            'closedAt' => ['nullable', 'date', 'after_or_equal:openedAt', 'required_with:pnlAmount'],
            'notes' => ['nullable', 'string', 'max:2000'],
            'checks' => ['array', 'max:'.TradingSetup::MAX_ACTIVE_RULES],
            'checks.*' => ['boolean'],
        ], [
            'tradingSetupId.required' => 'Pilih setup yang kamu pakai.',
            'symbol.required' => 'Isi instrumen yang kamu trading.',
            'direction.in' => 'Arah trade harus long atau short.',
            'riskAmount.required' => 'Isi berapa yang kamu risikokan.',
            'riskAmount.min' => 'Risiko minimal 0,01.',
            'closedAt.after_or_equal' => 'Waktu tutup tidak boleh mendahului waktu buka.',
            'closedAt.required_with' => 'Isi juga waktu tutupnya kalau trade sudah ada hasilnya.',
            'pnlAmount.required_with' => 'Isi juga hasilnya kalau trade sudah ditutup.',
            'notes.max' => 'Catatan maksimal 2000 karakter.',
        ]);

        // Dicek setelah validate() supaya pesan "pilih setup" muncul lebih dulu
        // ketika memang belum dipilih.
        if ($setup === null) {
            $this->addError('tradingSetupId', 'Setup itu tidak tersedia.');

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

            $trade = new Trade([
                'symbol' => strtoupper(trim($validated['symbol'])),
                'direction' => $validated['direction'],
                'risk_amount' => $validated['riskAmount'],
                'pnl_amount' => $validated['pnlAmount'] === '' ? null : $validated['pnlAmount'],
                'opened_at' => $validated['openedAt'],
                'closed_at' => $validated['closedAt'] === '' ? null : $validated['closedAt'],
                'notes' => $validated['notes'] === '' ? null : $validated['notes'],
            ]);
            $trade->trading_setup_id = $terkunci->id;
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

        $this->reset('symbol', 'riskAmount', 'pnlAmount', 'closedAt', 'notes', 'checks');
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
            <div class="border border-line rounded-md p-4">
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
                        <span class="font-semibold">
                            {{ $this->score->isUnscored() ? 'belum dinilai' : $this->score->value.'%' }}
                        </span>
                    </div>

                    @if ($this->score->needsWarning($this->threshold()))
                        <div class="mt-3 border border-warn-line bg-warn-soft rounded-md p-3 text-sm text-warn">
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
                <x-input-label for="symbol" value="Instrumen" />
                <x-text-input wire:model="symbol" id="symbol" type="text" class="mt-1 block w-full" placeholder="XAUUSD" />
                <x-input-error class="mt-2" :messages="$errors->get('symbol')" />
            </div>

            <div>
                <x-input-label for="direction" value="Arah" />
                <select wire:model="direction" id="direction"
                    class="mt-1 block w-full border-line-strong focus:border-accent focus:ring-accent rounded-md shadow-sm">
                    @foreach (\App\Models\Trade::directions() as $arah)
                        <option value="{{ $arah }}">{{ $arah }}</option>
                    @endforeach
                </select>
                <x-input-error class="mt-2" :messages="$errors->get('direction')" />
            </div>

            <div>
                <x-input-label for="riskAmount" value="Risiko" />
                <x-text-input wire:model="riskAmount" id="riskAmount" type="number" step="0.01" class="mt-1 block w-full" />
                <x-input-error class="mt-2" :messages="$errors->get('riskAmount')" />
            </div>

            <div>
                <x-input-label for="pnlAmount" value="Hasil (kosongkan bila belum ditutup)" />
                <x-text-input wire:model="pnlAmount" id="pnlAmount" type="number" step="0.01" class="mt-1 block w-full" />
                <x-input-error class="mt-2" :messages="$errors->get('pnlAmount')" />
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

        <div>
            <x-input-label for="notes" value="Catatan (opsional)" />
            <textarea wire:model="notes" id="notes" rows="3"
                class="mt-1 block w-full border-line-strong focus:border-accent focus:ring-accent rounded-md shadow-sm"></textarea>
            <x-input-error class="mt-2" :messages="$errors->get('notes')" />
        </div>

        <div class="flex items-center gap-4">
            <x-primary-button>Simpan trade</x-primary-button>
            <x-action-message class="me-3" on="trade-recorded">Tersimpan.</x-action-message>
        </div>
    </form>
</div>
