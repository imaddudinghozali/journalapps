<?php

use App\Models\Trade;
use App\Support\DashboardSummary;
use Illuminate\Support\Carbon;
use Livewire\Attributes\Computed;
use Livewire\Volt\Component;

new class extends Component
{
    public int $year;

    public int $month;

    public function mount(): void
    {
        $this->year = (int) now()->year;
        $this->month = (int) now()->month;
    }

    #[Computed]
    public function summary(): DashboardSummary
    {
        // Terscope global; relasi dimuat karena strict mode melarang lazy load.
        return DashboardSummary::from(Trade::with('setup')->get());
    }

    public function previousMonth(): void
    {
        $c = Carbon::create($this->year, $this->month, 1)->subMonth();
        $this->year = $c->year;
        $this->month = $c->month;
    }

    public function nextMonth(): void
    {
        $c = Carbon::create($this->year, $this->month, 1)->addMonth();
        $this->year = $c->year;
        $this->month = $c->month;
    }

    /** Petak kalender 6 baris x 7 kolom supaya tingginya tidak melompat antar bulan. */
    public function calendar(): array
    {
        $awal = Carbon::create($this->year, $this->month, 1)->startOfWeek(Carbon::SUNDAY);
        $harian = $this->summary()->monthlyPnl($this->year, $this->month);

        $sel = [];
        for ($i = 0; $i < 42; $i++) {
            $t = $awal->copy()->addDays($i);
            $kunci = $t->toDateString();
            $sel[] = [
                'date' => $t,
                'inMonth' => $t->month === $this->month,
                'pnl' => $harian[$kunci]['pnl'] ?? null,
                'count' => $harian[$kunci]['count'] ?? 0,
            ];
        }

        return $sel;
    }

    public function monthLabel(): string
    {
        $nama = ['', 'Januari', 'Februari', 'Maret', 'April', 'Mei', 'Juni',
            'Juli', 'Agustus', 'September', 'Oktober', 'November', 'Desember'];

        return $nama[$this->month].' '.$this->year;
    }
};
?>

@php
    $s = $this->summary;

    $uang = fn (?float $v) => $v === null ? null : ($v < 0 ? '-' : '').number_format(abs($v), 2);
    $nadaAngka = fn (?float $v) => $v === null ? 'text-ink' : ($v > 0 ? 'text-viz-positive' : ($v < 0 ? 'text-viz-negative' : 'text-ink'));

    // Garis kumulatif disiapkan di PHP: satu seri, satu sumbu, tanpa pustaka chart.
    $titik = $s->cumulative;
    $adaChart = count($titik) >= 2;
    if ($adaChart) {
        $nilai = array_column($titik, 'value');
        $min = min(min($nilai), 0.0);
        $max = max(max($nilai), 0.0);
        $rentang = ($max - $min) ?: 1.0;
        $W = 720; $H = 150; $pad = 8;
        $koordinat = [];
        foreach ($nilai as $i => $v) {
            $x = $pad + ($i / max(count($nilai) - 1, 1)) * ($W - 2 * $pad);
            $y = $pad + (1 - (($v - $min) / $rentang)) * ($H - 2 * $pad);
            $koordinat[] = [round($x, 1), round($y, 1)];
        }
        $garis = implode(' ', array_map(fn ($p) => $p[0].','.$p[1], $koordinat));
        $yNol = round($pad + (1 - ((0 - $min) / $rentang)) * ($H - 2 * $pad), 1);
        $akhir = end($nilai);
        $akhirKoordinat = end($koordinat);
        $warnaGaris = $akhir >= 0 ? 'rgb(var(--viz-positive))' : 'rgb(var(--viz-negative))';
    }
@endphp

