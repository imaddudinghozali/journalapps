@props(['metrik', 'delay' => 0])

@php
    use App\Support\Angka;
    use App\Support\Kurva;

    $deret = array_map(fn ($d) => (float) $d['drawdown'], $metrik->drawdownR);
    $cukup = count($deret) >= 2;

    $W = 320; $H = 96; $PAD = 6;

    if ($cukup) {
        $koordinat = Kurva::titik($deret, $W, $H, $PAD);
        $yNol = Kurva::y($deret, 0.0, $H, $PAD);
    }
@endphp

<x-card label="Drawdown (R)" :delay="$delay">
    <div class="mt-3 text-2xl font-semibold tracking-tight tabular {{ Angka::nada($metrik->maxDrawdownR) }}">
        {{ Angka::r($metrik->maxDrawdownR) }}
    </div>
    <div class="mt-1 text-xs text-ink-faint">terdalam dari puncak tertinggi</div>

    @if ($cukup)
        <svg viewBox="0 0 {{ $W }} {{ $H }}" class="mt-3 w-full" style="height: {{ $H }}px" preserveAspectRatio="none"
             role="img" aria-label="Kurva drawdown, terdalam {{ Angka::r($metrik->maxDrawdownR) }}">
            <path d="{{ Kurva::area($koordinat, $yNol) }}" fill="rgb(var(--viz-negative) / 0.16)" />
            <line x1="0" y1="{{ $yNol }}" x2="{{ $W }}" y2="{{ $yNol }}"
                  stroke="rgb(var(--line-strong))" stroke-width="1" stroke-dasharray="4 4" />
            <polyline points="{{ Kurva::garis($koordinat) }}" fill="none" stroke="rgb(var(--viz-negative))"
                      stroke-width="2" vector-effect="non-scaling-stroke" stroke-linejoin="round" />
        </svg>
    @endif
</x-card>
