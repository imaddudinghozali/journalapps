<button {{ $attributes->merge(['type' => 'submit', 'class' => 'press inline-flex items-center px-4 py-2 bg-negative border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-negative active:bg-negative focus:outline-none focus:ring-2 focus:ring-negative focus:ring-offset-2 focus:ring-offset-bg transition ease-in-out duration-150']) }}>
    {{ $slot }}
</button>
