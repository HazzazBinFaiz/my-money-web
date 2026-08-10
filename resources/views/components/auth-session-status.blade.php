@props(['status'])

@if ($status)
    <div {{ $attributes->merge([
        'class' => 'rounded-lg border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm font-medium text-emerald-800
                    dark:border-emerald-900 dark:bg-emerald-950/40 dark:text-emerald-300',
    ]) }}>
        {{ $status }}
    </div>
@endif
