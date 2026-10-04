@props(['active'])

@php
$classes = ($active ?? false)
 ? 'pil-nav pil-nav-aktif block w-full rounded-2xl px-4 py-3 text-start text-base font-medium text-ink focus:outline-none'
 : 'pil-nav block w-full rounded-2xl px-4 py-3 text-start text-base font-medium text-ink-muted hover:text-ink focus:outline-none focus:text-ink';
@endphp

<a {{ $attributes->merge(['class' => $classes]) }}>
    {{ $slot }}
</a>