<div class="space-y-6">
    @if ($s->isEmpty())
        <div class="p-8 bg-surface shadow-soft sm:rounded-xl spotlight transition-shadow hover:shadow-lift">
            <h2 class="text-lg font-medium text-ink">Belum ada yang bisa diringkas</h2>
            <p class="mt-2 text-sm text-ink-muted max-w-[60ch]">
                Angka di halaman ini datang dari trade yang sudah kamu tutup. Buat setup di
                <a href="{{ route('setups') }}" class="underline" wire:navigate>Setup &amp; Rules</a>, lalu catat
                trade pertamamu di <a href="{{ route('trades') }}" class="underline" wire:navigate>Jurnal Trade</a>.
            </p>
        </div>
    @else
        {{-- Angka pokok. Tanpa chart: satu besaran tidak butuh plot. --}}
        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
            <div class="p-5 bg-surface shadow-soft sm:rounded-xl spotlight transition-shadow hover:shadow-lift">
                <div class="text-sm text-ink-muted">P&amp;L bersih</div>
                <div class="mt-2 text-3xl font-semibold tabular {{ $nadaAngka($s->netPnl) }}">
                    {{ $uang($s->netPnl) }}
                </div>
                <div class="mt-1 text-xs text-ink-faint">dari {{ $s->closedCount }} trade tertutup</div>
            </div>

            <div class="p-5 bg-surface shadow-soft sm:rounded-xl spotlight transition-shadow hover:shadow-lift">
                <div class="text-sm text-ink-muted">Rata-rata per trade</div>
                <div class="mt-2 text-3xl font-semibold tabular {{ $nadaAngka($s->avgTrade) }}">
                    {{ $uang($s->avgTrade) ?? 'belum ada' }}
                </div>
                <div class="mt-1 text-xs text-ink-faint">{{ $s->openCount }} masih terbuka</div>
            </div>

            <div class="p-5 bg-surface shadow-soft sm:rounded-xl spotlight transition-shadow hover:shadow-lift">
                <div class="text-sm text-ink-muted">Rata-rata trade menang</div>
                <div class="mt-2 text-3xl font-semibold tabular {{ $s->avgWin === null ? 'text-ink-faint' : 'text-viz-positive' }}">
                    {{ $uang($s->avgWin) ?? 'belum ada' }}
                </div>
                <div class="mt-1 text-xs text-ink-faint">
                    {{ $s->winRate === null ? 'win rate belum ada' : $s->winRate.'% menang' }}
                </div>
            </div>

            <div class="p-5 bg-surface shadow-soft sm:rounded-xl spotlight transition-shadow hover:shadow-lift">
                <div class="text-sm text-ink-muted">Rata-rata trade kalah</div>
                <div class="mt-2 text-3xl font-semibold tabular {{ $s->avgLoss === null ? 'text-ink-faint' : 'text-viz-negative' }}">
                    {{ $uang($s->avgLoss) ?? 'belum ada' }}
                </div>
                <div class="mt-1 text-xs text-ink-faint">
                    @if ($s->profitFactor === null)
                        profit factor belum ada
                    @else
                        profit factor {{ number_format($s->profitFactor, 2) }}
                    @endif
                </div>
            </div>
        </div>

        <div class="grid grid-cols-1 lg:grid-cols-3 gap-4">
            {{-- Kepatuhan: alasan aplikasi ini ada, bukan metrik tambahan. --}}
            <div class="p-5 bg-surface shadow-soft sm:rounded-xl spotlight transition-shadow hover:shadow-lift">
                <div class="text-sm text-ink-muted">Kepatuhan rata-rata</div>
                <div class="mt-2 text-3xl font-semibold tabular text-ink">
                    {{ $s->avgCompliance === null ? 'belum ada' : number_format($s->avgCompliance, 0).'%' }}
                </div>
                <div class="mt-1 text-xs text-ink-faint">
                    Pecahannya terhadap hasil ada di
                    <a href="{{ route('reports') }}" class="underline" wire:navigate>Laporan</a>.
                </div>
            </div>

            {{-- Satu seri, satu sumbu, tanpa pustaka chart. --}}
            <div class="lg:col-span-2 p-5 bg-surface shadow-soft sm:rounded-xl spotlight transition-shadow hover:shadow-lift">
                <div class="flex items-baseline justify-between gap-4">
                    <div class="text-sm text-ink-muted">P&amp;L kumulatif</div>
                    @if ($adaChart)
                        <div class="text-sm tabular {{ $nadaAngka($akhir) }}">{{ $uang($akhir) }}</div>
                    @endif
                </div>

                @if ($adaChart)
                    <svg viewBox="0 0 {{ $W }} {{ $H }}" class="mt-3 w-full h-[150px]" role="img"
                         aria-label="Garis P&amp;L kumulatif dari {{ count($nilai) }} trade tertutup, berakhir di {{ $uang($akhir) }}">
                        <line x1="0" y1="{{ $yNol }}" x2="{{ $W }}" y2="{{ $yNol }}"
                              stroke="rgb(var(--line-strong))" stroke-width="1" stroke-dasharray="3 3" />
                        <polyline points="{{ $garis }}" fill="none" stroke-width="2"
                                  stroke-linejoin="round" stroke-linecap="round" stroke="{{ $warnaGaris }}" />
                        @foreach ($koordinat as $i => $p)
                            <circle cx="{{ $p[0] }}" cy="{{ $p[1] }}" r="8" fill="transparent">
                                <title>{{ $titik[$i]['date'] }}: {{ $uang($titik[$i]['value']) }}</title>
                            </circle>
                        @endforeach
                        <circle cx="{{ $akhirKoordinat[0] }}" cy="{{ $akhirKoordinat[1] }}" r="3.5" fill="{{ $warnaGaris }}" />
                    </svg>
                    <p class="mt-2 text-xs text-ink-faint">
                        Garis putus-putus adalah titik impas. Arahkan kursor ke satu titik untuk melihat tanggalnya.
                    </p>
                @else
                    <p class="mt-3 text-sm text-ink-faint">
                        Butuh minimal dua trade tertutup sebelum ada garis yang bisa digambar.
                    </p>
                @endif
            </div>
        </div>
    @endif

    {{-- Kalender P&L harian --}}
    <div class="p-4 sm:p-6 bg-surface shadow-soft sm:rounded-xl spotlight transition-shadow hover:shadow-lift">
        <div class="flex items-center justify-between gap-4">
            <h2 class="text-lg font-medium text-ink">{{ $this->monthLabel() }}</h2>
            <div class="flex items-center gap-2">
                <button type="button" wire:click="previousMonth"
                        class="press rounded-md border border-line-strong px-3 py-1.5 text-sm text-ink-muted hover:text-ink hover:bg-surface-sunken"
                        aria-label="Bulan sebelumnya">&larr;</button>
                <button type="button" wire:click="nextMonth"
                        class="press rounded-md border border-line-strong px-3 py-1.5 text-sm text-ink-muted hover:text-ink hover:bg-surface-sunken"
                        aria-label="Bulan berikutnya">&rarr;</button>
            </div>
        </div>

        <div class="mt-4 grid grid-cols-7 gap-px bg-line rounded-md overflow-hidden border border-line">
            @foreach (['Min', 'Sen', 'Sel', 'Rab', 'Kam', 'Jum', 'Sab'] as $hari)
                <div class="bg-surface-sunken px-2 py-2 text-xs font-medium text-ink-muted text-center">{{ $hari }}</div>
            @endforeach

            @foreach ($this->calendar() as $sel)
                @php
                    $p = $sel['pnl'];
                    // Divergen: dua kutub dengan titik tengah netral. Angkanya
                    // selalu ikut tercetak, jadi warna tidak pernah sendirian
                    // memikul arti.
                    $latar = $p === null
                        ? 'bg-surface'
                        : ($p > 0 ? 'bg-viz-positive/10' : ($p < 0 ? 'bg-viz-negative/10' : 'bg-viz-neutral/60'));
                @endphp
                <div class="min-h-[84px] p-2 {{ $latar }} {{ $sel['inMonth'] ? '' : 'opacity-40' }}">
                    <div class="text-xs text-ink-faint text-right tabular">{{ $sel['date']->day }}</div>
                    @if ($p !== null)
                        <div class="mt-1 text-sm font-semibold tabular {{ $nadaAngka($p) }}">{{ $uang($p) }}</div>
                        <div class="text-[11px] text-ink-faint">{{ $sel['count'] }} trade</div>
                    @endif
                </div>
            @endforeach
        </div>

        <p class="mt-3 text-xs text-ink-faint">
            Dikelompokkan pada tanggal trade ditutup, bukan tanggal dibuka: hasilnya baru ada saat posisi selesai.
        </p>
    </div>
</div>
