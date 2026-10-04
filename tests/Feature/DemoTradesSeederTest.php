<?php

use App\Models\Scopes\OwnedByUserScope;
use App\Models\Trade;
use App\Models\TradingSetup;
use App\Models\User;
use App\Support\TradeMath;
use Database\Seeders\DemoTradesSeeder;

/*
|--------------------------------------------------------------------------
| Seeder data demo
|--------------------------------------------------------------------------
|
| Data demo yang tidak realistis lebih berbahaya daripada tidak ada data:
| dashboard akan terlihat bagus di layar pengembang dan berantakan begitu
| ketemu angka sungguhan. Test di bawah menegakkan bentuk distribusinya,
| bukan sekadar "ada barisnya".
|
| Yang paling penting: risk_amount dan pnl_amount TIDAK ditulis langsung.
| Keduanya diturunkan dari harga lewat TradeMath, jalur yang sama persis
| dengan form pencatatan. Seeder yang menulis angka turunan secara manual
| bisa menghasilkan trade yang angkanya tidak cocok dengan harganya sendiri -
| persis kelas bug yang dicegah aturan "jangan jadikan fillable".
|
*/

function seedDemo(User $untuk): void
{
    (new DemoTradesSeeder)->untukPengguna($untuk)->run();
}

function tradeDemo(User $milik)
{
    return Trade::withoutGlobalScope(OwnedByUserScope::class)
        ->where('user_id', $milik->id)
        ->get();
}

it('menolak berjalan di lingkungan produksi', function () {
    $u = User::factory()->create();

    app()->detectEnvironment(fn () => 'production');

    expect(fn () => seedDemo($u))->toThrow(RuntimeException::class)
        ->and(tradeDemo($u))->toHaveCount(0);
});

it('membuat 40 sampai 50 trade yang semuanya sudah tertutup', function () {
    $u = User::factory()->create();
    seedDemo($u);

    $trades = tradeDemo($u);

    expect($trades->count())->toBeGreaterThanOrEqual(40)
        ->and($trades->count())->toBeLessThanOrEqual(50)
        ->and($trades->whereNull('closed_at'))->toHaveCount(0)
        ->and($trades->whereNull('pnl_amount'))->toHaveCount(0);
});

it('menurunkan risiko dan hasil dari harga, bukan menuliskannya', function () {
    $u = User::factory()->create();
    seedDemo($u);

    tradeDemo($u)->each(function (Trade $t) {
        $risiko = TradeMath::risk(
            (float) $t->lot_size, (float) $t->entry_price,
            (float) $t->stop_price, (float) $t->contract_size,
        );
        $hasil = TradeMath::pnl(
            $t->direction, (float) $t->lot_size, (float) $t->entry_price,
            (float) $t->exit_price, (float) $t->contract_size,
        );

        expect((float) $t->risk_amount)->toEqualWithDelta($risiko, 0.01)
            ->and((float) $t->pnl_amount)->toEqualWithDelta($hasil, 0.01);
    });
});

it('menghasilkan win rate antara 45 dan 60 persen', function () {
    $u = User::factory()->create();
    seedDemo($u);

    $trades = tradeDemo($u);
    $menang = $trades->filter(fn (Trade $t) => (float) $t->pnl_amount > 0)->count();

    expect($menang / $trades->count() * 100)
        ->toBeGreaterThanOrEqual(45.0)
        ->toBeLessThanOrEqual(60.0);
});

it('membuat rata-rata menang lebih besar daripada rata-rata kalah', function () {
    $u = User::factory()->create();
    seedDemo($u);

    $r = tradeDemo($u)->map(fn (Trade $t) => (float) $t->pnl_amount / (float) $t->risk_amount);

    $menang = $r->filter(fn ($x) => $x > 0)->avg();
    $kalah = abs($r->filter(fn ($x) => $x < 0)->avg());

    expect($menang)->toBeGreaterThanOrEqual(1.5)->toBeLessThanOrEqual(2.2)
        ->and($kalah)->toBeGreaterThanOrEqual(0.8)->toBeLessThanOrEqual(1.3);
});

