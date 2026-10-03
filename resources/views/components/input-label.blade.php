@props(['value'])

<label {{ $attributes->merge(['class' => 'block font-medium text-sm text-ink-muted']) }}>
    {{ $value ?? $slot }}
</label>
