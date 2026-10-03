<?php

use App\Models\Scopes\OwnedByUserScope;
use App\Models\User;
use App\Policies\OwnedRecordPolicy;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Schema;
use Tests\Fixtures\OwnedThing;

/*
|--------------------------------------------------------------------------
| Isolasi data antar pengguna
|--------------------------------------------------------------------------
|
| PRD menilai kebocoran data jurnal dan P&L antar akun sebagai dampak
| Kritis, sehingga mekanisme kepemilikan dibangun sebagai infrastruktur di
| milestone 1 — sebelum tabel domain pertama ada.
|
| Tabel fixture dibuat di sini, bukan lewat migrasi, agar tidak ada
| kemungkinan tabel pengujian terbentuk di database MariaDB.
|
*/

beforeEach(function () {
    Schema::create('owned_things', function (Blueprint $table) {
        $table->id();
        $table->foreignId('user_id')->constrained()->cascadeOnDelete();
        $table->string('title');
        $table->timestamps();
    });

    Gate::policy(OwnedThing::class, OwnedRecordPolicy::class);
});

/** Ambil record lintas pemilik untuk keperluan arrange, menembus global scope. */
function unscopedFind(int $id): ?OwnedThing
{
    return OwnedThing::withoutGlobalScope(OwnedByUserScope::class)->find($id);
}

it('tidak menampilkan record milik pengguna lain pada query koleksi', function () {
    $alice = User::factory()->create();
    $bob = User::factory()->create();

    $this->actingAs($alice);
    OwnedThing::create(['title' => 'Punya Alice']);

    $this->actingAs($bob);
    OwnedThing::create(['title' => 'Punya Bob']);

    $terlihatBob = OwnedThing::all();

    expect($terlihatBob)->toHaveCount(1)
        ->and($terlihatBob->first()->title)->toBe('Punya Bob');
});

it('tidak menemukan record pengguna lain lewat ID langsung', function () {
    $alice = User::factory()->create();
    $bob = User::factory()->create();

    $this->actingAs($alice);
    $idAlice = OwnedThing::create(['title' => 'Punya Alice'])->id;

    $this->actingAs($bob);

    // Null, bukan 403: keberadaan record milik orang lain pun tidak dibocorkan.
    expect(OwnedThing::find($idAlice))->toBeNull();
});

it('menolak pengguna lain mengubah atau menghapus record', function () {
    $alice = User::factory()->create();
    $bob = User::factory()->create();

    $this->actingAs($alice);
    $milikAlice = unscopedFind(OwnedThing::create(['title' => 'Punya Alice'])->id);

    expect($bob->can('view', $milikAlice))->toBeFalse()
        ->and($bob->can('update', $milikAlice))->toBeFalse()
        ->and($bob->can('delete', $milikAlice))->toBeFalse()
        ->and($alice->can('update', $milikAlice))->toBeTrue()
        ->and($alice->can('delete', $milikAlice))->toBeTrue();
});

it('mengisi user_id otomatis dari pengguna yang sedang masuk', function () {
    $alice = User::factory()->create();

    $this->actingAs($alice);
    $thing = OwnedThing::create(['title' => 'Tanpa user_id eksplisit']);

    expect($thing->user_id)->toBe($alice->id)
        ->and($thing->user)->not->toBeNull()
        ->and($thing->user->id)->toBe($alice->id);
});

it('melempar exception ketika query dijalankan tanpa pengguna terautentikasi', function () {
    $alice = User::factory()->create();

    $this->actingAs($alice);
    OwnedThing::create(['title' => 'Punya Alice']);

    auth()->logout();

    // Gagal keras lebih baik daripada mengembalikan hasil kosong, yang
    // menyembunyikan bug menjadi "data hilang tanpa sebab".
    expect(fn () => OwnedThing::all())->toThrow(RuntimeException::class);
});

it('tidak menyentuh baris pengguna lain pada mass update', function () {
    $alice = User::factory()->create();
    $bob = User::factory()->create();

    $this->actingAs($alice);
    $idAlice = OwnedThing::create(['title' => 'Punya Alice'])->id;

    $this->actingAs($bob);
    OwnedThing::create(['title' => 'Punya Bob']);
    $terdampak = OwnedThing::query()->update(['title' => 'Ditimpa Bob']);

    expect($terdampak)->toBe(1)
        ->and(unscopedFind($idAlice)->title)->toBe('Punya Alice');
});

it('tidak menyentuh baris pengguna lain pada mass delete', function () {
    $alice = User::factory()->create();
    $bob = User::factory()->create();

    $this->actingAs($alice);
    $idAlice = OwnedThing::create(['title' => 'Punya Alice'])->id;

    $this->actingAs($bob);
    OwnedThing::create(['title' => 'Punya Bob']);
    $terhapus = OwnedThing::query()->delete();

    expect($terhapus)->toBe(1)
        ->and(unscopedFind($idAlice))->not->toBeNull();
});

it('menolak pembuatan record atas nama pengguna lain', function () {
    $alice = User::factory()->create();
    $bob = User::factory()->create();

    $this->actingAs($bob);

    // Jalur serang nyata: user_id masuk lewat mass assignment pada model
    // domain yang keliru menaruhnya di $fillable.
    expect(fn () => OwnedThing::create(['title' => 'Disusupkan', 'user_id' => $alice->id]))
        ->toThrow(RuntimeException::class);
});

it('menolak perpindahan kepemilikan record', function () {
    $alice = User::factory()->create();
    $bob = User::factory()->create();

    $this->actingAs($alice);
    $thing = OwnedThing::create(['title' => 'Punya Alice']);

    $thing->user_id = $bob->id;

    expect(fn () => $thing->save())->toThrow(RuntimeException::class);
});

it('melempar exception saat membuat record tanpa pengguna dan tanpa user_id', function () {
    expect(fn () => OwnedThing::create(['title' => 'Tanpa pemilik']))
        ->toThrow(RuntimeException::class);
});

it('mempertahankan user_id eksplisit pada konteks tanpa autentikasi', function () {
    $alice = User::factory()->create();

    // Jalur resmi untuk seeder, queue job, dan perintah artisan.
    $thing = new OwnedThing(['title' => 'Dibuat seeder']);
    $thing->user_id = $alice->id;
    $thing->save();

    expect($thing->user_id)->toBe($alice->id);
});

it('mengizinkan pelepasan scope secara eksplisit tanpa autentikasi', function () {
    $alice = User::factory()->create();

    $this->actingAs($alice);
    OwnedThing::create(['title' => 'Punya Alice']);

    auth()->logout();

    $semua = OwnedThing::withoutGlobalScope(OwnedByUserScope::class)->get();

    expect($semua)->toHaveCount(1);
});

it('mengizinkan pengguna terautentikasi melihat daftar dan membuat record miliknya', function () {
    $alice = User::factory()->create();

    // viewAny dan create tidak dibatasi kepemilikan — daftar sudah dipersempit
    // global scope, dan hook creating yang menentukan pemiliknya.
    expect($alice->can('viewAny', OwnedThing::class))->toBeTrue()
        ->and($alice->can('create', OwnedThing::class))->toBeTrue();
});

it('mengarahkan pengguna yang belum masuk ke halaman login', function () {
    $this->get('/dashboard')->assertRedirect('/login');
});
