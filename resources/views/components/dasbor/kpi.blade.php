@props([
    'label',
    'nilai',
    'sub' => null,
    'nada' => 'text-ink',
    'angka' => null,
    'opsi' => '{}',
    'delay' => 0,
])

{{--
    Satu sel KPI. Angka pokoknya selalu dirender server; `angka` hanya
    menyalakan animasi merayap di atasnya, jadi tanpa JavaScript nilainya
    tetap terbaca utuh.
--}}
<x-card :label="$label" :delay="$delay">
    <x-slot:aksi>
        @isset($ikon)
            <span class="lencana-kpi grid h-8 w-8 shrink-0 place-items-center rounded-full bg-surface-sunken text-ink-muted">
                {{ $ikon }}
            </span>
        @endisset
    </x-slot:aksi>

    <div class="mt-3 text-2xl font-semibold tracking-tight tabular {{ $nada }}"
         @if ($angka !== null) x-data="angkaNaik({{ $angka }}, {{ $opsi }})" x-text="teks" @endif>{{ $nilai }}</div>

    @isset($sub)
        <div class="mt-1 text-xs text-ink-faint">{{ $sub }}</div>
    @endisset

    {{ $slot }}
</x-card>
