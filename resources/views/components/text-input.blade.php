@props(['disabled' => false])

{{--
    Tembus pandang, bukan permukaan padat. Kolom isian yang padat di atas
    panel kaca terbaca sebagai tempelan; yang tembus pandang terbaca sebagai
    cekungan pada kacanya.
--}}
<input @disabled($disabled) {{ $attributes->merge(['class' => 'rounded-xl focus:ring-2 focus:ring-accent focus:ring-offset-0']) }}>
