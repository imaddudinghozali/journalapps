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

/*
|--------------------------------------------------------------------------
| Angka yang merayap naik
|--------------------------------------------------------------------------
|
| Dipakai sekali saat halaman dimuat, pada angka pokok KPI. Gunanya bukan
| hiasan: mata mengikuti sesuatu yang bergerak, jadi angka yang merayap naik
| menarik perhatian ke dirinya sendiri tepat saat orang pertama kali melihat
| layar.
|
| Nilai akhirnya SELALU dirender server lebih dulu sebagai isi elemen; Alpine
| cuma menimpanya. Jadi tanpa JavaScript - dan di dalam test, yang membaca
| HTML mentah - angkanya tetap benar dan tetap utuh.
|
| Di bawah prefers-reduced-motion angkanya langsung ditulis apa adanya, tanpa
| satu bingkai animasi pun.
*/
/*
|--------------------------------------------------------------------------
| Crosshair dan tooltip pada kurva
|--------------------------------------------------------------------------
|
| Titik terdekat dicari dari posisi kursor pada sumbu x saja, bukan jarak dua
| dimensi. Pada kurva, yang ingin dibaca orang selalu "apa yang terjadi pada
| tanggal ini" - bukan titik mana yang paling dekat secara geometri. Jarak dua
| dimensi membuat kursor di atas lembah melompat ke titik sebelahnya yang
| kebetulan lebih dekat secara vertikal.
|
| Koordinat titik datang dalam satuan viewBox SVG, sedangkan kursor dalam
| piksel layar. Keduanya dijembatani sekali di sini, bukan di dalam template.
*/
document.addEventListener('alpine:init', () => {
    window.Alpine.data('kurvaSorot', (titik, lebarViewBox) => ({
        aktif: null,
        garisX: 0,
        gayaTooltip: '',

        arahkan(e) {
            if (titik.length === 0) {
                return;
            }

            const kotak = this.$el.getBoundingClientRect();
            const xViewBox = ((e.clientX - kotak.left) / kotak.width) * lebarViewBox;

            let dekat = titik[0];
            let jarak = Infinity;

            for (const t of titik) {
                const d = Math.abs(t.x - xViewBox);

                if (d < jarak) {
                    jarak = d;
                    dekat = t;
                }
            }

            this.aktif = dekat;
            this.garisX = dekat.x;

            // Tooltip ditahan di dalam kartu: di dekat tepi kanan ia digeser
            // ke kiri, kalau tidak sebagiannya terpotong.
            const px = (dekat.x / lebarViewBox) * kotak.width;
            const lebarTooltip = 176;
            const kiri = Math.min(Math.max(px + 14, 0), Math.max(kotak.width - lebarTooltip, 0));

            this.gayaTooltip = `left:${kiri}px;top:4px`;
        },

        lepas() {
            this.aktif = null;
        },
    }));

    window.Alpine.data('angkaNaik', (tujuan, opsi = {}) => ({
        nilai: 0,

        init() {
            const akhir = Number(tujuan);

            if (! Number.isFinite(akhir)) {
                this.nilai = 0;

                return;
            }

            if (KURANGI_GERAK.matches) {
                this.nilai = akhir;

                return;
            }

            const durasi = opsi.durasi ?? 850;
            const mulai = performance.now();

            const bingkai = (sekarang) => {
                const maju = Math.min((sekarang - mulai) / durasi, 1);

                // Ease-out kubik: cepat di awal lalu melambat, sehingga
                // berhentinya terasa mendarat, bukan terpotong.
                this.nilai = akhir * (1 - Math.pow(1 - maju, 3));

                if (maju < 1) {
                    requestAnimationFrame(bingkai);
                } else {
                    this.nilai = akhir;
                }
            };

            requestAnimationFrame(bingkai);
        },

        get teks() {
            const desimal = opsi.desimal ?? 2;

            // en-US, bukan id-ID: seluruh angka lain di aplikasi ini dicetak
            // PHP dengan number_format bawaan. Dua gaya pemisah ribuan dalam
            // satu layar lebih buruk daripada satu gaya yang bukan lokal.
            const angka = Math.abs(this.nilai).toLocaleString('en-US', {
                minimumFractionDigits: desimal,
                maximumFractionDigits: desimal,
            });

            const tanda = opsi.tanda
                ? (this.nilai > 0 ? '+' : (this.nilai < 0 ? '-' : ''))
                : (this.nilai < 0 ? '-' : '');

            return tanda + (opsi.awalan ?? '') + angka + (opsi.akhiran ?? '');
        },
    }));
});
