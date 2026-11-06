@props(['totals', 'key'])

@forelse ($totals as $row)
    <div class="text-2xl font-semibold tabular-nums">{{ \App\Services\Money::format($row[$key], $row['currency']) }}</div>
@empty
    <div class="text-2xl font-semibold tabular-nums">{{ \App\Services\Money::format(0, 'USD') }}</div>
@endforelse
