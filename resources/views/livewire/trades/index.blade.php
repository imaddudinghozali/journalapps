<?php

use App\Models\Trade;
use Livewire\Attributes\Computed;
use Livewire\Attributes\On;
use Livewire\Volt\Component;

new class extends Component
{
    #[Computed]
    public function trades()
    {
        // Terscope oleh global scope; tidak ada filter user_id manual.
        return Trade::with('setup')
            ->orderByDesc('opened_at')
            ->orderByDesc('id')
            ->limit(50)
            ->get();
    }

    #[On('trade-recorded')]
    public function refreshList(): void
    {
        unset($this->trades);
    }
};
?>

<div class="p-4 sm:p-8 bg-surface shadow-soft sm:rounded-xl spotlight transition-shadow hover:shadow-lift">
    <header>
        <h2 class="text-lg font-medium text-ink">Trade terakhir</h2>
        <p class="mt-1 text-sm text-ink-muted">
            Ringkasan per trade. Analisis kepatuhan terhadap hasil ada di halaman Laporan.
        </p>
    </header>

    @if ($this->trades->isEmpty())
        <p class="mt-4 text-sm text-ink-faint">Belum ada trade tercatat.</p>
    @else
        <div class="mt-4 overflow-x-auto">
            <table class="min-w-full text-sm">
                <thead class="text-left text-ink-faint border-b border-line">
                    <tr>
                        <th class="py-2 pe-4">Waktu</th>
                        <th class="py-2 pe-4">Instrumen</th>
                        <th class="py-2 pe-4">Arah</th>
                        <th class="py-2 pe-4">Setup</th>
                        <th class="py-2 pe-4">Kepatuhan</th>
                        <th class="py-2 pe-4">Hasil</th>
                        <th class="py-2 pe-4">R</th>
                        <th class="py-2"><span class="sr-only">Aksi</span></th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-line">
                    @foreach ($this->trades as $trade)
                        <tr wire:key="trade-{{ $trade->id }}" class="row-enter">
                            <td class="py-2 pe-4 text-ink-muted">{{ $trade->opened_at->format('d/m/Y H:i') }}</td>
                            <td class="py-2 pe-4 font-medium text-ink">{{ $trade->symbol }}</td>
                            <td class="py-2 pe-4 text-ink-muted">{{ $trade->direction }}</td>
                            <td class="py-2 pe-4 text-ink-muted">{{ $trade->setup->name }}</td>
                            <td class="py-2 pe-4 text-ink-muted tabular">
                                {{ $trade->isUnscored() ? 'tanpa skor' : $trade->compliance_score.'%' }}
                            </td>
                            <td class="py-2 pe-4 text-ink-muted">
                                {{ $trade->isClosed() ? number_format((float) $trade->pnl_amount, 2) : 'terbuka' }}
                            </td>
                            <td class="py-2 pe-4 text-ink-muted">
                                {{ $trade->rMultiple() === null ? 'belum ada' : number_format($trade->rMultiple(), 2).'R' }}
                            </td>
                            <td class="py-2 text-end">
                                <a href="{{ route('trades.edit', $trade) }}" wire:navigate
                                   class="text-sm text-accent underline">{{ $trade->isClosed() ? 'Ubah' : 'Tutup' }}</a>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    @endif
</div>
