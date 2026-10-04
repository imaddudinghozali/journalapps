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

    wire:key MEMUAT nilainya, dan itu bukan hiasan.

    x-data menangkap target animasinya sekali saat Alpine init. Livewire
    me-morph elemen yang sudah ada alih-alih menggantinya, jadi tanpa kunci
    yang ikut berubah Alpine tidak pernah init ulang: x-text terus menimpa
    angka baru dari server dengan angka lama. Akibatnya kartu mencetak angka
    periode yang bukan periode terpilih - bukan sekadar animasi yang tidak
    jalan, tapi angka yang salah.
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
         @if ($angka !== null)
             wire:key="kpi-{{ \Illuminate\Support\Str::slug($label) }}-{{ $angka }}"
             x-data="angkaNaik({{ $angka }}, {{ $opsi }})"
             x-text="teks"
         @endif>{{ $nilai }}</div>

    @isset($sub)
        <div class="mt-1 text-xs text-ink-faint">{{ $sub }}</div>
    @endisset

    {{ $slot }}
</x-card>
