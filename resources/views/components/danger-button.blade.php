<button {{ $attributes->merge(['type' => 'button', 'class' => 'press tombol-bahaya inline-flex items-center gap-2 rounded-full px-5 py-2.5 text-xs font-semibold uppercase tracking-widest text-accent-contrast focus:outline-none focus:ring-2 focus:ring-negative focus:ring-offset-2 focus:ring-offset-bg disabled:opacity-40 disabled:pointer-events-none']) }}>
    {{ $slot }}
</button>
