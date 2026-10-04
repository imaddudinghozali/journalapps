<button {{ $attributes->merge(['type' => 'button', 'class' => 'press kaca inline-flex items-center gap-2 rounded-full px-4 py-2 text-xs font-semibold uppercase tracking-widest text-ink-muted hover:text-ink focus:outline-none focus:ring-2 focus:ring-accent focus:ring-offset-2 focus:ring-offset-bg disabled:opacity-40 disabled:pointer-events-none']) }}>
    {{ $slot }}
</button>
