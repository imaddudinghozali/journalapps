<?php

namespace App\Policies;

/**
 * Mewarisi seluruh perilaku OwnedRecordPolicy tanpa tambahan.
 *
 * Kelas ini tetap dibuat agar Laravel menemukannya lewat konvensi penamaan
 * App\Policies\{Model}Policy. Itu juga yang membuat otorisasi terpasang di
 * aplikasi sungguhan, bukan hanya di dalam test.
 */
class TradingSetupPolicy extends OwnedRecordPolicy
{
}
