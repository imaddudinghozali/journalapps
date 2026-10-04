<?php

namespace Database\Seeders;

use App\Models\Instrument;
use App\Models\Scopes\OwnedByUserScope;
use App\Models\SetupRule;
use App\Models\Trade;
use App\Models\TradeRuleCheck;
use App\Models\TradingSetup;
use App\Models\User;
use App\Support\ComplianceScore;
use App\Support\TradeMath;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;
use RuntimeException;

/**
 * Data demo untuk memeriksa tampilan dashboard.
 *
 * TIDAK didaftarkan di DatabaseSeeder, jadi `php artisan db:seed` polos tidak
 * akan menjalankannya tanpa sengaja. Jalankan secara eksplisit:
 *
 *     php artisan db:seed --class=DemoTradesSeeder
 *
 * Tiga hal yang membuat seeder ini berbeda dari pengisi baris biasa:
 *
 * 1. Angka turunan benar-benar diturunkan. risk_amount dan pnl_amount
 *    dihitung TradeMath dari harga, lewat jalur yang sama dengan form
 *    pencatatan. Menuliskannya langsung akan menghasilkan trade yang angkanya
 *    tidak cocok dengan harganya sendiri, dan dashboard akan tampak benar di
 *    atas data yang sebenarnya mustahil.
 *
 * 2. Skor kepatuhan dihitung ComplianceScore dari checklist yang benar-benar
 *    dibuat, bukan diacak terpisah. Kalau skornya diacak sendiri, checklist
 *    dan skornya saling bertentangan dan halaman Laporan akan menampilkan
 *    korelasi yang tidak ada di datanya.
 *
 * 3. Hasil dikondisikan pada kepatuhan, bukan sebaliknya. Kepatuhan diputuskan
 *    lebih dulu, lalu hasilnya diambil dari distribusi yang berbeda untuk tiap
 *    kelompok. Itulah pola yang seharusnya terlihat di dashboard; kalau pola
 *    itu tidak ada di data demo, tidak ada yang bisa dinilai dari tampilannya.
 *
 * Global scope dilepas di seluruh kueri dan user_id diisi eksplisit: seeder
 * berjalan tanpa pengguna terautentikasi, dan OwnedByUserScope sengaja
 * melempar exception dalam keadaan itu.
 */
class DemoTradesSeeder extends Seeder
{
    /** Benih tetap supaya distribusinya bisa diuji dan bisa diulang. */
    private const BENIH = 20261004;

    private const JUMLAH_TRADE = 46;

    private const HARI_KE_BELAKANG = 52;

    private ?User $pengguna = null;

    private int $acakan = self::BENIH;

    public function untukPengguna(User $pengguna): self
    {
        $this->pengguna = $pengguna;

        return $this;
    }

    public function run(): void
    {
        if (app()->isProduction()) {
            throw new RuntimeException(
                'DemoTradesSeeder berisi data karangan dan tidak boleh berjalan di produksi.'
            );
        }

        $pengguna = $this->pengguna ?? User::withoutGlobalScopes()->latest('id')->first();

        if ($pengguna === null) {
            throw new RuntimeException('Tidak ada pengguna untuk diisi data demo. Daftar dulu.');
        }

        $instrumen = $this->instrumen($pengguna);
        $setups = $this->setupBerikutRules($pengguna);

        foreach ($this->rencanaTrade() as $i => $rencana) {
            $this->buatTrade($pengguna, $instrumen, $setups[$rencana['setup']], $rencana, $i);
        }
    }

    /** @return array<string, Instrument> */
    private function instrumen(User $pengguna): array
    {
        $daftar = [
            'XAUUSD' => 100,
            'EURUSD' => 100000,
            'BTCUSD' => 1,
        ];

        $hasil = [];

        foreach ($daftar as $simbol => $ukuran) {
            // Bukan firstOrCreate: user_id sengaja tidak fillable, dan
            // firstOrCreate memasukkan seluruh atribut lewat mass assignment.
            // Di bawah strict mode itu langsung melempar exception - dan itu
            // memang gunanya aturan tersebut.
            $ins = Instrument::withoutGlobalScope(OwnedByUserScope::class)
                ->where('user_id', $pengguna->id)
                ->where('symbol', $simbol)
                ->first();

            if ($ins === null) {
                $ins = new Instrument(['symbol' => $simbol, 'contract_size' => $ukuran]);
                $ins->user_id = $pengguna->id;
                $ins->saveQuietly();
            }

            $hasil[$simbol] = $ins;
        }

        return $hasil;
    }

