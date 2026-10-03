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

<div class="p-4 sm:p-8 bg-white shadow sm:rounded-lg">
    <header>
        <h2 class="text-lg font-medium text-gray-900">Trade terakhir</h2>
        <p class="mt-1 text-sm text-gray-600">
            Ringkasan per trade. Analisis kepatuhan terhadap hasil ada di halaman Laporan.
        </p>
    </header>

    @if ($this->trades->isEmpty())
        <p class="mt-4 text-sm text-gray-500">Belum ada trade tercatat.</p>
    @else
        <div class="mt-4 overflow-x-auto">
            <table class="min-w-full text-sm">
                <thead class="text-left text-gray-500 border-b border-gray-200">
                    <tr>
                        <th class="py-2 pe-4">Waktu</th>
                        <th class="py-2 pe-4">Instrumen</th>
                        <th class="py-2 pe-4">Arah</th>
                        <th class="py-2 pe-4">Setup</th>
                        <th class="py-2 pe-4">Kepatuhan</th>
                        <th class="py-2 pe-4">Hasil</th>
                        <th class="py-2">R</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100">
                    @foreach ($this->trades as $trade)
                        <tr wire:key="trade-{{ $trade->id }}">
                            <td class="py-2 pe-4 text-gray-600">{{ $trade->opened_at->format('d/m/Y H:i') }}</td>
                            <td class="py-2 pe-4 font-medium text-gray-900">{{ $trade->symbol }}</td>
                            <td class="py-2 pe-4 text-gray-600">{{ $trade->direction }}</td>
                            <td class="py-2 pe-4 text-gray-600">{{ $trade->setup->name }}</td>
                            <td class="py-2 pe-4 text-gray-700">
                                {{ $trade->isUnscored() ? '—' : $trade->compliance_score.'%' }}
                            </td>
                            <td class="py-2 pe-4 text-gray-700">
                                {{ $trade->isClosed() ? number_format((float) $trade->pnl_amount, 2) : 'terbuka' }}
                            </td>
                            <td class="py-2 text-gray-700">
                                {{ $trade->rMultiple() === null ? '—' : number_format($trade->rMultiple(), 2).'R' }}
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    @endif
</div>
