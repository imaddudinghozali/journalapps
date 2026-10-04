@props(['metrik', 'delay' => 0])

@php
    use App\Support\Angka;
    use App\Support\ComplianceTone;

    $skor = $metrik->avgCompliance;
    $selisih = $metrik->complianceDelta;

    // Keliling 2*pi*r dengan r=34 pada kanvas 80x80.
    $keliling = 2 * M_PI * 34;
    $terisi = $keliling * (($skor ?? 0) / 100);
@endphp

{{-- Hero. Inilah yang membedakan aplikasi ini dari jurnal trading mana pun,
     jadi ia yang paling besar dan satu-satunya yang memakai cincin. --}}
<x-card label="Kepatuhan rata-rata" :delay="$delay" class="sm:col-span-2">
    <div class="mt-3 flex items-center gap-5">
        <div class="relative shrink-0">
            <svg viewBox="0 0 80 80" class="h-20 w-20 -rotate-90" aria-hidden="true">
                <circle cx="40" cy="40" r="34" fill="none" stroke="rgb(var(--line))" stroke-width="7" />
                @if ($skor !== null)
                    <circle cx="40" cy="40" r="34" fill="none" stroke-width="7" stroke-linecap="round"
                            stroke="{{ ComplianceTone::ringColor($skor) }}"
                            stroke-dasharray="{{ round($terisi, 2) }} {{ round($keliling, 2) }}" />
                @endif
            </svg>

            <span class="absolute inset-0 grid place-items-center text-xl font-semibold tabular {{ ComplianceTone::textClass($skor) }}">
                {{ $skor === null ? 'belum' : $skor.'%' }}
            </span>
        </div>

        <div class="min-w-0 space-y-1.5 text-sm">
            @if ($selisih === null)
                <p class="text-ink-faint">Belum ada pembanding tujuh hari sebelumnya.</p>
            @else
                <p class="tabular {{ Angka::nada((float) $selisih) }}">
                    {{ $selisih > 0 ? '+' : ($selisih < 0 ? '-' : '') }}{{ abs($selisih) }} poin
                    <span class="text-ink-faint">vs 7 hari sebelumnya</span>
                </p>
            @endif

            <p class="tabular text-ink-muted">
                {{ $metrik->complianceStreak }} trade patuh berturut-turut
            </p>

            <p class="text-xs text-ink-faint">
                Ambangmu {{ $metrik->threshold }}%. Pecahannya terhadap hasil ada di
                <a href="{{ route('reports') }}" class="text-accent-ink underline underline-offset-2" wire:navigate>Laporan</a>.
            </p>
        </div>
    </div>
</x-card>
