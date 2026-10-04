import defaultTheme from 'tailwindcss/defaultTheme';
import forms from '@tailwindcss/forms';

const token = (name) => `rgb(var(--${name}) / <alpha-value>)`;

/** @type {import('tailwindcss').Config} */
export default {
    content: [
        './vendor/laravel/framework/src/Illuminate/Pagination/resources/views/*.blade.php',
        './storage/framework/views/*.php',
        './resources/views/**/*.blade.php',
    ],

    theme: {
        extend: {
            // Sudut dibuat lebih lembut menyeluruh. 41 tempat memakai
            // `rounded-md`; menaikkan skalanya di sini mengubah semuanya
            // sekaligus tanpa menyentuh satu pun berkas Blade, dan menjaga
            // perbandingan antar tingkat tetap konsisten.
            borderRadius: {
                md: '0.625rem',
                lg: '0.875rem',
                xl: '1.125rem',
                '2xl': '1.5rem',
                '3xl': '2rem',
            },
            boxShadow: {
                // Bayangan mengambil warna latar, bukan hitam murni.
                soft: '0 1px 2px rgb(var(--shadow) / 0.06), 0 4px 12px rgb(var(--shadow) / 0.08)',
                lift: '0 2px 4px rgb(var(--shadow) / 0.08), 0 12px 32px rgb(var(--shadow) / 0.14)',
                glass: 'inset 0 1px 0 rgb(255 255 255 / 0.10), 0 8px 32px rgb(var(--shadow) / 0.16)',
            },
            fontFamily: {
                sans: ['Geist', ...defaultTheme.fontFamily.sans],
                mono: ['Geist Mono', ...defaultTheme.fontFamily.mono],
            },
            colors: {
                bg: token('bg'),
                surface: token('surface'),
                'surface-raised': token('surface-raised'),
                'surface-sunken': token('surface-sunken'),
                line: token('line'),
                'line-strong': token('line-strong'),
                ink: token('ink'),
                'ink-muted': token('ink-muted'),
                'ink-faint': token('ink-faint'),
                accent: token('accent'),
                // Merek sebagai TEKS. Isian memakai `accent`; di mode gelap
                // warna isian itu terlalu gelap untuk dibaca sebagai teks.
                'accent-ink': token('accent-ink'),
                'accent-hover': token('accent-hover'),
                'accent-contrast': token('accent-contrast'),
                'accent-soft': token('accent-soft'),
                warn: token('warn'),
                'warn-soft': token('warn-soft'),
                'warn-line': token('warn-line'),
                positive: token('positive'),
                negative: token('negative'),
                'viz-positive': token('viz-positive'),
                'viz-negative': token('viz-negative'),
                'viz-neutral': token('viz-neutral'),
            },
        },
    },

    plugins: [forms],
};
