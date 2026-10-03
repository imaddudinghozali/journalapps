<?php

namespace App\Providers;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // Di luar produksi: lazy loading dan atribut hilang dijadikan exception
        // agar bug terdeteksi saat menulis kode, bukan setelah tampil sebagai
        // data yang salah di layar pengguna.
        //
        // Berlaku untuk local DAN testing — kalau strict hanya aktif di local,
        // test justru tidak menangkap kelas bug yang strict mode ada untuk
        // menangkapnya.
        Model::shouldBeStrict(! $this->app->isProduction());
    }
}
