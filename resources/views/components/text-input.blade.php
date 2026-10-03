@props(['disabled' => false])

<input @disabled($disabled) {{ $attributes->merge(['class' => 'border-line-strong focus:border-accent focus:ring-accent rounded-md shadow-sm']) }}>
