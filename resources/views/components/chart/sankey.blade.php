@props([
    'flow',
    'title' => null,
    'subtitle' => null,
    'empty' => 'Nothing moved in this range.',
])

@php
    use App\Lib\Util;

    $columns = [
        'income' => $flow['income'],
        'accounts' => $flow['accounts'],
        'expense' => $flow['expense'],
    ];

    $nodeWidth = 14;
    $gap = 15;

    // A transfer has to turn around in the space between two account nodes, so
    // that column is given room when there is one to draw.
    $hasTransfers = $flow['links']->contains(fn (array $link) => $link['side'] === 'transfer');

    $gaps = [
        'income' => $gap,
        'accounts' => $hasTransfers ? 64 : $gap,
        'expense' => $gap,
    ];
    $height = 520;
    $width = 1000;

    // Column x positions leave room for the labels either side.
    $x = [
        'income' => 150,
        'accounts' => 493,
        'expense' => 836,
    ];

    $sums = collect($columns)->map(fn ($nodes) => (int) $nodes->sum('total'));
    $counts = collect($columns)->map(fn ($nodes) => $nodes->count());

    // One scale for every column, so a ribbon keeps its thickness end to end.
    $scale = $counts->filter()->isEmpty() ? 0 : collect($columns)
        ->filter(fn ($nodes) => $nodes->isNotEmpty())
        ->map(fn ($nodes, $key) => ($height - ($counts[$key] - 1) * $gaps[$key]) / max(1, $sums[$key]))
        ->min();

    // Lay every node out, then remember where each edge has filled up to.
    $placed = [];

    foreach ($columns as $key => $nodes) {
        $stack = $nodes->sum(fn (array $node) => max(3, $node['total'] * $scale))
            + max(0, $nodes->count() - 1) * $gaps[$key];

        $y = ($height - $stack) / 2;

        foreach ($nodes as $node) {
            $nodeHeight = max(3, $node['total'] * $scale);

            $placed[$node['id']] = $node + [
                'column' => $key,
                'x' => $x[$key],
                'y' => $y,
                'height' => $nodeHeight,
                'left' => $y,
                'right' => $y,
            ];

            $y += $nodeHeight + $gaps[$key];
        }
    }

    // Each edge is stacked in a fixed order so the two ends of a ribbon always
    // agree: transfers in at the top of an account's left edge, transfers out at
    // the bottom of its right edge, income and spending in between.
    $thicknessOf = fn (array $link) => max(1, $link['value'] * $scale);

    $ends = [];

    // Left edges: transfers arrive first, then income beneath them.
    foreach (['transfer', 'income'] as $side) {
        foreach ($flow['links']->where('side', $side) as $link) {
            if (! isset($placed[$link['source']], $placed[$link['target']])) {
                continue;
            }

            $ends[$link['source'].'>'.$link['target']]['in'] = $placed[$link['target']]['left'];
            $placed[$link['target']]['left'] += $thicknessOf($link);
        }
    }

    // Right edges: spending leaves from the top.
    foreach ($flow['links']->where('side', 'expense') as $link) {
        if (! isset($placed[$link['source']], $placed[$link['target']])) {
            continue;
        }

        $ends[$link['source'].'>'.$link['target']]['out'] = $placed[$link['source']]['right'];
        $placed[$link['source']]['right'] += $thicknessOf($link);
    }

    // Transfers out leave last, flush with the bottom of the node. Anything the
    // account kept is then the gap between its spending and its transfers.
    $movedOut = [];

    foreach ($flow['links']->where('side', 'transfer') as $link) {
        if (isset($placed[$link['source']])) {
            $movedOut[$link['source']] = ($movedOut[$link['source']] ?? 0) + $thicknessOf($link);
        }
    }

    foreach ($movedOut as $id => $thickness) {
        $placed[$id]['right'] = max(
            $placed[$id]['right'],
            $placed[$id]['y'] + $placed[$id]['height'] - $thickness,
        );
    }

    foreach ($flow['links']->where('side', 'transfer') as $link) {
        if (! isset($placed[$link['source']], $placed[$link['target']])) {
            continue;
        }

        $ends[$link['source'].'>'.$link['target']]['out'] = $placed[$link['source']]['right'];
        $placed[$link['source']]['right'] += $thicknessOf($link);
    }

    // Income ribbons leave their category in the order they were stacked.
    foreach ($flow['links']->where('side', 'income') as $link) {
        if (! isset($placed[$link['source']], $placed[$link['target']])) {
            continue;
        }

        $ends[$link['source'].'>'.$link['target']]['out'] = $placed[$link['source']]['right'];
        $placed[$link['source']]['right'] += $thicknessOf($link);
    }

    // Expense ribbons arrive at their category the same way.
    foreach ($flow['links']->where('side', 'expense') as $link) {
        if (! isset($placed[$link['source']], $placed[$link['target']])) {
            continue;
        }

        $ends[$link['source'].'>'.$link['target']]['in'] = $placed[$link['target']]['left'];
        $placed[$link['target']]['left'] += $thicknessOf($link);
    }

    $ribbons = [];

    foreach ($flow['links'] as $link) {
        $key = $link['source'].'>'.$link['target'];

        if (! isset($ends[$key]['in'], $ends[$key]['out'])) {
            continue;
        }

        $source = $placed[$link['source']];
        $target = $placed[$link['target']];

        $thickness = $thicknessOf($link);
        $x0 = $source['x'] + $nodeWidth;
        $x1 = $target['x'];
        $y0 = $ends[$key]['out'];
        $y1 = $ends[$key]['in'];

        if ($link['side'] === 'transfer') {
            // Both ends are in the same column, so the ribbon leaves the source
            // heading right, swings out, and comes back across the column into
            // the target's left edge. The return boundary reaches further out
            // than the outbound one, which keeps the width even round the bend
            // instead of pinching into a bowtie.
            $reach = min(150, max(70, abs($y1 - $y0) * 0.6 + 60));

            $path = sprintf(
                'M%.1f,%.1f C%.1f,%.1f %.1f,%.1f %.1f,%.1f L%.1f,%.1f C%.1f,%.1f %.1f,%.1f %.1f,%.1f Z',
                $x0, $y0,
                $x0 + $reach, $y0,
                $x1 - $reach + $thickness, $y1,
                $x1, $y1,
                $x1, $y1 + $thickness,
                $x1 - $reach, $y1 + $thickness,
                $x0 + $reach + $thickness, $y0 + $thickness,
                $x0, $y0 + $thickness,
            );
        } else {
            $mid = ($x0 + $x1) / 2;

            $path = sprintf(
                'M%.1f,%.1f C%.1f,%.1f %.1f,%.1f %.1f,%.1f L%.1f,%.1f C%.1f,%.1f %.1f,%.1f %.1f,%.1f Z',
                $x0, $y0, $mid, $y0, $mid, $y1, $x1, $y1,
                $x1, $y1 + $thickness,
                $mid, $y1 + $thickness, $mid, $y0 + $thickness,
                $x0, $y0 + $thickness,
            );
        }

        $ribbons[] = [
            'side' => $link['side'],
            'value' => $link['value'],
            'label' => $source['name'].' → '.$target['name'],
            'path' => $path,
        ];
    }

    // Transfer loops are thin and cross the busiest part of the picture, so they
    // are painted last rather than lost under the spending.
    usort($ribbons, fn (array $a, array $b) => ($a['side'] === 'transfer' ? 1 : 0) <=> ($b['side'] === 'transfer' ? 1 : 0));

    $ribbonFill = [
        'income' => 'var(--viz-income)',
        'expense' => 'var(--viz-expense)',
        'transfer' => 'var(--viz-bar)',
    ];

    $nodeFill = [
        'income' => 'var(--viz-income)',
        'accounts' => 'var(--viz-bar)',
        'expense' => 'var(--viz-expense)',
    ];
