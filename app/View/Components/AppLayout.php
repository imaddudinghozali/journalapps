<?php

namespace App\View\Components;

use Illuminate\View\Component;
use Illuminate\View\View;

class AppLayout extends Component
{
    /**
     * Judul dan deskripsi per halaman.
     *
     * Keduanya opsional supaya halaman yang belum mengisinya tidak pecah, tapi
     * tests/Feature/PageMetaTest.php menegakkan bahwa tidak ada dua halaman
     * yang berjudul sama - judul seragam membuat riwayat peramban, bookmark,
     * dan daftar tab tidak bisa dibedakan satu sama lain.
     */
    public function __construct(
        public ?string $title = null,
        public ?string $description = null,
    ) {}

    /**
     * Get the view / contents that represents the component.
     */
    public function render(): View
    {
        return view('layouts.app');
    }
}
