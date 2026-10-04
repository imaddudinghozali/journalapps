@props(['metrik', 'delay' => 0])

@php
    use App\Support\Angka;

    // 14 hari terakhir yang ada datanya. Lebih dari itu, batangnya terlalu
    // tipis untuk dibandingkan satu sama lain.
    // dailyPnl berbentuk tanggal => ['pnl' => float, 'count' => int], bukan
    // tanggal => float. Dua bentuk yang mirip dan gampang tertukar.
    $harian = array_map(
        fn (array $h) => (float) $h['pnl'],
        array_slice($metrik->summary->dailyPnl, -14, 14, true),
    );

    $puncak = $harian === [] ? 0.0 : max(array_map('abs', array_values($harian)));
@endphp

<x-card label="P&L harian" :delay="$delay">
    @if ($harian === [])
        <p class="mt-6 text-sm text-ink-faint">Belum ada hari dengan trade tertutup.</p>
    @else
        <div class="mt-4 flex h-[96px] items-stretch gap-1">
            @foreach ($harian as $tanggal => $nilai)
                @php($tinggi = $puncak > 0 ? max(2, (int) round(abs($nilai) / $puncak * 40)) : 2)
                <div class="flex flex-1 flex-col" title="{{ $tanggal }}: {{ Angka::uang((float) $nilai, true) }}">
                    <div class="flex flex-1 items-end">
                        @if ($nilai > 0)
                            <div class="w-full rounded-t-sm bg-viz-positive/70" style="height: {{ $tinggi }}px"></div>
                        @endif
                    </div>
                    <div class="h-px w-full bg-line"></div>
                    <div class="flex flex-1 items-start">
                        @if ($nilai < 0)
                            <div class="w-full rounded-b-sm bg-viz-negative/70" style="height: {{ $tinggi }}px"></div>
                        @endif
                    </div>
                </div>
            @endforeach
        </div>
        <p class="mt-2 text-xs text-ink-faint">{{ count($harian) }} hari terakhir yang ada tradenya</p>
    @endif
</x-card>
