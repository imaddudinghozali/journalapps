<?php

use App\Models\Trade;
use App\Support\Angka;
use App\Support\ComplianceTone;
use App\Support\DashboardMetrics;
use App\Support\Periode;
use App\Support\TradeStatistics;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Url;
use Livewire\Volt\Component;

new class extends Component
{
    public int $year;

    public int $month;

    /**
     * Rentang waktu untuk kartu ringkasan.
     *
     * Ikut ke query string supaya rentang yang dipilih selamat dari muat
     * ulang. Konsekuensinya ia masukan yang tidak dipercaya, jadi properti ini
     * TIDAK PERNAH dibaca langsung - selalu lewat rentang(), yang menjatuhkan
     * kunci asing ke seluruh riwayat.
     */
    #[Url]
    public string $periode = Periode::BAWAAN;

    /** Tanggal yang sedang dibuka ringkasannya dari kalender, format Y-m-d. */
    public ?string $tanggalDipilih = null;

    /** Kueri trade untuk satu request. Private, jadi tidak ikut diserialkan. */
    private ?Collection $kumpulan = null;

    public function mount(): void
    {
        $this->year = (int) now()->year;
        $this->month = (int) now()->month;
    }

    #[Computed]
    public function rentang(): Periode
    {
        return Periode::dari($this->periode);
    }

    public function pilihPeriode(string $kunci): void
    {
        $this->periode = Periode::dari($kunci)->kunci;

        // Tanggal yang dibuka di kalender sengaja TIDAK direset: kalender
        // punya navigasi bulan sendiri dan tidak ikut rentang ini.
        unset($this->rentang, $this->metrik);
    }

    /**
     * Seluruh angka dashboard, satu kali kueri.
     *
     * Relasi dimuat di sini karena strict mode melarang lazy load, dan karena
     * DashboardMetrics menolak bekerja tanpa keduanya.
     */
    #[Computed]
    public function metrikPenuh(): DashboardMetrics
    {
        return DashboardMetrics::from($this->trades(), $this->ambang());
    }

    /**
     * Angka yang sama, dipotong ke rentang yang dipilih.
     *
     * Pada seluruh riwayat ia mengembalikan objek yang SAMA, bukan hitungan
     * kedua atas koleksi yang identik - jalur bawaan tidak ikut membayar biaya
     * fitur ini.
     */
    #[Computed]
    public function metrik(): DashboardMetrics
    {
        if ($this->rentang()->seluruhRiwayat()) {
            return $this->metrikPenuh();
        }

        return DashboardMetrics::from(
            $this->rentang()->saring($this->trades()),
            $this->ambang(),
        );
    }

    /** @return Collection<int, Trade> */
    private function trades(): Collection
    {
        return $this->kumpulan ??= Trade::with(['setup', 'ruleChecks'])->get();
    }

    private function ambang(): int
    {
        return (int) auth()->user()->compliance_threshold;
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

    /** Trade yang ditutup pada tanggal yang sedang dibuka dari kalender. */
    #[Computed]
    public function tradeHari()
    {
        if ($this->tanggalDipilih === null) {
            return collect();
        }

        return Trade::with('setup')
            ->closed()
            ->whereDate('closed_at', $this->tanggalDipilih)
            ->orderBy('closed_at')
            ->get();
    }

    /** Klik kedua pada tanggal yang sama menutupnya lagi. */
    public function pilihTanggal(string $tanggal): void
    {
        $this->tanggalDipilih = $this->tanggalDipilih === $tanggal ? null : $tanggal;

        unset($this->tradeHari);
    }

    public function previousMonth(): void
    {
        $this->geserBulan(-1);
    }

    public function nextMonth(): void
    {
        $this->geserBulan(1);
    }

    private function geserBulan(int $arah): void
    {
        $c = Carbon::create($this->year, $this->month, 1)->addMonths($arah);

        $this->year = $c->year;
        $this->month = $c->month;

        // Tanggal terpilih dari bulan lama tidak ada lagi di petak yang baru.
        $this->tanggalDipilih = null;
        unset($this->tradeHari);
    }

    /**
     * Petak kalender dipecah per minggu, lengkap dengan total dan kepatuhan.
     *
     * Total mingguan dihitung dari sel yang masuk bulan ini saja. Sel luapan
     * dari bulan tetangga ikut tergambar supaya petaknya utuh, tapi
     * menjumlahkannya akan membuat satu trade terhitung dua kali.
     */
    public function calendarWeeks(): array
    {
        $awal = Carbon::create($this->year, $this->month, 1)->startOfWeek(Carbon::SUNDAY);
        // metrikPenuh, bukan metrik: kalender punya navigasi bulan sendiri dan
        // tidak ikut rentang di atas. Kalender yang disaring ke "Hari ini"
        // cuma menyisakan satu sel berwarna dan kehilangan seluruh gunanya.
        $harian = $this->metrikPenuh()->summary->monthlyPnl($this->year, $this->month);
        $kepatuhan = $this->metrikPenuh()->kepatuhanHarian();

        $sel = [];

        for ($i = 0; $i < 42; $i++) {
            $t = $awal->copy()->addDays($i);
            $kunci = $t->toDateString();

            $sel[] = [
                'date' => $t,
                'key' => $kunci,
                'inMonth' => $t->month === $this->month,
                'pnl' => $harian[$kunci]['pnl'] ?? null,
                'count' => $harian[$kunci]['count'] ?? 0,
                'score' => $kepatuhan[$kunci] ?? null,
            ];
        }

        $minggu = [];

        foreach (array_chunk($sel, 7) as $tujuhHari) {
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
    $penuh = $this->metrikPenuh;
    $rentang = $this->rentang;
    $m = $this->metrik;
    $s = $m->summary;

    // R kumulatif terakhir, untuk menyandingkan dolar dengan satuan pokok.
    // Diambil lewat indeks, bukan end(): end() menerima argumennya sebagai
    // referensi dan cumulativeR adalah properti readonly.
    $kum = $m->cumulativeR;
    $totalR = $kum === [] ? null : (float) $kum[count($kum) - 1]['cumulative'];

    $rentangKosong = ! $rentang->seluruhRiwayat() && $s->closedCount === 0;
    $sebutan = $rentang->seluruhRiwayat() ? 'sepanjang riwayat' : mb_strtolower($rentang->label());
@endphp

<div class="space-y-6">
    {{-- Posisi terbuka duluan. Angka ringkasan melaporkan masa lalu; ini
         sekarang, dan satu-satunya hal yang menuntut tindakan. --}}
    @if ($this->openTrades->isNotEmpty())
        <section class="kaca panel border-l-2 border-l-accent p-4 sm:p-6" aria-labelledby="judul-terbuka">
            <header class="flex flex-wrap items-baseline justify-between gap-2">
                <h2 id="judul-terbuka" class="text-lg font-medium text-ink">Posisi terbuka</h2>
                <span class="tabular text-sm text-ink-faint">{{ $this->openTrades->count() }} posisi</span>
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

    {{-- Pertanyaan "apakah ada data sama sekali" dijawab angka yang TIDAK
         tersaring. Memakai angka tersaring akan memunculkan ajakan mencatat
         trade pertama kepada orang yang sudah punya ratusan trade, cuma
         karena hari ini ia belum trading. --}}
    @if ($penuh->isEmpty())
        <div class="blok p-8">
            <h2 class="text-lg font-medium text-ink">Belum ada yang bisa diringkas</h2>
            <p class="mt-2 max-w-[60ch] text-sm text-ink-muted">
                Angka di halaman ini datang dari trade yang sudah kamu tutup. Buat setup di
                <a href="{{ route('setups') }}" class="text-accent-ink underline" wire:navigate>Setup &amp; Rules</a>, lalu catat
                trade pertamamu di <a href="{{ route('trades') }}" class="text-accent-ink underline" wire:navigate>Jurnal Trade</a>.
            </p>
        </div>
    @else
        {{-- Pemilih rentang. Kartu ringkasan mengikutinya, kalender di bawah
             tidak: keduanya menjawab pertanyaan berbeda. Kartu menjawab
             "seberapa baik rentang ini", kalender menjawab "hari yang mana". --}}
        <div class="kaca panel flex flex-wrap items-center justify-between gap-x-4 gap-y-2 px-3 py-2.5 sm:px-4">
            <div class="flex flex-wrap items-center gap-1" role="group" aria-label="Rentang waktu ringkasan">
                @foreach (Periode::daftar() as $kunci => $label)
                    <button type="button"
                            wire:click="pilihPeriode('{{ $kunci }}')"
                            wire:key="periode-{{ $kunci }}"
                            @if ($rentang->kunci === $kunci) aria-current="true" @endif
                            class="pil-nav rounded-full px-3 py-1.5 text-sm {{ $rentang->kunci === $kunci ? 'pil-nav-aktif font-medium text-ink' : 'text-ink-muted hover:text-ink' }}">
                        {{ $label }}
                    </button>
                @endforeach
            </div>

            {{-- Ambang sampel disebut terang-terangan. Tanpa kalimat ini,
                 menyempitkan rentang akan membuat tiga kartu mendadak berisi
                 "belum ada" dan terbaca sebagai halaman yang rusak. --}}
            <p class="text-xs text-ink-faint">
                @if ($s->closedCount === 0)
                    Belum ada trade tertutup {{ $sebutan }}.
                @elseif ($s->closedCount < TradeStatistics::MIN_SAMPLE)
                    {{ $s->closedCount }} dari {{ TradeStatistics::MIN_SAMPLE }} trade &mdash; expectancy,
                    profit factor, dan win rate masih disembunyikan.
                @else
                    {{ $s->closedCount }} trade tertutup {{ $sebutan }}.
                @endif
            </p>
        </div>

        @if ($rentangKosong)
            <div class="blok p-8">
                <h2 class="text-lg font-medium text-ink">Tidak ada trade tertutup {{ $sebutan }}</h2>
                <p class="mt-2 max-w-[60ch] text-sm text-ink-muted">
                    Riwayatmu tetap utuh &mdash; yang kosong hanya rentang ini. Kalender di bawah masih
                    memperlihatkan seluruh bulannya.
                </p>
                <button type="button" wire:click="pilihPeriode('{{ Periode::SEMUA }}')"
                        class="press tombol-aksen mt-4 rounded-md px-3 py-1.5 text-sm">
                    Lihat seluruh riwayat
                </button>
            </div>
        @else
        {{-- Kalimat insight di atas baris angka: ia yang menjawab pertanyaan
             produk ini, sementara KPI cuma bahan bakunya. --}}
        <x-dasbor.insight :metrik="$m" />

        <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-4">
            {{-- Rentetan diambil dari angka yang TIDAK tersaring. Rentetan
                 adalah hitungan mundur dari trade terbaru; memotongnya ke
                 rentang akan mencetak angka yang lebih kecil dari kenyataan,
                 dan itu bukan sekadar kurang lengkap - itu salah. --}}
            <x-dasbor.kepatuhan :metrik="$m" :rentetan="$penuh->complianceStreak" :delay="0" />

            <x-dasbor.kpi
                label="Total P&L"
                :nilai="Angka::uang($s->netPnl, true)"
                :angka="$s->netPnl"
                opsi="{ desimal: 2, tanda: true, awalan: '$' }"
                :nada="Angka::nada($s->netPnl)"
                :sub="$totalR === null ? $s->closedCount.' trade tertutup' : 'setara '.Angka::r($totalR)"
                :delay="60">
                <x-slot:ikon><x-phosphor-currency-dollar class="h-4 w-4" /></x-slot:ikon>

                @unless ($rentang->seluruhRiwayat())
                    {{-- Angka seluruh riwayat tetap terlihat saat rentang
                         disempitkan, supaya menyaring tidak pernah terasa
                         seperti kehilangan uang. --}}
                    <div class="mt-2 text-xs text-ink-faint">
                        Sepanjang riwayat
                        <span class="tabular {{ Angka::nada($penuh->summary->netPnl) }}">{{ Angka::uang($penuh->summary->netPnl, true) }}</span>
                    </div>
                @endunless
            </x-dasbor.kpi>

            <x-dasbor.kpi
                label="Expectancy"
                :nilai="Angka::r($m->expectancyR)"
                :angka="$m->expectancyR"
                opsi="{ desimal: 2, tanda: true, akhiran: 'R' }"
                :nada="Angka::nada($m->expectancyR)"
                :sub="'rata-rata '.Angka::uang($s->avgTrade, true).' per trade'"
                :delay="120">
                <x-slot:ikon><x-phosphor-target class="h-4 w-4" /></x-slot:ikon>
            </x-dasbor.kpi>

            <x-dasbor.kpi
                label="Profit factor"
                :nilai="Angka::kali($m->profitFactor)"
                :angka="$m->profitFactor"
                opsi="{ desimal: 2 }"
                :sub="$m->profitFactor === null ? 'belum pernah rugi' : 'untung dibagi rugi'"
                :delay="180">
                <x-slot:ikon><x-phosphor-scales class="h-4 w-4" /></x-slot:ikon>
            </x-dasbor.kpi>

            <x-dasbor.kpi
                label="Win rate"
                :nilai="Angka::persen($m->winRate)"
                :angka="$m->winRate"
                opsi="{ desimal: 0, akhiran: '%' }"
                :sub="$s->closedCount.' trade tertutup'"
                :delay="240">
                <x-slot:ikon><x-phosphor-percent class="h-4 w-4" /></x-slot:ikon>
            </x-dasbor.kpi>

            <x-dasbor.kpi
                label="Rata-rata menang / kalah"
                :nilai="Angka::r($m->avgWinR)"
                :nada="Angka::nada($m->avgWinR)"
                :sub="'kalah '.Angka::r($m->avgLossR)"
                :delay="300">
                <x-slot:ikon><x-phosphor-arrows-out-line-vertical class="h-4 w-4" /></x-slot:ikon>

                @php
                    $menang = abs($m->avgWinR ?? 0);
                    $kalah = abs($m->avgLossR ?? 0);
                    $totalBatang = $menang + $kalah;
                @endphp
                @if ($totalBatang > 0)
                    {{-- Dua batang berdampingan, bukan angka saja: perbandingan
                         besaran lebih cepat terbaca sebagai panjang. --}}
                    <div class="mt-3 flex h-1.5 overflow-hidden rounded-full bg-surface-sunken">
                        <div class="bg-viz-positive" style="width: {{ round($menang / $totalBatang * 100, 1) }}%"></div>
                        <div class="bg-viz-negative" style="width: {{ round($kalah / $totalBatang * 100, 1) }}%"></div>
                    </div>
                @endif
            </x-dasbor.kpi>

            <x-dasbor.kpi
                label="Pelanggaran rule wajib"
                :nilai="(string) $m->requiredViolations"
                :angka="$m->requiredViolations"
                opsi="{ desimal: 0 }"
                :nada="$m->requiredViolations > 0 ? 'text-viz-negative' : 'text-ink'"
                sub="rule wajib yang tidak terpenuhi"
                :delay="360">
                <x-slot:ikon><x-phosphor-warning class="h-4 w-4" /></x-slot:ikon>
            </x-dasbor.kpi>
        </div>

        <div class="grid grid-cols-1 gap-4 lg:grid-cols-4">
            <x-dasbor.disiplin :skor="$m->disiplin()" :delay="0" />
            <x-dasbor.kurva-r :metrik="$m" :delay="60" />
            <x-dasbor.drawdown :metrik="$m" :delay="120" />
        </div>

        <div class="grid grid-cols-1 gap-4 lg:grid-cols-3">
            <x-dasbor.harian :metrik="$m" :delay="0" />
            <x-dasbor.pelanggaran :metrik="$m" :delay="60" />
            <x-dasbor.trade-terakhir :metrik="$m" :delay="120" />
        </div>
        @endif
    @endif

    {{-- Kalender P&L harian --}}
    <div class="blok p-4 sm:p-6">
        <div class="flex items-center justify-between gap-4">
            <h2 class="text-lg font-medium text-ink">{{ $this->monthLabel() }}</h2>
            <div class="flex items-center gap-2">
                <button type="button" wire:click="previousMonth"
                        class="press rounded-md border border-line-strong p-2 text-ink-muted hover:bg-surface-sunken hover:text-ink"
                        aria-label="Bulan sebelumnya">
                    <x-phosphor-caret-left class="h-4 w-4" aria-hidden="true" />
                </button>
                <button type="button" wire:click="nextMonth"
                        class="press rounded-md border border-line-strong p-2 text-ink-muted hover:bg-surface-sunken hover:text-ink"
                        aria-label="Bulan berikutnya">
                    <x-phosphor-caret-right class="h-4 w-4" aria-hidden="true" />
                </button>
            </div>
        </div>

        {{-- Pembungkus yang menyandang wire:loading, bukan grid-nya sendiri:
             wire:loading menimpa display, dan grid yang dipaksa jadi
             inline-block akan runtuh jadi satu kolom. --}}
        <div class="w-full" wire:loading wire:target="previousMonth,nextMonth" aria-hidden="true">
            <div class="mt-4 grid grid-cols-8 gap-px overflow-hidden rounded-md border border-line bg-line">
                @foreach (['Min', 'Sen', 'Sel', 'Rab', 'Kam', 'Jum', 'Sab', 'Minggu'] as $hari)
                    <div class="bg-surface-sunken px-2 py-2 text-center text-xs font-medium text-ink-muted">{{ $hari }}</div>
                @endforeach

                @for ($i = 0; $i < 48; $i++)
                    @php
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

        <div class="mt-4 grid grid-cols-8 gap-px overflow-hidden rounded-md border border-line bg-line"
             wire:loading.remove wire:target="previousMonth,nextMonth">
            @foreach (['Min', 'Sen', 'Sel', 'Rab', 'Kam', 'Jum', 'Sab'] as $hari)
                <div class="bg-surface-sunken px-2 py-2 text-center text-xs font-medium text-ink-muted">{{ $hari }}</div>
            @endforeach
            <div class="bg-surface-sunken px-2 py-2 text-center text-xs font-medium text-ink-muted">Minggu</div>

            @foreach ($this->calendarWeeks() as $iMinggu => $minggu)
                @foreach ($minggu['days'] as $sel)
                    @php
                        $p = $sel['pnl'];
                        $adaTrade = $p !== null;

                        // Hari tanpa trade memakai permukaan netral, BUKAN warna
                        // kepatuhan rendah. Keduanya gampang tertukar, dan
                        // menyamakannya berarti menuduh pengguna melanggar di
                        // hari yang justru ia tidak trading sama sekali.
                        $latar = ! $adaTrade
                            ? 'bg-surface'
                            : ($p > 0 ? 'bg-viz-positive/10' : ($p < 0 ? 'bg-viz-negative/10' : 'bg-surface-sunken'));

                        $terpilih = $this->tanggalDipilih === $sel['key'];
                    @endphp

                    <button type="button"
                            @if ($adaTrade) wire:click="pilihTanggal('{{ $sel['key'] }}')" @else disabled @endif
                            class="relative min-h-[84px] p-2 text-start transition-colors {{ $latar }} {{ $sel['inMonth'] ? '' : 'opacity-40' }} {{ $adaTrade ? 'cursor-pointer hover:bg-surface-raised' : 'cursor-default' }} {{ $terpilih ? 'ring-2 ring-inset ring-accent' : '' }}"
                            wire:key="sel-{{ $sel['key'] }}"
                            @if ($adaTrade) aria-label="{{ $sel['key'] }}, {{ $sel['count'] }} trade" @endif>
                        <div class="flex items-center justify-end gap-1">
                            @if ($sel['date']->isToday())
                                {{-- Hari ini ditandai titik, bukan latar berwarna:
                                     latar di sel ini sudah dipakai menyatakan
                                     untung atau rugi. --}}
                                <span class="h-1.5 w-1.5 rounded-full bg-accent" aria-hidden="true"></span>
                                <span class="sr-only">Hari ini,</span>
                            @endif
                            <span class="tabular text-xs text-ink-faint">{{ $sel['date']->day }}</span>
                        </div>

                        @if ($adaTrade)
                            <div class="mt-1 text-sm font-semibold tabular {{ Angka::nada((float) $p) }}">
                                {{ Angka::uang((float) $p, true) }}
                            </div>
                            <div class="flex items-center gap-1.5 text-[11px] text-ink-faint">
                                @if ($sel['score'] !== null)
                                    <span class="h-1.5 w-1.5 shrink-0 rounded-full"
                                          style="background: {{ ComplianceTone::ringColor($sel['score']) }}"
                                          aria-hidden="true"></span>
                                    <span>{{ $sel['count'] }} trade &middot; {{ $sel['score'] }}%</span>
                                @else
                                    <span>{{ $sel['count'] }} trade</span>
                                @endif
                            </div>
                        @endif
                    </button>
                @endforeach

                {{-- Total mingguan. Satu minggu adalah satuan yang benar-benar
                     dipakai orang saat meninjau: cukup panjang untuk meredam
                     keberuntungan harian, cukup pendek untuk masih diingat. --}}
                <div class="min-h-[84px] bg-surface-sunken p-2" wire:key="minggu-{{ $iMinggu }}">
                    <div class="text-right text-[11px] text-ink-faint">M{{ $iMinggu + 1 }}</div>
                    @if ($minggu['pnl'] !== null)
                        <div class="mt-1 text-sm font-semibold tabular {{ Angka::nada((float) $minggu['pnl']) }}">
                            {{ Angka::uang((float) $minggu['pnl'], true) }}
                        </div>
                        <div class="text-[11px] text-ink-faint">{{ $minggu['count'] }} trade</div>
                    @endif
                </div>
            @endforeach
        </div>

        @if ($this->tanggalDipilih !== null)
            <div class="pop mt-4 rounded-xl border border-line bg-surface-raised p-4">
                <div class="flex items-baseline justify-between gap-3">
                    <h3 class="font-medium text-ink">{{ $this->tanggalDipilih }}</h3>
                    <button type="button" wire:click="pilihTanggal('{{ $this->tanggalDipilih }}')"
                            class="text-sm text-ink-faint underline underline-offset-2 hover:text-ink">Tutup</button>
                </div>

                <ul class="mt-3 divide-y divide-line">
                    @foreach ($this->tradeHari as $t)
                        <li class="flex items-center justify-between gap-3 py-2" wire:key="hari-{{ $t->id }}">
                            <div class="min-w-0">
                                <a href="{{ route('trades.edit', $t) }}" wire:navigate
                                   class="font-medium text-ink transition-colors hover:text-accent-ink">{{ $t->symbol }}</a>
                                <span class="block text-xs text-ink-faint">
                                    {{ $t->direction }} &middot; {{ $t->setup->name }} &middot; {{ $t->closed_at->format('H:i') }}
                                </span>
                            </div>
                            <div class="flex shrink-0 items-center gap-3 text-sm tabular">
                                <span class="{{ ComplianceTone::textClass($t->compliance_score) }}">
                                    {{ $t->compliance_score === null ? 'tanpa skor' : $t->compliance_score.'%' }}
                                </span>
                                <span class="w-20 text-end font-semibold {{ Angka::nada((float) $t->pnl_amount) }}">
                                    {{ Angka::uang((float) $t->pnl_amount, true) }}
                                </span>
                            </div>
                        </li>
                    @endforeach
                </ul>
            </div>
        @endif

        <p class="mt-3 text-xs text-ink-faint">
            Dikelompokkan pada tanggal trade ditutup, bukan tanggal dibuka: hasilnya baru ada saat posisi selesai.
            Hari tanpa trade dibiarkan polos, bukan diberi warna kepatuhan rendah.
        </p>
    </div>
</div>
