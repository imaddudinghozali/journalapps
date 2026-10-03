import Lenis from 'lenis';
import 'lenis/dist/lenis.css';

/*
|--------------------------------------------------------------------------
| Scroll berinersia - khusus halaman welcome
|--------------------------------------------------------------------------
|
| Bundle terpisah dari app.js dengan sengaja. Mengambil alih scroll bawaan
| peramban itu wajar di halaman pemasaran, tapi tidak di /trades: itu
| permukaan pengetikan harian, dan scroll yang terasa "berat" di sana cuma
| mengganggu. Halaman lain tidak pernah memuat berkas ini.
|
| Dua pintu keluar dipasang:
|  - prefers-reduced-motion: inersia tidak dinyalakan sama sekali.
|  - Lenis gagal dimuat: scroll bawaan peramban tetap berjalan seperti biasa,
|    karena tidak ada yang kita matikan sebelum Lenis benar-benar hidup.
|
*/

const DIAM = window.matchMedia('(prefers-reduced-motion: reduce)');

let lenis = null;

function nyalakan() {
    if (lenis !== null) {
        return;
    }

    lenis = new Lenis({
        // Sedikit lebih berat dari bawaan, tapi masih berhenti cepat saat
        // pengguna melepas scroll. Lebih dari ini mulai terasa seperti lag,
        // bukan seperti bobot.
        duration: 1.05,
        easing: (t) => Math.min(1, 1.001 - Math.pow(2, -10 * t)),
        // Sentuh dibiarkan memakai scroll asli perangkat. Inersia buatan di
        // atas inersia bawaan ponsel terasa salah dan merusak pull-to-refresh.
        smoothWheel: true,
        syncTouch: false,
    });

    const bingkai = (waktu) => {
        if (lenis === null) {
            return;
        }

        lenis.raf(waktu);
        requestAnimationFrame(bingkai);
    };

    requestAnimationFrame(bingkai);

    // scroll-behavior: smooth dari CSS adalah jaring pengaman untuk peramban
    // yang tidak pernah sampai ke baris ini. Setelah Lenis hidup, keduanya
    // saling berebut posisi scroll, jadi yang bawaan dimatikan.
    document.documentElement.style.scrollBehavior = 'auto';
}

function matikan() {
    if (lenis === null) {
        return;
    }

    lenis.destroy();
    lenis = null;

    document.documentElement.style.removeProperty('scroll-behavior');
}

// Tautan sesama halaman ikut dihaluskan; tanpa ini anchor melompat keras
// sementara scroll biasa meluncur, dan bedanya terasa seperti bug. Dipasang
// sekali di sini, bukan di dalam nyalakan(), supaya tidak bertumpuk kalau
// pengguna menyalakan dan mematikan preferensi gerakannya berulang kali.
document.querySelectorAll('a[href^="#"]').forEach((tautan) => {
    tautan.addEventListener('click', (e) => {
        // Saat Lenis mati, anchor dibiarkan kembali ke perilaku bawaan.
        if (lenis === null) {
            return;
        }

        const tujuan = document.querySelector(tautan.getAttribute('href'));

        if (tujuan === null) {
            return;
        }

        e.preventDefault();
        lenis.scrollTo(tujuan, { offset: -80 });
    });
});

if (! DIAM.matches) {
    nyalakan();
}

// Pengguna bisa mengubah preferensinya tanpa memuat ulang halaman.
DIAM.addEventListener('change', (e) => (e.matches ? matikan() : nyalakan()));
