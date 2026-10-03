import { defineConfig } from 'vite';
import laravel from 'laravel-vite-plugin';

export default defineConfig({
    plugins: [
        laravel({
            // welcome.js berdiri sendiri supaya scroll berinersia hanya ikut
            // terunduh di halaman depan, bukan di setiap halaman aplikasi.
            input: ['resources/css/app.css', 'resources/js/app.js', 'resources/js/welcome.js'],
            refresh: true,
        }),
    ],
});
