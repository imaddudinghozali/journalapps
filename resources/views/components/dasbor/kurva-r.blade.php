@props(['metrik', 'delay' => 0])

@php
    use App\Support\Angka;
    use App\Support\Kurva;

    $titik = $metrik->cumulativeR;
    $nilai = array_map(fn ($t) => (float) $t['cumulative'], $titik);
    $cukup = count($nilai) >= 2;

    $W = 720; $H = 170; $PAD = 10;

    if ($cukup) {
        $koordinat = Kurva::titik($nilai, $W, $H, $PAD);
        $yNol = Kurva::y($nilai, 0.0, $H, $PAD);
        $akhir = end($nilai);
        $warna = $akhir >= 0 ? 'rgb(var(--viz-positive))' : 'rgb(var(--viz-negative))';

        $umpan = [];
        foreach ($titik as $i => $t) {
            $umpan[] = [
                'x' => $koordinat[$i][0],
                'tgl' => $t['date'],
                'r' => Angka::r((float) $t['r']),
                'kum' => Angka::r((float) $t['cumulative']),
                'skor' => $t['score'],
                'simbol' => $t['symbol'],
            ];
        }
    }
@endphp

<x-card label="P&L kumulatif (R)" :delay="$delay" class="lg:col-span-2">
    @if (! $cukup)
        <p class="mt-6 text-sm text-ink-faint">Butuh minimal dua trade tertutup sebelum ada garis yang bisa digambar.</p>
    @else
        <div class="mt-2 flex flex-wrap items-baseline justify-between gap-x-4 gap-y-1">
            <div class="flex items-baseline gap-2">
                <span class="text-2xl font-semibold tracking-tight tabular {{ Angka::nada($akhir) }}">{{ Angka::r($akhir) }}</span>
                {{-- Dolar sebagai keterangan, bukan angka pokok: R yang bisa
                     dibandingkan antar instrumen, tapi dolar yang dirasakan.
                     Keduanya perlu terbaca berdampingan. --}}
                <span class="tabular text-sm text-ink-muted">{{ Angka::uang($metrik->summary->netPnl, true) }}</span>
            </div>
            <span class="text-xs text-ink-faint">{{ count($titik) }} trade tertutup</span>
        </div>

        <div class="relative mt-3" x-data="kurvaSorot(@js($umpan), {{ $W }})"
             @pointermove="arahkan($event)" @pointerleave="lepas()">
            <svg viewBox="0 0 {{ $W }} {{ $H }}" class="w-full" style="height: {{ $H }}px" preserveAspectRatio="none"
                 role="img" aria-label="Kurva P&amp;L kumulatif dalam R dari {{ count($titik) }} trade, berakhir di {{ Angka::r($akhir) }}">
                <defs>
                    <linearGradient id="isi-r" x1="0" y1="0" x2="0" y2="1">
                        <stop offset="0%" stop-color="{{ $warna }}" stop-opacity="0.24" />
                        <stop offset="100%" stop-color="{{ $warna }}" stop-opacity="0.02" />
                    </linearGradient>
                </defs>

                <path d="{{ Kurva::area($koordinat, $yNol) }}" fill="url(#isi-r)" />

                <line x1="0" y1="{{ $yNol }}" x2="{{ $W }}" y2="{{ $yNol }}"
                      stroke="rgb(var(--line-strong))" stroke-width="1" stroke-dasharray="4 4" />

                <polyline points="{{ Kurva::garis($koordinat) }}" fill="none" stroke="{{ $warna }}"
                          stroke-width="2" vector-effect="non-scaling-stroke" stroke-linejoin="round" stroke-linecap="round" />

                {{-- Titik diwarnai menurut KEPATUHAN trade-nya, bukan untung
                     rugi: garisnya sudah menyatakan hasil, dan yang ingin
                     terbaca di sini apakah hasil itu datang dari trade yang
                     patuh. --}}
                @foreach ($titik as $i => $t)
                    <circle cx="{{ $koordinat[$i][0] }}" cy="{{ $koordinat[$i][1] }}" r="3"
                            fill="{{ $t['compliant'] === false ? 'rgb(var(--viz-negative))' : ($t['compliant'] === true ? 'rgb(var(--viz-positive))' : 'rgb(var(--line-strong))') }}" />
                @endforeach

                {{-- opacity, bukan x-show. x-show bekerja lewat properti
                     `display`, dan pada elemen SVG hasilnya tidak konsisten:
                     pengikatan x1 ikut berubah dengan benar sementara garisnya
                     tetap tersembunyi. opacity atribut asli SVG. --}}
                <line :x1="garisX" :x2="garisX" y1="0" y2="{{ $H }}"
                      :opacity="aktif === null ? 0 : 1" opacity="0"
                      stroke="rgb(var(--accent-ink))" stroke-width="1" stroke-dasharray="3 3" />
            </svg>

            <div x-show="aktif !== null" x-cloak :style="gayaTooltip"
                 class="kaca pointer-events-none absolute z-20 w-44 rounded-xl p-3 text-xs">
                <div class="font-medium text-ink" x-text="aktif?.simbol + ' - ' + aktif?.tgl"></div>
                <div class="mt-1 tabular text-ink-muted">Hasil <span class="text-ink" x-text="aktif?.r"></span></div>
                <div class="tabular text-ink-muted">Kumulatif <span class="text-ink" x-text="aktif?.kum"></span></div>
                <div class="tabular text-ink-muted">Kepatuhan <span class="text-ink" x-text="aktif?.skor === null ? 'tanpa skor' : aktif?.skor + '%'"></span></div>
            </div>
        </div>

        <div class="mt-3 flex flex-wrap items-center gap-x-5 gap-y-1 text-xs text-ink-faint">
            <span class="flex items-center gap-1.5"><span class="h-2 w-2 rounded-full bg-viz-positive"></span> patuh</span>
            <span class="flex items-center gap-1.5"><span class="h-2 w-2 rounded-full bg-viz-negative"></span> melanggar</span>
            <span class="flex items-center gap-1.5"><span class="h-2 w-2 rounded-full bg-line-strong"></span> tanpa skor</span>
            <span>garis putus-putus = titik impas</span>
        </div>
    @endif
</x-card>
