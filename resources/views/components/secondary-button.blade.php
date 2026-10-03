<button {{ $attributes->merge(['type' => 'button', 'class' => 'inline-flex items-center px-4 py-2 bg-surface border border-line-strong rounded-md font-semibold text-xs text-ink-muted uppercase tracking-widest shadow-sm hover:bg-surface-sunken focus:outline-none focus:ring-2 focus:ring-accent focus:ring-offset-2 focus:ring-offset-bg disabled:opacity-25 transition ease-in-out duration-150']) }}>
    {{ $slot }}
</button>
