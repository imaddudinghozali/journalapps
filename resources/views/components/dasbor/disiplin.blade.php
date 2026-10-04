@props(['skor', 'delay' => 0])

@php
    use App\Support\DisciplineScore;
    use App\Support\Kurva;
    use App\Support\TradeStatistics;

    $P = 60; $J = 44;
    $urut = array_keys(DisciplineScore::BOBOT);

    $poligon = Kurva::garis(Kurva::jaring(array_map(fn ($k) => $skor->axes[$k], $urut), $P, $J));
    $rusuk = Kurva::jaring(array_fill(0, count($urut), 100.0), $P, $J);
    $batas = Kurva::garis($rusuk);
    $tengah = Kurva::garis(Kurva::jaring(array_fill(0, count($urut), 50.0), $P, $J));
@endphp

<x-card label="Skor Disiplin" :delay="$delay">
    <div class="mt-3 flex items-center gap-4">
        <svg viewBox="0 0 120 120" class="h-28 w-28 shrink-0" aria-hidden="true">
            <polygon points="{{ $batas }}" fill="none" stroke="rgb(var(--line))" stroke-width="1" />
            <polygon points="{{ $tengah }}" fill="none" stroke="rgb(var(--line))" stroke-width="1" stroke-dasharray="2 3" />
            @foreach ($rusuk as $t)
                <line x1="{{ $P }}" y1="{{ $P }}" x2="{{ $t[0] }}" y2="{{ $t[1] }}"
                      stroke="rgb(var(--line))" stroke-width="1" />
            @endforeach

            <polygon points="{{ $poligon }}"
                     fill="rgb(var(--accent) / {{ $skor->hasEnoughSample() ? '0.28' : '0.10' }})"
                     stroke="rgb(var(--accent-ink))"
                     stroke-width="{{ $skor->hasEnoughSample() ? 2 : 1 }}"
                     stroke-dasharray="{{ $skor->hasEnoughSample() ? '0' : '3 3' }}" />
        </svg>

        <div class="min-w-0">
            <div class="text-3xl font-semibold tracking-tight tabular text-ink">
                {{ $skor->value ?? 'belum' }}
            </div>

            @unless ($skor->hasEnoughSample())
                <p class="mt-1 text-xs text-ink-faint">
                    {{ $skor->sampleSize }}/{{ TradeStatistics::MIN_SAMPLE }} trade menuju skor
                </p>
            @endunless

            {{-- Keempat sumbunya selalu ikut tercetak. Angka tunggal tidak
                 boleh berdiri sendiri: ia mencampur apa yang kamu lakukan
                 dengan apa yang terjadi, dan 73 saja tidak menjawab
                 "strateginya atau saya?". --}}
            <ul class="mt-2 space-y-0.5 text-xs text-ink-muted">
                @foreach ($urut as $k)
                    <li class="flex items-baseline justify-between gap-4">
                        <span>{{ DisciplineScore::LABEL[$k] }}</span>
                        <span class="tabular text-ink">{{ (int) round($skor->axes[$k]) }}</span>
                    </li>
                @endforeach
            </ul>
        </div>
    </div>
</x-card>
