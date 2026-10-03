<?php

namespace Tests\Fixtures;

use App\Models\Concerns\BelongsToUser;
use Illuminate\Database\Eloquent\Model;

/**
 * Model fixture khusus pengujian.
 *
 * Milestone 1 membangun mekanisme kepemilikan data sebelum tabel domain
 * (setup, rules, trade) ada. Model ini memberi mekanisme itu sesuatu yang
 * nyata untuk diuji tanpa memalsukan fitur yang belum dibuat.
 *
 * Tabelnya dibuat ad hoc oleh DataIsolationTest di SQLite in-memory, jadi
 * tidak ada migrasi yang bisa menyentuh database MariaDB.
 */
class OwnedThing extends Model
{
    use BelongsToUser;

    protected $table = 'owned_things';

    protected $fillable = ['title'];
}
