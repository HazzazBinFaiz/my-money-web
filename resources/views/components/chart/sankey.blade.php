@props([
    'flow',
    'title' => null,
    'subtitle' => null,
    'empty' => 'Nothing moved in this range.',
])

@php
    use App\Lib\Util;

    // The layout runs in the browser (d3-sankey-circular), so the server's job
    // is only to hand over the graph with its amounts already formatted for the
    // book's currency.
    $names = collect([$flow['income'], $flow['accounts'], $flow['expense']])
        ->flatten(1)
        ->pluck('name', 'id');

    $payload = [
        'nodes' => collect(['income' => $flow['income'], 'accounts' => $flow['accounts'], 'expense' => $flow['expense']])
            ->flatMap(fn ($nodes, $column) => $nodes->map(fn (array $node) => [
                'id' => $node['id'],
                'name' => $node['name'],
                // Not 'column': the layout writes its own numeric column onto
                // every node and would overwrite it.
                'group' => $column,
                'amount' => Util::displayAmount($node['total']),
            ]))
            ->values(),

        'links' => $flow['links']->map(fn (array $link) => [
            'source' => $link['source'],
            'target' => $link['target'],
            'value' => $link['value'],
            'side' => $link['side'],
            'sourceName' => $names[$link['source']] ?? '',
            'targetName' => $names[$link['target']] ?? '',
            'amount' => Util::displayAmount($link['value']),
        ])->values(),
    ];
@endphp

{{-- x-data lives on a plain element: Blade does not compile directives inside a
     component tag's attributes. --}}
<x-ui.card {{ $attributes->merge(['class' => 'viz']) }}>
    <div x-data="moneyFlow(@js($payload))">
        @if ($title)
            <div class="border-b border-gray-100 px-4 py-4 sm:px-6 dark:border-gray-700">
                <h3 class="text-base font-semibold text-gray-900 dark:text-gray-100">{{ $title }}</h3>
                @if ($subtitle)
                    <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">{{ $subtitle }}</p>
                @endif
            </div>
        @endif

        <template x-if="empty">
            <p class="px-4 py-12 text-center text-sm text-gray-500 sm:px-6">{{ $empty }}</p>
        </template>

        <div x-show="! empty">
            {{-- Legend: three kinds of ribbon, so the transfer loops need no explaining --}}
            <div class="flex flex-wrap items-center gap-4 px-4 pt-4 text-xs text-gray-600 sm:px-6 dark:text-gray-400">
                @foreach ([__('Income') => 'income', __('Transfer') => 'transfer', __('Spending') => 'expense'] as $label => $side)
                    <span class="flex items-center gap-1.5">
                        <span class="viz-key viz-key--{{ $side }}"></span>
                        {{ $label }}
                    </span>
                @endforeach
            </div>

            <div class="overflow-x-auto p-4 sm:p-6">
                <svg x-ref="canvas" class="h-auto w-full min-w-[46rem]" viewBox="0 0 1000 520"
                     role="img" aria-label="{{ __('Income categories flowing through accounts into expense categories') }}"></svg>
            </div>
        </div>

        <noscript>
            <p class="px-4 py-6 text-center text-sm text-gray-500 sm:px-6">
                {{ __('The diagram needs JavaScript. The table below carries the same figures.') }}
            </p>
        </noscript>
    </div>
</x-ui.card>
