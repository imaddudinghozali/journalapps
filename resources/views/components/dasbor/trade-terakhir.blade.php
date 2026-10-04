@props(['metrik', 'delay' => 0])

@php
    use App\Support\Angka;
    use App\Support\ComplianceTone;
@endphp

<x-card label="Trade terakhir" :delay="$delay">
    @if ($metrik->recentTrades === [])
        <p class="mt-4 text-sm text-ink-faint">Belum ada trade tertutup.</p>
    @else
        <ul class="mt-3 divide-y divide-line">
            @foreach ($metrik->recentTrades as $baris)
                @php($t = $baris['trade'])
                <li class="flex items-center justify-between gap-3 py-2.5" wire:key="akhir-{{ $t->id }}">
                    <div class="min-w-0">
                        <a href="{{ route('trades.edit', $t) }}" wire:navigate
                           class="font-medium text-ink transition-colors hover:text-accent-ink">{{ $t->symbol }}</a>
                        <span class="block text-xs text-ink-faint">{{ $t->closed_at->format('d/m') }} &middot; {{ $t->setup->name }}</span>
                    </div>

                    <div class="flex shrink-0 items-center gap-3">
                        <span class="rounded-full border px-2 py-0.5 text-xs tabular {{ ComplianceTone::surfaceClass($t->compliance_score) }} {{ ComplianceTone::textClass($t->compliance_score) }}">
                            {{ $baris['total'] > 0 ? $baris['met'].'/'.$baris['total'] : 'tanpa rule' }}
                        </span>
                        <span class="w-16 text-end text-sm font-semibold tabular {{ Angka::nada($baris['r']) }}">{{ Angka::r($baris['r']) }}</span>
                    </div>
                </li>
            @endforeach
        </ul>
    @endif
</x-card>