it('membuat trade patuh berkinerja lebih baik daripada yang melanggar', function () {
    $u = User::factory()->create();
    seedDemo($u);

    $ambang = $u->fresh()->compliance_threshold;

    $rata = fn ($trades) => $trades
        ->map(fn (Trade $t) => (float) $t->pnl_amount / (float) $t->risk_amount)
        ->avg();

    $trades = tradeDemo($u)->whereNotNull('compliance_score');
    $patuh = $trades->filter(fn (Trade $t) => $t->compliance_score >= $ambang);
    $melanggar = $trades->filter(fn (Trade $t) => $t->compliance_score < $ambang);

    // Inti seluruh dashboard: korelasinya harus terlihat mata, bukan sekadar
    // ada. Dua kelompok juga harus cukup besar untuk lolos MIN_SAMPLE.
    expect($patuh->count())->toBeGreaterThanOrEqual(10)
        ->and($melanggar->count())->toBeGreaterThanOrEqual(10)
        ->and($rata($patuh))->toBeGreaterThan($rata($melanggar));
});

it('menyebarkan trade di 30 sampai 60 hari terakhir dan menyisakan hari kosong', function () {
    $u = User::factory()->create();
    seedDemo($u);

    $trades = tradeDemo($u);
    $tanggal = $trades->map(fn (Trade $t) => $t->closed_at->toDateString())->unique();
    $rentang = $trades->min('opened_at')->diffInDays(now());

    expect($rentang)->toBeGreaterThanOrEqual(29)->toBeLessThanOrEqual(61)
        // Hari unik lebih sedikit daripada rentangnya berarti ada hari kosong.
        ->and($tanggal->count())->toBeLessThan(30);
});

it('memberi tiap trade setup, checklist, dan skor yang saling cocok', function () {
    $u = User::factory()->create();
    seedDemo($u);

    $setup = TradingSetup::withoutGlobalScope(OwnedByUserScope::class)
        ->where('user_id', $u->id)->get();

    expect($setup->count())->toBeGreaterThanOrEqual(2);

    tradeDemo($u)->each(function (Trade $t) {
        $checks = $t->ruleChecks()->withoutGlobalScope(OwnedByUserScope::class)->get();

        expect($checks)->not->toBeEmpty()
            ->and($t->compliance_score)->not->toBeNull()
            ->and($t->compliance_score)->toBeGreaterThanOrEqual(0)
            ->and($t->compliance_score)->toBeLessThanOrEqual(100);

        // Snapshot wajib terisi: laporan membacanya, bukan relasi rule-nya.
        $checks->each(fn ($c) => expect($c->rule_label)->not->toBeEmpty());
    });
});

it('tidak menyentuh data pengguna lain', function () {
    $alice = User::factory()->create();
    $bob = User::factory()->create();

    seedDemo($bob);

    expect(tradeDemo($alice))->toHaveCount(0)
        ->and(TradingSetup::withoutGlobalScope(OwnedByUserScope::class)
            ->where('user_id', $alice->id)->count())->toBe(0);
});

it('menghasilkan data yang sama persis setiap kali dijalankan', function () {
    $a = User::factory()->create();
    $b = User::factory()->create();

    seedDemo($a);
    seedDemo($b);

    // Benih tetap. Tanpa ini, satu kali seed bisa kebetulan menghasilkan
    // distribusi yang lolos test dan kali berikutnya tidak - dan kegagalannya
    // akan terbaca sebagai bug di tempat lain.
    $hasil = fn (User $u) => tradeDemo($u)
        ->map(fn (Trade $t) => (float) $t->pnl_amount)
        ->values()
        ->all();

    expect($hasil($a))->toBe($hasil($b));
});
