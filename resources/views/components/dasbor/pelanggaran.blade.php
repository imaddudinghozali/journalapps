@props(['metrik', 'delay' => 0])

@php
    use App\Support\Angka;
@endphp

<x-card label="Rule paling sering dilanggar" :delay="$delay">
    @if ($metrik->topViolations === [])
        <p class="mt-4 text-sm text-ink-faint">
            Belum ada rule yang tercatat dilanggar. Tidak ada yang perlu ditampilkan di sini.
        </p>
    @else
        <ul class="mt-4 space-y-4">
            @foreach ($metrik->topViolations as $baris)
                @php
                    $patuh = $baris['met']->expectancy;
                    $langgar = $baris['unmet']->expectancy;
                    $cukup = $baris['met']->hasEnoughSample() && $baris['unmet']->hasEnoughSample();
                @endphp
                <li wire:key="langgar-{{ $baris['rule_id'] ?? $baris['label'] }}">
                    <div class="flex items-baseline justify-between gap-3">
                        <span class="min-w-0 text-sm text-ink">{{ $baris['label'] }}</span>
                        <span class="shrink-0 text-sm tabular text-viz-negative">{{ $baris['violations'] }}x</span>
                    </div>

                    @if ($cukup)
                        <p class="mt-1 text-xs tabular text-ink-faint">
                            Dipatuhi <span class="{{ Angka::nada($patuh) }}">{{ Angka::r($patuh) }}</span>
                            &middot; dilanggar <span class="{{ Angka::nada($langgar) }}">{{ Angka::r($langgar) }}</span>
                        </p>
                    @else
                        <p class="mt-1 text-xs text-ink-faint">Sampelnya belum cukup untuk dibandingkan.</p>
                    @endif
                </li>
            @endforeach
        </ul>
    @endif
</x-card>
