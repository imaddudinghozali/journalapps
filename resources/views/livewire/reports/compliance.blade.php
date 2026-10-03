<?php

use App\Models\Trade;
use App\Support\ComplianceReport;
use App\Support\TradeStatistics;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Computed;
use Livewire\Volt\Component;

new class extends Component
{
    #[Computed]
    public function report(): ComplianceReport
    {
        // Terscope global; relasi dimuat karena ComplianceReport
        // mensyaratkannya dan strict mode melarang lazy loading.
        return ComplianceReport::from(
            Trade::with(['setup', 'ruleChecks'])->closed()->get(),
            $this->threshold(),
        );
    }

    #[Computed]
    public function openCount(): int
    {
        return Trade::stillOpen()->count();
    }

    public function threshold(): int
    {
        return (int) Auth::user()->compliance_threshold;
    }

    public function minSample(): int
    {
        return ComplianceReport::MIN_SAMPLE;
    }
};
?>

@php
    $laporan = $this->report;
    $n = $this->minSample();

    // Satu tempat memformat angka, supaya tidak ada statistik yang lolos
    // tampil tanpa jumlah sampelnya.
    $angka = function (TradeStatistics $s) use ($n): string {
        if (! $s->hasEnoughSample($n)) {
            return 'belum cukup data (n='.$s->sampleSize.')';
        }

        return $s->winRate.'% menang · '.number_format($s->expectancy, 2).'R rata-rata (n='.$s->sampleSize.')';
    };
@endphp

<div class="space-y-6">
    <div class="p-4 sm:p-8 bg-white shadow sm:rounded-lg">
        <header>
            <h2 class="text-lg font-medium text-gray-900">Kepatuhan dan hasil</h2>
            <p class="mt-1 text-sm text-gray-600">
                Angka hanya ditampilkan setelah ada minimal {{ $n }} trade tertutup pada kelompok yang bersangkutan.
                Di bawah itu, angkanya lebih menyesatkan daripada tidak ada.
            </p>
        </header>

        @if ($laporan->isEmpty())
            <div class="mt-6 text-sm text-gray-600">
                <p>Belum ada trade tertutup untuk dianalisis.</p>
                <p class="mt-2">
                    Catat trade di halaman <a href="{{ route('trades') }}" class="underline" wire:navigate>Jurnal Trade</a>,
                    lengkap dengan waktu tutup dan hasilnya. Laporan ini terisi sendiri begitu datanya ada.
                </p>
            </div>
        @else
            <dl class="mt-6 grid grid-cols-1 sm:grid-cols-3 gap-4 text-sm">
                <div class="border border-gray-200 rounded-md p-3">
                    <dt class="text-gray-500">Keseluruhan</dt>
                    <dd class="mt-1 text-gray-900">{{ $angka($laporan->overall) }}</dd>
                </div>
                <div class="border border-gray-200 rounded-md p-3">
                    <dt class="text-gray-500">Masih terbuka</dt>
                    <dd class="mt-1 text-gray-900">{{ $this->openCount }} trade</dd>
                </div>
                <div class="border border-gray-200 rounded-md p-3">
                    <dt class="text-gray-500">Tanpa skor kepatuhan</dt>
                    <dd class="mt-1 text-gray-900">{{ $laporan->unscoredCount }} trade</dd>
                </div>
            </dl>
        @endif
    </div>

    @unless ($laporan->isEmpty())
        {{-- Perbandingan inti produk --}}
        <div class="p-4 sm:p-8 bg-white shadow sm:rounded-lg">
            <header>
                <h2 class="text-lg font-medium text-gray-900">Patuh vs tidak patuh</h2>
                <p class="mt-1 text-sm text-gray-600">
                    Memakai ambangmu sendiri ({{ $this->threshold() }}%). Trade tanpa skor tidak dihitung di kelompok
                    mana pun.
                </p>
            </header>

            <dl class="mt-4 grid grid-cols-1 sm:grid-cols-2 gap-4 text-sm">
                <div class="border border-gray-200 rounded-md p-3">
                    <dt class="text-gray-500">Patuh (skor &ge; {{ $this->threshold() }}%)</dt>
                    <dd class="mt-1 text-gray-900">{{ $angka($laporan->compliant) }}</dd>
                </div>
                <div class="border border-gray-200 rounded-md p-3">
                    <dt class="text-gray-500">Tidak patuh (skor &lt; {{ $this->threshold() }}%)</dt>
                    <dd class="mt-1 text-gray-900">{{ $angka($laporan->nonCompliant) }}</dd>
                </div>
            </dl>

            <p class="mt-4 text-xs text-gray-500">
                Perbedaan angka di sini menunjukkan kaitan, bukan sebab-akibat. Periode pasar, instrumen, dan ukuran
                posisi juga ikut berpengaruh.
            </p>
        </div>

        {{-- Per setup --}}
        <div class="p-4 sm:p-8 bg-white shadow sm:rounded-lg">
            <header>
                <h2 class="text-lg font-medium text-gray-900">Per setup</h2>
            </header>

            <div class="mt-4 overflow-x-auto">
                <table class="min-w-full text-sm">
                    <thead class="text-left text-gray-500 border-b border-gray-200">
                        <tr>
                            <th class="py-2 pe-4">Setup</th>
                            <th class="py-2">Hasil</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100">
                        @foreach ($laporan->perSetup as $baris)
                            <tr wire:key="setup-{{ $loop->index }}">
                                <td class="py-2 pe-4 font-medium text-gray-900">{{ $baris['name'] }}</td>
                                <td class="py-2 text-gray-700">{{ $angka($baris['stats']) }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>

        {{-- Per rule --}}
        @if (count($laporan->perRule) > 0)
            <div class="p-4 sm:p-8 bg-white shadow sm:rounded-lg">
                <header>
                    <h2 class="text-lg font-medium text-gray-900">Per rule</h2>
                    <p class="mt-1 text-sm text-gray-600">
                        Diurutkan dari yang paling sering dilanggar. Kolom terakhir membandingkan hasil saat rule
                        terpenuhi dan saat tidak.
                    </p>
                </header>

                <div class="mt-4 overflow-x-auto">
                    <table class="min-w-full text-sm">
                        <thead class="text-left text-gray-500 border-b border-gray-200">
                            <tr>
                                <th class="py-2 pe-4">Rule</th>
                                <th class="py-2 pe-4">Dilanggar</th>
                                <th class="py-2 pe-4">Saat terpenuhi</th>
                                <th class="py-2">Saat dilanggar</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100">
                            @foreach ($laporan->perRule as $baris)
                                <tr wire:key="rule-{{ $loop->index }}">
                                    <td class="py-2 pe-4 text-gray-900">
                                        {{ $baris['label'] }}
                                        @if ($baris['rule_id'] === null)
                                            <span class="ms-1 text-xs text-gray-500">(sudah dihapus)</span>
                                        @endif
                                    </td>
                                    <td class="py-2 pe-4 text-gray-700">{{ $baris['violations'] }}&times;</td>
                                    <td class="py-2 pe-4 text-gray-700">{{ $angka($baris['met']) }}</td>
                                    <td class="py-2 text-gray-700">{{ $angka($baris['unmet']) }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>
        @endif
    @endunless
</div>
