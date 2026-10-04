@props([
    'label' => null,
    'tone' => null,
    'delay' => 0,
])

{{--
    Dasar semua kartu dashboard.

    Ada supaya gaya dan interaksinya hidup di SATU tempat. Begitu kartu
    disalin-tempel, hover dan jeda masuknya akan menyimpang satu per satu
    sampai tidak ada dua kartu yang berperilaku sama.

    Materialnya tetap kaca, bukan permukaan rata. Yang diubah dari spesifikasi
    hanya itu, atas keputusan pemilik produk.

    `delay` memberi jeda masuk bertahap. Nilainya ditulis sebagai properti
    kustom, bukan kelas Tailwind, karena jeda yang dihitung berurutan tidak
    bisa dipindai Tailwind dari berkas Blade.
--}}
<div
    {{ $attributes->class([
        'kartu kaca panel group relative p-5',
        'border-l-2' => $tone !== null,
        $tone => $tone !== null,
    ]) }}
    @style(['--jeda-masuk: '.$delay.'ms'])
>
    @isset($label)
        <div class="flex items-start justify-between gap-3">
            <span class="text-xs font-medium uppercase tracking-wider text-ink-muted">{{ $label }}</span>
            @isset($aksi)
                {{ $aksi }}
            @endisset
        </div>
    @endisset

    {{ $slot }}
</div>
