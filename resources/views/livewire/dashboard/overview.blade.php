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

    /**
     * Posisi yang belum ditutup, paling lama menggantung lebih dulu.
     *
     * Penutupannya tetap di halaman ubah trade. Perhitungan P&L berjalan di
     * dalam transaksi dengan baris instrumen dikunci; menyalinnya ke sini
     * berarti logika uang hidup di dua tempat.
     */
    #[Computed]
    public function openTrades()
    {
        return Trade::with('setup')
            ->stillOpen()
            ->orderBy('opened_at')
            ->orderBy('id')
            ->get();
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

    /**
     * Petak kalender dipecah per minggu, lengkap dengan totalnya.
     *
     * Total mingguan dihitung dari sel yang masuk bulan ini saja. Sel luapan
     * dari bulan sebelum dan sesudahnya ikut tergambar supaya petaknya utuh,
     * tapi menjumlahkannya akan membuat satu trade terhitung dua kali - sekali
     * di bulannya sendiri, sekali lagi sebagai luapan di bulan tetangga.
     */
    public function calendarWeeks(): array
    {
        $minggu = [];

        foreach (array_chunk($this->calendar(), 7) as $tujuhHari) {
            $milikBulanIni = array_filter($tujuhHari, fn (array $s) => $s['inMonth'] && $s['pnl'] !== null);

            $minggu[] = [
                'days' => $tujuhHari,
                'pnl' => $milikBulanIni === [] ? null : array_sum(array_column($milikBulanIni, 'pnl')),
                'count' => array_sum(array_column($milikBulanIni, 'count')),
            ];
        }

        return $minggu;
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

    /*
    | Simbol mata uang dipatri sebagai dolar karena seluruh instrumen di
    | aplikasi ini dikutip terhadap USD (XAUUSD, EURUSD, BTCUSD). Begitu ada
    | pengguna yang akunnya bukan USD, ini harus naik jadi pengaturan di
    | profil - dicatat di README bagian "Yang belum dikerjakan".
    */
    $uang = fn (?float $v) => $v === null ? null : ($v < 0 ? '-' : '').'$'.number_format(abs($v), 2);

    // Varian bertanda untuk angka yang arahnya penting. Plus eksplisit di
    // angka positif membuat arahnya terbaca tanpa harus mengandalkan warna -
    // syarat agar pembaca buta warna tetap mendapat informasinya.
    $uangBertanda = fn (?float $v) => $v === null
        ? null
        : ($v > 0 ? '+' : ($v < 0 ? '-' : '')).'$'.number_format(abs($v), 2);

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
        $awalKoordinat = $koordinat[0];
        $warnaGaris = $akhir >= 0 ? 'rgb(var(--viz-positive))' : 'rgb(var(--viz-negative))';

        // Area di bawah garis, ditutup ke garis impas - bukan ke dasar kanvas.
        // Ditutup ke dasar, bagian yang merugi akan ikut terisi dan terbaca
        // seolah tetap ada hasilnya.
        $area = 'M '.$awalKoordinat[0].','.$yNol
            .' L '.implode(' L ', array_map(fn ($p) => $p[0].','.$p[1], $koordinat))
            .' L '.$akhirKoordinat[0].','.$yNol.' Z';

        $tglAwal = $titik[0]['date'] ?? null;
        $tglAkhir = $titik[count($titik) - 1]['date'] ?? null;
    }
@endphp

<div class="space-y-6">
    {{-- Posisi terbuka duluan. Angka ringkasan melaporkan masa lalu; ini
         sekarang, dan satu-satunya hal di halaman ini yang menuntut tindakan.
         Karena itu ia yang jadi panel, dan ringkasan di bawahnya turun jadi
         blok - dua panel bersebelahan berarti tidak ada yang diutamakan. --}}
    @if ($this->openTrades->isNotEmpty())
        <section class="kaca panel border-l-2 border-l-accent p-4 sm:p-6" aria-labelledby="judul-terbuka">
            <header class="flex flex-wrap items-baseline justify-between gap-2">
                <h2 id="judul-terbuka" class="text-lg font-medium text-ink">Posisi terbuka</h2>
                <span class="text-sm text-ink-faint tabular">{{ $this->openTrades->count() }} posisi</span>
            </header>

            <ul class="mt-4 divide-y divide-line">
                @foreach ($this->openTrades as $terbuka)
                    <li class="flex flex-wrap items-center justify-between gap-x-4 gap-y-1 py-3"
                        wire:key="terbuka-{{ $terbuka->id }}">
                        <div class="min-w-0">
                            <span class="font-medium text-ink">{{ $terbuka->symbol }}</span>
                            <span class="text-ink-muted">{{ $terbuka->direction }}</span>
                            <span class="block text-sm text-ink-faint">
                                {{ $terbuka->setup->name }} &middot;
                                dibuka {{ $terbuka->opened_at->format('d/m/Y H:i') }}
                                ({{ $terbuka->opened_at->diffForHumans(syntax: \Carbon\CarbonInterface::DIFF_ABSOLUTE) }})
                            </span>
                        </div>

                        <a href="{{ route('trades.edit', $terbuka) }}" wire:navigate
                           class="press shrink-0 rounded-md border border-line-strong px-3 py-1.5 text-sm text-ink hover:bg-surface-sunken">
                            Tutup
                        </a>
                    </li>
                @endforeach
            </ul>
        </section>
    @endif

    @if ($s->isEmpty())
        <div class="blok p-8">
            <h2 class="text-lg font-medium text-ink">Belum ada yang bisa diringkas</h2>
            <p class="mt-2 text-sm text-ink-muted max-w-[60ch]">
                Angka di halaman ini datang dari trade yang sudah kamu tutup. Buat setup di
                <a href="{{ route('setups') }}" class="underline" wire:navigate>Setup &amp; Rules</a>, lalu catat
                trade pertamamu di <a href="{{ route('trades') }}" class="underline" wire:navigate>Jurnal Trade</a>.
            </p>
        </div>
    @else
        {{-- Angka pokok: satu panel, bukan empat kartu. Keempatnya adalah satu
             ringkasan yang sama, jadi dipisah garis rambut, bukan jarak. --}}
        <div class="blok overflow-hidden grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4">
            <div class="sel-angka p-5">
                <div class="flex items-center gap-2 text-sm text-ink-muted">
                    <x-phosphor-coins class="h-4 w-4 shrink-0 text-ink-faint" aria-hidden="true" />
                    P&amp;L bersih
                </div>
                <div class="mt-2 text-3xl font-semibold tracking-tight tabular {{ $nadaAngka($s->netPnl) }}">
                    {{ $uangBertanda($s->netPnl) }}
                </div>
                <div class="mt-1 text-xs text-ink-faint">dari {{ $s->closedCount }} trade tertutup</div>
            </div>

            <div class="sel-angka p-5">
                <div class="flex items-center gap-2 text-sm text-ink-muted">
                    <x-phosphor-equals class="h-4 w-4 shrink-0 text-ink-faint" aria-hidden="true" />
                    Rata-rata per trade
                </div>
                <div class="mt-2 text-3xl font-semibold tracking-tight tabular {{ $nadaAngka($s->avgTrade) }}">
                    {{ $uangBertanda($s->avgTrade) ?? 'belum ada' }}
                </div>
                <div class="mt-1 text-xs text-ink-faint">{{ $s->openCount }} masih terbuka</div>
            </div>

            <div class="sel-angka p-5">
                <div class="flex items-center gap-2 text-sm text-ink-muted">
                    <x-phosphor-trend-up class="h-4 w-4 shrink-0 text-ink-faint" aria-hidden="true" />
                    Rata-rata trade menang
                </div>
                <div class="mt-2 text-3xl font-semibold tracking-tight tabular {{ $s->avgWin === null ? 'text-ink-faint' : 'text-viz-positive' }}">
                    {{ $uang($s->avgWin) ?? 'belum ada' }}
                </div>
                <div class="mt-1 text-xs text-ink-faint">
                    {{ $s->winRate === null ? 'win rate belum ada' : $s->winRate.'% menang' }}
                </div>
            </div>

            <div class="sel-angka p-5">
                <div class="flex items-center gap-2 text-sm text-ink-muted">
                    <x-phosphor-trend-down class="h-4 w-4 shrink-0 text-ink-faint" aria-hidden="true" />
                    Rata-rata trade kalah
                </div>
                <div class="mt-2 text-3xl font-semibold tracking-tight tabular {{ $s->avgLoss === null ? 'text-ink-faint' : 'text-viz-negative' }}">
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
            {{-- Kepatuhan: alasan aplikasi ini ada, bukan metrik tambahan. Tetap
                 setingkat blok, tapi tepi aksen membedakannya dari grafik di
                 sebelahnya tanpa menaikkannya jadi panel kedua. --}}
            <div class="blok border-l-2 border-l-accent p-5">
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
            <div class="blok lg:col-span-2 p-5">
                <div class="flex items-baseline justify-between gap-4">
                    <div class="text-sm text-ink-muted">P&amp;L kumulatif</div>
                    @if ($adaChart)
                        <div class="text-sm tabular {{ $nadaAngka($akhir) }}">{{ $uang($akhir) }}</div>
                    @endif
                </div>

                @if ($adaChart)
                    <svg viewBox="0 0 {{ $W }} {{ $H }}" class="mt-3 w-full h-[150px]" role="img" preserveAspectRatio="none"
                         aria-label="Garis P&amp;L kumulatif dari {{ count($nilai) }} trade tertutup, berakhir di {{ $uang($akhir) }}">
                        <defs>
                            {{-- Area memudar ke arah garis impas, jadi ketebalannya
                                 sendiri sudah membawa arti: makin jauh dari impas,
                                 makin pekat. --}}
                            <linearGradient id="isi-pnl" x1="0" y1="0" x2="0" y2="1">
                                <stop offset="0%" stop-color="{{ $warnaGaris }}" stop-opacity="0.22" />
                                <stop offset="100%" stop-color="{{ $warnaGaris }}" stop-opacity="0.02" />
                            </linearGradient>
                        </defs>

                        <path d="{{ $area }}" fill="url(#isi-pnl)" />

                        <line x1="0" y1="{{ $yNol }}" x2="{{ $W }}" y2="{{ $yNol }}"
                              stroke="rgb(var(--line-strong))" stroke-width="1" stroke-dasharray="3 3" />
                        <polyline points="{{ $garis }}" fill="none" stroke-width="2" vector-effect="non-scaling-stroke"
                                  stroke-linejoin="round" stroke-linecap="round" stroke="{{ $warnaGaris }}" />
                        @foreach ($koordinat as $i => $p)
                            <circle cx="{{ $p[0] }}" cy="{{ $p[1] }}" r="8" fill="transparent">
                                <title>{{ $titik[$i]['date'] }}: {{ $uang($titik[$i]['value']) }}</title>
                            </circle>
                        @endforeach
                        <circle cx="{{ $akhirKoordinat[0] }}" cy="{{ $akhirKoordinat[1] }}" r="3.5" fill="{{ $warnaGaris }}" />
                    </svg>

                    <div class="mt-2 flex items-baseline justify-between gap-4 text-xs text-ink-faint tabular">
                        <span>{{ $tglAwal }}</span>
                        <span>{{ $tglAkhir }}</span>
                    </div>

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
    <div class="blok p-4 sm:p-6">
        <div class="flex items-center justify-between gap-4">
            <h2 class="text-lg font-medium text-ink">{{ $this->monthLabel() }}</h2>
            <div class="flex items-center gap-2">
                <button type="button" wire:click="previousMonth"
                        class="press rounded-md border border-line-strong p-2 text-ink-muted hover:text-ink hover:bg-surface-sunken"
                        aria-label="Bulan sebelumnya">
                    <x-phosphor-caret-left class="h-4 w-4" aria-hidden="true" />
                </button>
                <button type="button" wire:click="nextMonth"
                        class="press rounded-md border border-line-strong p-2 text-ink-muted hover:text-ink hover:bg-surface-sunken"
                        aria-label="Bulan berikutnya">
                    <x-phosphor-caret-right class="h-4 w-4" aria-hidden="true" />
                </button>
            </div>
        </div>

        {{-- Rangka pemuat saat pindah bulan. Tanpa ini kalender bulan lama
             tetap terpampang sampai yang baru datang, dan selama sepersekian
             detik itu angkanya terbaca seolah milik bulan yang baru. --}}
        {{-- Pembungkus yang menyandang wire:loading, bukan grid-nya sendiri:
             wire:loading menimpa display, dan grid yang dipaksa jadi
             inline-block akan runtuh jadi satu kolom. --}}
        <div class="w-full" wire:loading wire:target="previousMonth,nextMonth" aria-hidden="true">
            <div class="mt-4 grid grid-cols-8 gap-px bg-line rounded-md overflow-hidden border border-line">
                @foreach (['Min', 'Sen', 'Sel', 'Rab', 'Kam', 'Jum', 'Sab', 'Minggu'] as $hari)
                    <div class="bg-surface-sunken px-2 py-2 text-xs font-medium text-ink-muted text-center">{{ $hari }}</div>
                @endforeach

                @for ($i = 0; $i < 48; $i++)
                    @php
                        // Bentuk panjang, bukan bentuk singkat berkurung: yang
                        // singkat gagal terkompilasi untuk ekspresi ini dan
                        // ikut merusak sisa berkas.
                        $kolomTotal = $i % 8 === 7;
                    @endphp
                    <div class="min-h-[84px] p-2 {{ $kolomTotal ? 'bg-surface-sunken' : 'bg-surface' }}">
                        <div class="ms-auto h-3 w-4 rangka"></div>
                        @if ($kolomTotal || $i % 5 === 2)
                            <div class="mt-2 h-4 w-14 rangka"></div>
                            <div class="mt-1 h-2.5 w-10 rangka"></div>
                        @endif
                    </div>
                @endfor
            </div>
        </div>

        <div class="mt-4 grid grid-cols-8 gap-px bg-line rounded-md overflow-hidden border border-line"
             wire:loading.remove wire:target="previousMonth,nextMonth">
            @foreach (['Min', 'Sen', 'Sel', 'Rab', 'Kam', 'Jum', 'Sab'] as $hari)
                <div class="bg-surface-sunken px-2 py-2 text-xs font-medium text-ink-muted text-center">{{ $hari }}</div>
            @endforeach
            <div class="bg-surface-sunken px-2 py-2 text-xs font-medium text-ink-muted text-center">Minggu</div>

            @foreach ($this->calendarWeeks() as $iMinggu => $minggu)
                @foreach ($minggu['days'] as $sel)
                    @php
                        $p = $sel['pnl'];
                        // Divergen: dua kutub dengan titik tengah netral. Angkanya
                        // selalu ikut tercetak, jadi warna tidak pernah sendirian
                        // memikul arti.
                        $latar = $p === null
                            ? 'bg-surface'
                            : ($p > 0 ? 'bg-viz-positive/10' : ($p < 0 ? 'bg-viz-negative/10' : 'bg-viz-neutral/60'));
                    @endphp
                    <div class="relative min-h-[84px] p-2 {{ $latar }} {{ $sel['inMonth'] ? '' : 'opacity-40' }}"
                         wire:key="sel-{{ $sel['date']->toDateString() }}">
                        <div class="flex items-center justify-end gap-1">
                            @if ($sel['date']->isToday())
                                {{-- Hari ini ditandai titik, bukan latar berwarna:
                                     latar di sel ini sudah dipakai menyatakan untung
                                     atau rugi, dan dua arti pada satu properti
                                     berarti salah satunya pasti terbaca keliru. --}}
                                <span class="h-1.5 w-1.5 rounded-full bg-accent" aria-hidden="true"></span>
                                <span class="sr-only">Hari ini,</span>
                            @endif
                            <span class="text-xs text-ink-faint tabular">{{ $sel['date']->day }}</span>
                        </div>

                        @if ($p !== null)
                            <div class="mt-1 text-sm font-semibold tabular {{ $nadaAngka($p) }}">{{ $uangBertanda($p) }}</div>
                            <div class="text-[11px] text-ink-faint">{{ $sel['count'] }} trade</div>
                        @endif
                    </div>
                @endforeach

                {{-- Total mingguan. Satu minggu adalah satuan yang benar-benar
                     dipakai orang saat meninjau: cukup panjang untuk meredam
                     keberuntungan harian, cukup pendek untuk masih diingat. --}}
                <div class="min-h-[84px] bg-surface-sunken p-2" wire:key="minggu-{{ $iMinggu }}">
                    <div class="text-right text-[11px] text-ink-faint">M{{ $iMinggu + 1 }}</div>
                    @if ($minggu['pnl'] !== null)
                        <div class="mt-1 text-sm font-semibold tabular {{ $nadaAngka($minggu['pnl']) }}">
                            {{ $uangBertanda($minggu['pnl']) }}
                        </div>
                        <div class="text-[11px] text-ink-faint">{{ $minggu['count'] }} trade</div>
                    @endif
                </div>
            @endforeach
        </div>

        <p class="mt-3 text-xs text-ink-faint">
            Dikelompokkan pada tanggal trade ditutup, bukan tanggal dibuka: hasilnya baru ada saat posisi selesai.
        </p>
    </div>
</div>
