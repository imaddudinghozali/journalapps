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
    <div class="blok p-4 sm:p-8">
        <header>
            <h2 class="text-lg font-medium text-ink">Kepatuhan dan hasil</h2>
            <p class="mt-1 text-sm text-ink-muted">
                Angka hanya ditampilkan setelah ada minimal {{ $n }} trade tertutup pada kelompok yang bersangkutan.
                Di bawah itu, angkanya lebih menyesatkan daripada tidak ada.
            </p>
        </header>

        @if ($laporan->isEmpty())
            <div class="mt-6 text-sm text-ink-muted">
                <p>Belum ada trade tertutup untuk dianalisis.</p>
                <p class="mt-2">
                    Catat trade di halaman <a href="{{ route('trades') }}" class="underline" wire:navigate>Jurnal Trade</a>,
                    lengkap dengan waktu tutup dan hasilnya. Laporan ini terisi sendiri begitu datanya ada.
                </p>
            </div>
        @else
            <dl class="mt-6 grid grid-cols-1 sm:grid-cols-3 gap-4 text-sm">
                <div class="border border-line rounded-md p-3">
                    <dt class="text-ink-faint">Keseluruhan</dt>
                    <dd class="mt-1 text-ink">{{ $angka($laporan->overall) }}</dd>
                </div>
                <div class="border border-line rounded-md p-3">
                    <dt class="text-ink-faint">Masih terbuka</dt>
                    <dd class="mt-1 text-ink">{{ $this->openCount }} trade</dd>
                </div>
                <div class="border border-line rounded-md p-3">
                    <dt class="text-ink-faint">Tanpa skor kepatuhan</dt>
                    <dd class="mt-1 text-ink">{{ $laporan->unscoredCount }} trade</dd>
                </div>
            </dl>
        @endif
    </div>

    @unless ($laporan->isEmpty())
        {{-- Perbandingan inti produk. Satu-satunya panel di halaman ini:
             seluruh laporan lain ada untuk menjelaskan angka di sini. --}}
        <div class="panel spotlight p-4 sm:p-8">
            <header>
                <h2 class="text-lg font-medium text-ink">Patuh vs tidak patuh</h2>
                <p class="mt-1 text-sm text-ink-muted">
                    Memakai ambangmu sendiri ({{ $this->threshold() }}%). Trade tanpa skor tidak dihitung di kelompok
                    mana pun.
                </p>
            </header>

            <dl class="mt-4 grid grid-cols-1 sm:grid-cols-2 gap-4 text-sm">
                <div class="border border-line rounded-md p-3">
                    <dt class="text-ink-faint">Patuh (skor &ge; {{ $this->threshold() }}%)</dt>
                    <dd class="mt-1 text-ink">{{ $angka($laporan->compliant) }}</dd>
                </div>
                <div class="border border-line rounded-md p-3">
                    <dt class="text-ink-faint">Tidak patuh (skor &lt; {{ $this->threshold() }}%)</dt>
                    <dd class="mt-1 text-ink">{{ $angka($laporan->nonCompliant) }}</dd>
                </div>
            </dl>

            @if ($laporan->revisedCount > 0)
                <p class="mt-4 text-sm text-warn">
                    {{ $laporan->revisedCount }} trade checklist-nya pernah direvisi setelah dicatat. Skor kelompok di
                    atas memakai jawaban terbaru, bukan jawaban saat entry.
                </p>
            @endif

            <p class="mt-4 text-xs text-ink-faint">
                Perbedaan angka di sini menunjukkan kaitan, bukan sebab-akibat. Periode pasar, instrumen, dan ukuran
                posisi juga ikut berpengaruh.
            </p>
        </div>

        {{-- Per setup --}}
        <div class="blok p-4 sm:p-8">
            <header>
                <h2 class="text-lg font-medium text-ink">Per setup</h2>
            </header>

            <div class="mt-4 overflow-x-auto">
                <table class="min-w-full text-sm">
                    <thead class="text-left text-ink-faint border-b border-line">
                        <tr>
                            <th class="py-2 pe-4">Setup</th>
                            <th class="py-2">Hasil</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-line">
                        @foreach ($laporan->perSetup as $baris)
                            <tr wire:key="setup-{{ $loop->index }}">
                                <td class="py-2 pe-4 font-medium text-ink">{{ $baris['name'] }}</td>
                                <td class="py-2 text-ink-muted">{{ $angka($baris['stats']) }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>

        {{-- Per rule --}}
        @if (count($laporan->perRule) > 0)
            <div class="blok p-4 sm:p-8">
                <header>
                    <h2 class="text-lg font-medium text-ink">Per rule</h2>
                    <p class="mt-1 text-sm text-ink-muted">
                        Diurutkan dari yang paling sering dilanggar. Kolom terakhir membandingkan hasil saat rule
                        terpenuhi dan saat tidak.
                    </p>
                </header>

                <div class="mt-4 overflow-x-auto">
                    <table class="min-w-full text-sm">
                        <thead class="text-left text-ink-faint border-b border-line">
                            <tr>
                                <th class="py-2 pe-4">Rule</th>
                                <th class="py-2 pe-4">Dilanggar</th>
                                <th class="py-2 pe-4">Saat terpenuhi</th>
                                <th class="py-2">Saat dilanggar</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-line">
                            @foreach ($laporan->perRule as $baris)
                                <tr wire:key="rule-{{ $loop->index }}">
                                    <td class="py-2 pe-4 text-ink">
                                        {{ $baris['label'] }}
                                        @if ($baris['rule_id'] === null)
                                            <span class="ms-1 text-xs text-ink-faint">(sudah dihapus)</span>
                                        @endif
                                    </td>
                                    <td class="py-2 pe-4 text-ink-muted">{{ $baris['violations'] }}&times;</td>
                                    <td class="py-2 pe-4 text-ink-muted">{{ $angka($baris['met']) }}</td>
                                    <td class="py-2 text-ink-muted">{{ $angka($baris['unmet']) }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>
        @endif
    @endunless
</div>
