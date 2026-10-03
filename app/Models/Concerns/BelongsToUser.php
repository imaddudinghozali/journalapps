<?php

namespace App\Models\Concerns;

use App\Models\Scopes\OwnedByUserScope;
use App\Models\User;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Auth;
use RuntimeException;

/**
 * Menjadikan sebuah model milik satu pengguna.
 *
 * Setiap model domain yang menyimpan data pribadi pengguna (setup, rules,
 * trade) wajib memakai trait ini. Dengan begitu kepemilikan tidak perlu
 * diingat ulang di setiap controller.
 *
 * Tabel model pemakai harus memiliki kolom user_id.
 */
trait BelongsToUser
{
    public static function bootBelongsToUser(): void
    {
        static::addGlobalScope(new OwnedByUserScope);

        static::creating(function (self $model): void {
            $pemilikEksplisit = $model->getAttribute('user_id');
            $penggunaSaatIni = Auth::id();

            if ($pemilikEksplisit === null) {
                if ($penggunaSaatIni === null) {
                    throw new RuntimeException(sprintf(
                        'Pembuatan [%s] tanpa pengguna terautentikasi dan tanpa user_id '
                        .'eksplisit. Isi user_id secara eksplisit pada konteks CLI, queue '
                        .'job, atau seeder.',
                        $model::class,
                    ));
                }

                $model->setAttribute('user_id', $penggunaSaatIni);

                return;
            }

            // user_id eksplisit hanya sah di luar konteks request. Di dalam request,
            // nilai yang berbeda dari pengguna yang masuk berarti percobaan membuat
            // record atas nama orang lain — termasuk lewat mass assignment.
            if ($penggunaSaatIni !== null && (int) $pemilikEksplisit !== (int) $penggunaSaatIni) {
                throw new RuntimeException(sprintf(
                    'Penolakan pembuatan [%s] atas nama pengguna lain: user_id eksplisit '
                    .'tidak cocok dengan pengguna yang sedang masuk.',
                    $model::class,
                ));
            }
        });

        static::updating(function (self $model): void {
            if ($model->isDirty('user_id')) {
                throw new RuntimeException(sprintf(
                    'Kepemilikan [%s] tidak boleh dipindahkan ke pengguna lain.',
                    $model::class,
                ));
            }
        });
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