    /** @return array<int, TradingSetup> */
    private function setupBerikutRules(User $pengguna): array
    {
        $rencana = [
            'Break of Structure' => [
                ['Struktur higher high / lower low jelas', 3, true],
                ['Break terkonfirmasi candle close', 3, true],
                ['Ada zona supply/demand di belakangnya', 2, false],
                ['Risk/reward minimal 1:2', 2, false],
                ['Bukan jam rilis berita merah', 1, false],
            ],
            'Order Block' => [
                ['Order block belum pernah disentuh', 3, true],
                ['Searah tren timeframe lebih tinggi', 3, true],
                ['Ada imbalance menuju zona', 2, false],
                ['Stop di luar zona, bukan di tengahnya', 2, true],
            ],
            'Supply / Demand' => [
                ['Zona dibentuk pergerakan impulsif', 3, true],
                ['Zona masih segar', 2, false],
                ['Konfirmasi reaksi di timeframe kecil', 2, false],
                ['Tidak melawan sesi likuiditas utama', 1, false],
            ],
        ];

        $setups = [];

        foreach ($rencana as $nama => $rules) {
            $setup = TradingSetup::withoutGlobalScope(OwnedByUserScope::class)
                ->where('user_id', $pengguna->id)
                ->where('name', $nama)
                ->first();

            if ($setup === null) {
                $setup = new TradingSetup([
                    'name' => $nama,
                    'description' => 'Setup contoh untuk data demo.',
                ]);
                $setup->user_id = $pengguna->id;
                $setup->saveQuietly();
            }

            foreach ($rules as $posisi => [$label, $bobot, $wajib]) {
                $sudahAda = SetupRule::withoutGlobalScope(OwnedByUserScope::class)
                    ->where('trading_setup_id', $setup->id)
                    ->where('label', $label)
                    ->exists();

                if ($sudahAda) {
                    continue;
                }

                $rule = new SetupRule([
                    'label' => $label,
                    'weight' => $bobot,
                    'is_required' => $wajib,
                    'position' => $posisi,
                ]);
                $rule->user_id = $pengguna->id;
                $rule->trading_setup_id = $setup->id;
                $rule->saveQuietly();
            }

            $setups[] = $setup;
        }

        return $setups;
    }

    /**
     * Rencana tiap trade: kelompok kepatuhannya dan hasilnya dalam R.
     *
     * Dibuat terpisah dari penulisan ke database supaya distribusinya bisa
     * dibaca dan dinilai sebagai satu kesatuan, bukan tersembunyi di dalam
     * perulangan yang juga mengurus harga dan relasi.
     *
     * @return array<int, array{setup:int, patuh:bool, r:float, hariLalu:int}>
     */
    private function rencanaTrade(): array
    {
        /*
        | Komposisinya DIBANGUN, bukan diundi.
        |
        | Versi pertama mengambil tiap trade dari distribusi peluang dan
        | berharap 46 undian mendarat di rentang yang benar. Hasilnya win rate
        | 30% padahal rancangannya 52% - satu undian yang kebetulan buruk,
        | bukan kesalahan logika. Mengganti pengacaknya tidak menyelesaikan
        | apa pun: benih berikutnya akan meleset ke arah lain.
        |
        | Kalau test menuntut sebuah distribusi, seeder harus MENJAMIN
        | distribusi itu. Jumlah tiap kelompok ditetapkan di sini, nilai R-nya
        | disebar merata di dalam pitanya, lalu urutannya saja yang diacak.
        |
        | 29 patuh (17 menang) dan 17 melanggar (7 menang):
        |   win rate     24/46 = 52%
        |   rata menang  1,85R   rata kalah  1,08R
        |   rata R patuh +0,76   melanggar  -0,07
        | Kedua kelompok di atas MIN_SAMPLE, dan selisihnya terlihat mata.
        */
        $spek = [];

        for ($i = 0; $i < 17; $i++) {
            // Yang patuh membiarkan posisinya berjalan lebih jauh.
            $spek[] = ['patuh' => true, 'r' => round(1.6 + ($i / 16) * 0.8, 2)];
        }

        for ($i = 0; $i < 12; $i++) {
            // Patuh berarti stop dihormati: rugi tepat 1R, tidak lebih.
            $spek[] = ['patuh' => true, 'r' => -1.0];
        }

        for ($i = 0; $i < 7; $i++) {
            $spek[] = ['patuh' => false, 'r' => round(1.2 + ($i / 6) * 0.6, 2)];
        }

        for ($i = 0; $i < 10; $i++) {
            // Melanggar berarti stop digeser atau posisi kebesaran sejak awal,
            // jadi ruginya melewati 1R.
            $spek[] = ['patuh' => false, 'r' => round(-1.0 - ($i / 9) * 0.35, 2)];
        }

        $this->kocok($spek);

        /*
        | Hari dipilih dari daftar tetap berjarak dua hari, bukan diundi.
        |
        | 46 trade di atas 25 hari berarti sebagian hari berisi dua trade dan
        | sisanya kosong - keduanya memang yang ingin terlihat di kalender.
        | Mengundi hari dari rentang 52 hari menghasilkan sekitar 31 hari unik
        | dan nyaris tidak menyisakan celah.
        */
        $hariTersedia = range(2, self::HARI_KE_BELAKANG - 2, 2);

        foreach ($spek as $i => $s) {
            $spek[$i]['setup'] = $i % 3;
            $spek[$i]['hariLalu'] = $hariTersedia[$i % count($hariTersedia)];
        }

        return $spek;
    }

