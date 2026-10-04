import './bootstrap';

/*
|--------------------------------------------------------------------------
| Sorot spekular pada permukaan kaca
|--------------------------------------------------------------------------
|
| Kaca sungguhan memantulkan sumber cahaya, dan pantulan itu bergerak saat
| sudut pandang berubah. Di layar, penggantinya adalah kursor: satu sorot
| lembut yang mengikutinya membuat permukaannya terbaca sebagai benda, bukan
| sebagai gambar permukaan.
|
| Hanya menulis dua properti kustom. Tidak menyentuh layout, jadi tidak ada
| reflow, dan pembacaan getBoundingClientRect dibatasi satu kali per elemen
| per gerakan.
|
| Didelegasikan ke document, bukan dipasang per elemen: Livewire mengganti
| potongan DOM setiap kali komponen dirender ulang, dan listener yang
| menempel pada elemen lama ikut hilang bersamanya.
*/
const KURANGI_GERAK = window.matchMedia('(prefers-reduced-motion: reduce)');

function pasangSorot() {
    document.addEventListener('pointermove', (e) => {
        const kaca = e.target instanceof Element
            ? e.target.closest('.kaca, .glass')
            : null;

        if (kaca === null) {
            return;
        }

        const kotak = kaca.getBoundingClientRect();
        kaca.style.setProperty('--mx', `${e.clientX - kotak.left}px`);
        kaca.style.setProperty('--my', `${e.clientY - kotak.top}px`);
    }, { passive: true });

    // Sorot yang tertinggal di posisi terakhir setelah kursor pergi terbaca
    // sebagai noda, bukan sebagai pantulan.
    document.addEventListener('pointerleave', (e) => {
        const kaca = e.target instanceof Element
            ? e.target.closest('.kaca, .glass')
            : null;

        if (kaca === null) {
            return;
        }

        kaca.style.removeProperty('--mx');
        kaca.style.removeProperty('--my');
    }, { capture: true, passive: true });
}

if (! KURANGI_GERAK.matches) {
    pasangSorot();
}
