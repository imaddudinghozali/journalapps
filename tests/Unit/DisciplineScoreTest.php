<?php

use App\Support\DisciplineScore;
use App\Support\TradeStatistics;

function skorSeragam(int $berapa, int $nilai = 100): array
{
    return array_fill(0, $berapa, $nilai);
}

it('menahan nilainya sampai sampelnya cukup', function () {
    $s = DisciplineScore::from(skorSeragam(TradeStatistics::MIN_SAMPLE - 1), 2.5, 70.0);

    // Sumbunya tetap dihitung supaya radar bisa digambar sebagai pratinjau,
    // tapi angka tunggalnya ditahan.
    expect($s->value)->toBeNull()
        ->and($s->hasEnoughSample())->toBeFalse()
        ->and($s->axes['kepatuhan'])->toBe(100.0);
});

it('memberi 100 hanya ketika keempat sumbunya penuh', function () {
    $s = DisciplineScore::from(skorSeragam(10), 2.5, 70.0);

    expect($s->value)->toBe(100)
        ->and($s->hasEnoughSample())->toBeTrue();
});

it('menempatkan profit factor impas tepat di tengah sumbunya', function () {
    // PF 1,0 berarti untung dan rugi berimbang. Pemetaan linear dari nol akan
    // memberi 40 pada trader yang sebenarnya impas.
    expect(DisciplineScore::from(skorSeragam(10), 1.0, 70.0)->axes['profitFactor'])->toBe(50.0);
});

it('membatasi profit factor dan win rate di atas pita terbaiknya', function () {
    $a = DisciplineScore::from(skorSeragam(10), 9.9, 99.0);
    $b = DisciplineScore::from(skorSeragam(10), 2.5, 70.0);

    expect($a->axes['profitFactor'])->toBe($b->axes['profitFactor'])
        ->and($a->axes['winRate'])->toBe($b->axes['winRate']);
});

it('memberi konsistensi penuh pada kepatuhan yang tidak berayun', function () {
    expect(DisciplineScore::from(array_fill(0, 10, 80), 1.5, 50.0)->axes['konsistensi'])->toBe(100.0);
});

it('menurunkan konsistensi ketika kepatuhan berayun jauh', function () {
    $stabil = DisciplineScore::from(array_fill(0, 10, 80), 1.5, 50.0);
    $berayun = DisciplineScore::from(
        array_merge(array_fill(0, 5, 30), array_fill(0, 5, 100)), 1.5, 50.0,
    );

    // Rata-ratanya mirip, tapi yang berayun jelas kurang disiplin.
    expect($berayun->axes['konsistensi'])->toBeLessThan($stabil->axes['konsistensi'])
        ->and($berayun->value)->toBeLessThan($stabil->value);
});

it('tidak memberi konsistensi penuh pada satu trade', function () {
    // Satu trade tidak punya sebaran. Konsistensinya tidak diketahui, bukan
    // sempurna - memberi nilai penuh akan menghadiahi orang yang baru mulai.
    expect(DisciplineScore::from([90], 1.5, 50.0)->axes['konsistensi'])->toBe(0.0);
});

it('tidak pecah tanpa data sama sekali', function () {
    $s = DisciplineScore::from([], null, null);

    expect($s->value)->toBeNull()
        ->and($s->axes['kepatuhan'])->toBe(0.0)
        ->and($s->axes['profitFactor'])->toBe(0.0)
        ->and($s->axes['winRate'])->toBe(0.0);
});

it('memberi bobot kepatuhan paling besar di antara keempat sumbu', function () {
    expect(DisciplineScore::BOBOT['kepatuhan'])->toBeGreaterThan(DisciplineScore::BOBOT['profitFactor'])
        ->and(DisciplineScore::BOBOT['profitFactor'])->toBeGreaterThan(DisciplineScore::BOBOT['konsistensi'])
        ->and(DisciplineScore::BOBOT['konsistensi'])->toBeGreaterThan(DisciplineScore::BOBOT['winRate'])
        ->and(array_sum(DisciplineScore::BOBOT))->toBe(100);
});