    /**
     * Fisher-Yates dengan pengacak milik kelas ini.
     *
     * shuffle() bawaan PHP memakai benih global, jadi hasilnya tidak bisa
     * diulang - dan seluruh gunanya benih tetap di sini adalah supaya bisa.
     *
     * @param  array<int, array<string, mixed>>  $daftar
     */
    private function kocok(array &$daftar): void
    {
        for ($i = count($daftar) - 1; $i > 0; $i--) {
            $j = (int) floor($this->acak() * ($i + 1));
            [$daftar[$i], $daftar[$j]] = [$daftar[$j], $daftar[$i]];
        }
    }

    /**
     * @param  array<string, Instrument>  $instrumen
     * @param  array{setup:int, patuh:bool, r:float, hariLalu:int}  $rencana
     */
    private function buatTrade(
        User $pengguna,
        array $instrumen,
        TradingSetup $setup,
        array $rencana,
        int $urutan,
    ): void {
        $simbol = array_keys($instrumen)[$urutan % count($instrumen)];
        $ins = $instrumen[$simbol];

        [$lot, $entry, $jarakStop] = match ($simbol) {
            'XAUUSD' => [0.20, 2340.00 + $this->acak() * 60, 6.00],
            'EURUSD' => [0.50, 1.0820 + $this->acak() * 0.02, 0.0040],
            default => [0.05, 61000.00 + $this->acak() * 4000, 900.00],
        };

        $long = $this->acak() < 0.55;
        $arah = $long ? Trade::DIRECTION_LONG : Trade::DIRECTION_SHORT;

        $stop = $long ? $entry - $jarakStop : $entry + $jarakStop;

        // Exit diturunkan dari R yang diinginkan, bukan sebaliknya. Dengan
        // begitu pnl yang dihitung TradeMath dari harga ini pasti menghasilkan
        // R yang direncanakan - tanpa perlu menulis pnl-nya sendiri.
        $geser = $rencana['r'] * $jarakStop;
        $exit = $long ? $entry + $geser : $entry - $geser;

        $dibuka = Carbon::now()
            ->subDays($rencana['hariLalu'])
            ->setTime(8 + (int) floor($this->acak() * 7), (int) floor($this->acak() * 60));

        // Ditutup di hari yang sama. Kalau bisa melewati tengah malam, satu
        // hari kalender bisa terisi oleh trade yang dibuka hari sebelumnya,
        // dan celah hari kosong yang sengaja dibuat di atas ikut tertutup.
        $ditutup = $dibuka->copy()->addHours(1 + (int) floor($this->acak() * 6));

        $trade = new Trade([
            'symbol' => $simbol,
            'direction' => $arah,
            'lot_size' => $lot,
            'entry_price' => round($entry, 8),
            'stop_price' => round($stop, 8),
            'exit_price' => round($exit, 8),
            'opened_at' => $dibuka,
            'closed_at' => $ditutup,
        ]);

        $trade->user_id = $pengguna->id;
        $trade->trading_setup_id = $setup->id;
        $trade->instrument_id = $ins->id;
        $trade->contract_size = $ins->contract_size;

        $trade->risk_amount = TradeMath::risk(
            $lot, (float) $trade->entry_price, (float) $trade->stop_price, (float) $ins->contract_size,
        );
        $trade->pnl_amount = TradeMath::pnl(
            $arah, $lot, (float) $trade->entry_price, (float) $trade->exit_price, (float) $ins->contract_size,
        );

        $checks = $this->checklist($setup, $rencana['patuh'], $urutan);

        $skor = ComplianceScore::from($checks);
        $trade->compliance_score = $skor->value;
        $trade->original_compliance_score = $skor->value;

        $trade->saveQuietly();

        foreach ($checks as $posisi => $c) {
            $periksa = new TradeRuleCheck([
                'is_met' => $c['met'],
                'rule_label' => $c['label'],
                'rule_weight' => $c['weight'],
                'rule_required' => $c['required'],
                'position' => $posisi,
            ]);

            $periksa->user_id = $pengguna->id;
            $periksa->trade_id = $trade->id;
            $periksa->setup_rule_id = $c['id'];
            $periksa->saveQuietly();
        }
    }

