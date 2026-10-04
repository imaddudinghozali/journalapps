@props(['active'])

{{--
    Kapsul, bukan garis bawah. Di bilah kaca, garis bawah menempel pada tepi
    bilahnya dan terbaca sebagai bagian dari bingkai, bukan sebagai penanda
    halaman. Kapsul mengambang di atas kaca - itu juga cara iOS menandai
    item terpilih.
--}}
@php
$classes = ($active ?? false)
 ? 'pil-nav pil-nav-aktif inline-flex items-center gap-2 rounded-full px-3.5 py-2 text-sm font-medium leading-5 text-ink focus:outline-none'
 : 'pil-nav inline-flex items-center gap-2 rounded-full px-3.5 py-2 text-sm font-medium leading-5 text-ink-faint hover:text-ink focus:outline-none focus:text-ink';
@endphp

<a {{ $attributes->merge(['class' => $classes]) }}>
    {{ $slot }}
</a>
