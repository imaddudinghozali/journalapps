@props(['active'])

@php
$classes = ($active ?? false)
 ? 'block w-full ps-3 pe-4 py-2 border-l-4 border-accent text-start text-base font-medium text-accent bg-accent-soft focus:outline-none focus:text-accent focus:bg-accent-soft focus:border-accent transition duration-150 ease-in-out'
 : 'block w-full ps-3 pe-4 py-2 border-l-4 border-transparent text-start text-base font-medium text-ink-muted hover:text-ink hover:bg-surface-sunken hover:border-line-strong focus:outline-none focus:text-ink focus:bg-surface-sunken focus:border-line-strong transition duration-150 ease-in-out';
@endphp

<a {{ $attributes->merge(['class' => $classes]) }}>
 {{ $slot }}
</a>
