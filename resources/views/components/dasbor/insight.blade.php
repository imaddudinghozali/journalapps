@props(['metrik'])

@php
    use App\Support\Angka;

    [$ada, $butuh] = $metrik->insightKemajuan();
    $persen = $butuh > 0 ? min(100, (int) round($ada / $butuh * 100)) : 0;
@endphp

@if ($metrik->insightSiap())
    @php
        $patuh = $metrik->compliance->compliant->expectancy;
        $langgar = $metrik->compliance->nonCompliant->expectancy;
    @endphp

    <p class="text-base text-ink">
        Trade patuh rata-rata
        <span class="font-semibold tabular {{ Angka::nada($patuh) }}">{{ Angka::r($patuh) }}</span>,
        yang melanggar
        <span class="font-semibold tabular {{ Angka::nada($langgar) }}">{{ Angka::r($langgar) }}</span>.
        <span class="text-ink-faint">Ini kaitan, bukan sebab-akibat.</span>
    </p>
@else
    {{-- Angka ditahan sampai kedua kelompok cukup. Yang ditampilkan kemajuannya,
         bukan angka setengah matang yang terbaca seolah temuan. --}}
    <div class="space-y-2">
        <p class="text-sm text-ink-muted">
            {{ $ada }}/{{ $butuh }} trade menuju perbandingan patuh vs melanggar.
        </p>
        <div class="h-1.5 w-full max-w-sm overflow-hidden rounded-full bg-surface-sunken">
            <div class="h-full rounded-full bg-accent transition-[width] duration-500" style="width: {{ $persen }}%"></div>
        </div>
    </div>
@endif
