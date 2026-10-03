<?php

namespace App\Models\Scopes;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Scope;
use Illuminate\Support\Facades\Auth;
use RuntimeException;

/**
 * Membatasi setiap query model pemilik ke pengguna yang sedang masuk.
 *
 * Isolasi diletakkan di satu tempat, bukan disebar sebagai where() manual di
 * controller: satu query yang lupa difilter sudah cukup untuk membocorkan data
 * finansial pengguna lain.
 */
class OwnedByUserScope implements Scope
{
    public function apply(Builder $builder, Model $model): void
    {
        $userId = Auth::id();

        if ($userId === null) {
            throw new RuntimeException(sprintf(
                'Query terhadap [%s] dijalankan tanpa pengguna terautentikasi. '
                .'Pada konteks CLI, queue job, atau seeder, lepaskan scope secara '
                .'eksplisit dengan withoutGlobalScope(%s::class).',
                $model::class,
                self::class,
            ));
        }

        $builder->where($model->qualifyColumn('user_id'), $userId);
    }
}
