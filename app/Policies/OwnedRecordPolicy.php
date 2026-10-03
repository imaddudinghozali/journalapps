<?php

namespace App\Policies;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;

/**
 * Policy dasar untuk record milik pengguna.
 *
 * Global scope sudah menyembunyikan record pengguna lain dari query biasa.
 * Policy ini adalah lapisan kedua, untuk kasus ketika sebuah instance model
 * sudah dipegang — misalnya hasil withoutGlobalScope, route binding, atau
 * relasi — sehingga otorisasi tetap diperiksa.
 *
 * Policy domain di milestone berikutnya mewarisi kelas ini.
 */
class OwnedRecordPolicy
{
    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(User $user, Model $record): bool
    {
        return $this->owns($user, $record);
    }

    public function create(User $user): bool
    {
        return true;
    }

    public function update(User $user, Model $record): bool
    {
        return $this->owns($user, $record);
    }

    public function delete(User $user, Model $record): bool
    {
        return $this->owns($user, $record);
    }

    protected function owns(User $user, Model $record): bool
    {
        $ownerId = $record->getAttribute('user_id');

        return $ownerId !== null && (int) $ownerId === (int) $user->getKey();
    }
}