@endphp

<x-ui.card {{ $attributes->merge(['class' => 'viz']) }}>
    @if ($title)
        <div class="border-b border-gray-100 px-4 py-4 sm:px-6 dark:border-gray-700">
            <h3 class="text-base font-semibold text-gray-900 dark:text-gray-100">{{ $title }}</h3>
            @if ($subtitle)
                <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">{{ $subtitle }}</p>
            @endif
        </div>
    @endif

    @if ($ribbons === [])
        <p class="px-4 py-12 text-center text-sm text-gray-500 sm:px-6">{{ $empty }}</p>
    @else
        {{-- Legend: three kinds of ribbon, so the blue loops need no explaining --}}
        <div class="flex flex-wrap items-center gap-4 px-4 pt-4 text-xs text-gray-600 sm:px-6 dark:text-gray-400">
            @foreach ([__('Income') => 'var(--viz-income)', __('Transfer') => 'var(--viz-bar)', __('Spending') => 'var(--viz-expense)'] as $label => $colour)
                <span class="flex items-center gap-1.5">
                    <span class="h-2.5 w-4 rounded-sm" style="background: {{ $colour }}; opacity: .55"></span>
                    {{ $label }}
                </span>
            @endforeach
        </div>

        <div class="overflow-x-auto p-4 sm:p-6">
            <svg viewBox="0 0 {{ $width }} {{ $height }}" class="viz-sankey h-auto w-full min-w-[46rem]"
                 role="img" aria-label="{{ __('Income categories flowing through accounts into expense categories') }}">

                <g class="viz-ribbons">
                    @foreach ($ribbons as $ribbon)
                        <path class="viz-ribbon" d="{{ $ribbon['path'] }}" tabindex="0"
                              fill="{{ $ribbonFill[$ribbon['side']] }}"
                              fill-opacity="{{ $ribbon['side'] === 'transfer' ? '0.45' : '0.32' }}">
                            <title>{{ $ribbon['label'] }}: {{ Util::displayAmount($ribbon['value']) }}</title>
                        </path>
                    @endforeach
                </g>

                @foreach ($placed as $node)
                    <g>
                        <rect x="{{ $node['x'] }}" y="{{ round($node['y'], 1) }}"
                              width="{{ $nodeWidth }}" height="{{ round($node['height'], 1) }}"
                              rx="3" fill="{{ $nodeFill[$node['column']] }}">
                            <title>{{ $node['name'] }}: {{ Util::displayAmount($node['total']) }}</title>
                        </rect>

                        {{-- Labels carry a halo so they stay readable over the ribbons --}}
                        <text class="viz-label"
                              x="{{ $node['column'] === 'income' ? $node['x'] - 8 : $node['x'] + $nodeWidth + 8 }}"
                              y="{{ round($node['y'] + $node['height'] / 2, 1) }}"
                              text-anchor="{{ $node['column'] === 'income' ? 'end' : 'start' }}"
                              dominant-baseline="middle">
                            {{ $node['name'] }}
                            <tspan class="viz-label-value" dx="6">{{ Util::displayAmount($node['total']) }}</tspan>
                        </text>
                    </g>
                @endforeach
            </svg>
        </div>
    @endif
</x-ui.card>
