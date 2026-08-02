@props(['disabled' => false])

<input @disabled($disabled) {{ $attributes->merge([
    'class' => 'flex h-10 w-full rounded-md border border-gray-200 bg-white px-3 py-2 text-sm text-gray-900 shadow-sm transition
                placeholder:text-gray-400 focus:outline-none focus:ring-2 focus:ring-gray-900/10 focus:border-gray-400
                disabled:cursor-not-allowed disabled:opacity-50
                dark:border-gray-700 dark:bg-gray-900 dark:text-gray-100 dark:focus:ring-white/10 dark:focus:border-gray-500',
]) }}>
