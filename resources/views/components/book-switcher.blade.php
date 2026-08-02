@props([
    'books' => null,
    'current' => null,
    'class' => '',
])

@php
    $books = $books ?? collect();
@endphp

@if ($books->isNotEmpty())
    <div class="{{ $class }}">
        <p class="px-4 pt-2 pb-1 text-xs font-medium uppercase tracking-wide text-gray-400">{{ __('Book') }}</p>

        <div class="max-h-56 overflow-y-auto pb-1">
            @foreach ($books as $book)
                <form method="POST" action="{{ route('books.switch', $book) }}">
                    @csrf
                    <button type="submit"
                            class="flex w-full items-center gap-2 px-4 py-2 text-start text-sm transition hover:bg-gray-100 dark:hover:bg-gray-600
                                   {{ $current && $current->id === $book->id
                                       ? 'font-semibold text-gray-900 dark:text-white'
                                       : 'text-gray-600 dark:text-gray-300' }}">
                        @if ($book->icon)
                            <img src="{{ $book->icon->url }}" alt="" class="h-5 w-5 shrink-0 rounded-full object-cover">
                        @else
                            <span class="h-5 w-5 shrink-0 rounded-full bg-gray-200 dark:bg-gray-600"></span>
                        @endif

                        <span class="truncate">{{ $book->name }}</span>

                        @if ($current && $current->id === $book->id)
                            <svg class="ms-auto h-4 w-4 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/>
                            </svg>
                        @endif
                    </button>
                </form>
            @endforeach
        </div>
    </div>
@endif