    /**
     * Checklist yang konsisten dengan kelompok kepatuhannya.
     *
     * @return array<int, array{id:int, label:string, weight:int, required:bool, met:bool}>
     */
    private function checklist(TradingSetup $setup, bool $patuh, int $urutan): array
    {
        $rules = SetupRule::withoutGlobalScope(OwnedByUserScope::class)
            ->where('trading_setup_id', $setup->id)
            ->orderBy('position')
            ->get();

        $totalBobot = (int) $rules->sum('weight');

        /*
        | Skor dibidik, bukan diundi.
        |
        | compliance_score dihitung ComplianceScore dari checklist ini, dan
        | laporan membandingkannya terhadap ambang pengguna (bawaan 80). Kalau
        | tiap rule dicentang berdasarkan peluang, sebagian trade yang
        | dimaksudkan "patuh" akan mendarat di bawah ambang dan kelompoknya
        | tertukar - lalu korelasi yang mestinya ditampilkan dashboard justru
        | tidak ada di datanya.
        |
        | Pita yang dibidik sengaja berjarak jauh dari ambang di kedua sisi,
        | jadi pembulatan tidak pernah bisa memindahkan satu trade pun.
        */
        $target = $patuh
            ? 0.86 + ($urutan % 4) * 0.035   // 0,86 - 0,965
            : 0.34 + ($urutan % 5) * 0.06;   // 0,34 - 0,58

        // Titik mulai digeser tiap trade supaya rule yang terlewat berbeda-beda.
        // Tanpa ini, rule yang sama selalu jadi korban dan laporan "rule paling
        // sering dilanggar" cuma punya satu jawaban yang membosankan.
        $urut = $rules->all();
        $geser = $urutan % max(count($urut), 1);
        $urut = array_merge(array_slice($urut, $geser), array_slice($urut, 0, $geser));

        $bobotTerpenuhi = 0;
        $hasil = [];

        foreach ($urut as $rule) {
            $masihKurang = $totalBobot > 0
                && ($bobotTerpenuhi / $totalBobot) < $target;

            if ($masihKurang) {
                $bobotTerpenuhi += (int) $rule->weight;
            }

            $hasil[] = [
                'id' => $rule->id,
                'label' => $rule->label,
                'weight' => (int) $rule->weight,
                'required' => (bool) $rule->is_required,
                'met' => $masihKurang,
            ];
        }

        return $hasil;
    }

    /**
     * Pengacak deterministik (xorshift32).
     *
     * Deret milik sendiri, bukan mt_rand: mt_rand memakai benih global yang
     * ikut terpengaruh kode lain di proses yang sama, sehingga hasilnya
     * berhenti bisa diulang.
     *
     * Versi pertama memakai LCG `x * 1103515245 + 12345`, dan hasilnya salah
     * dengan cara yang halus: keluaran berurutannya berkorelasi, sementara
     * seeder ini mengonsumsinya dalam pola yang tetap tiap iterasi - kepatuhan,
     * lalu menang-kalah, lalu besaran R. Slot yang sama di tiap putaran jatuh
     * pada nilai yang saling berhubungan, dan win rate meleset ke 74% padahal
     * rancangannya 52%. Test-nya yang menangkap itu, bukan pembacaan kode.
     *
     * xorshift32 mencampur seluruh bitnya tiap langkah, jadi tidak punya pola
     * tersebut.
     */
    private function acak(): float
    {
        $x = $this->acakan;

        $x ^= ($x << 13) & 0xFFFFFFFF;
        $x ^= $x >> 17;
        $x ^= ($x << 5) & 0xFFFFFFFF;

        $this->acakan = $x & 0xFFFFFFFF;

        return $this->acakan / 0xFFFFFFFF;
    }
}
