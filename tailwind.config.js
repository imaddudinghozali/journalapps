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
            fontFamily: {
                sans: ['Figtree', ...defaultTheme.fontFamily.sans],
            },
            colors: {
                bg: token('bg'),
                surface: token('surface'),
                'surface-sunken': token('surface-sunken'),
                line: token('line'),
                'line-strong': token('line-strong'),
                ink: token('ink'),
                'ink-muted': token('ink-muted'),
                'ink-faint': token('ink-faint'),
                accent: token('accent'),
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
