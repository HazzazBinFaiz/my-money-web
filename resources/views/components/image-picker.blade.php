@props([
    'name' => 'icon_id',
    'type' => \App\Enums\ImageType::Account,
    'image' => null,
    'label' => 'Icon',
])

<div
    x-data="imagePicker({
        type: {{ $type->value }},
        imageId: {{ $image?->id ?? 'null' }},
        imageUrl: {{ $image ? Js::from($image->url) : 'null' }},
    })"
    class="flex items-center gap-3"
>
    <input type="hidden" name="{{ $name }}" :value="selectedId">

    <button type="button" @click="openModal()"
            class="group relative flex h-[69px] w-[69px] shrink-0 items-center justify-center overflow-hidden avatar rounded-full
                   border-2 border-dashed border-gray-300 bg-gray-50 transition hover:border-gray-900
                   dark:border-gray-600 dark:bg-gray-900 dark:hover:border-gray-300">
        <template x-if="selectedUrl">
            <img :src="selectedUrl" alt="" class="h-[69px] w-[69px] avatar rounded-full object-cover">
        </template>
        <template x-if="!selectedUrl">
            <span class="px-1 text-center text-[11px] leading-tight text-gray-400">{{ $label }}</span>
        </template>
    </button>

    <button type="button" x-show="selectedId" x-cloak @click="clear()"
            class="text-xs font-medium text-red-600 hover:underline">
        {{ __('Remove') }}
    </button>

    <!-- Picker dialog -->
    <div x-show="open" x-cloak @keydown.escape.window="closeModal()"
         class="fixed inset-0 z-50 flex items-end justify-center p-0 sm:items-center sm:p-4">
        <div class="absolute inset-0 bg-black/50 backdrop-blur-[1px]" @click="closeModal()"></div>

        <div class="relative flex h-[88vh] max-h-[720px] w-full max-w-xl flex-col rounded-t-2xl border border-gray-200
                    bg-white p-5 shadow-xl sm:h-[80vh] sm:rounded-xl dark:border-gray-700 dark:bg-gray-800">

            <div class="mb-4 flex items-start justify-between gap-4">
                <div>
                    <h3 class="text-base font-semibold text-gray-900 dark:text-gray-100"
                        x-text="mode === 'grid' ? '{{ __('Pick an image') }}' : '{{ __('New image') }}'"></h3>
                    <p class="mt-0.5 text-xs text-gray-500 dark:text-gray-400"
                       x-text="mode === 'grid' ? '{{ __('Reuse an image or add a new one.') }}' : '{{ __('Scale and drag to fit the circle.') }}'"></p>
                </div>
                <button type="button" @click="closeModal()"
                        class="rounded-md p-1 text-gray-400 transition hover:bg-gray-100 hover:text-gray-600 dark:hover:bg-gray-700">
                    <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                    </svg>
                </button>
            </div>

            <p x-show="error" x-cloak x-text="error"
               class="mb-3 rounded-md bg-red-50 px-3 py-2 text-sm text-red-600 dark:bg-red-950/50"></p>

            <!-- Grid mode -->
            <div x-show="mode === 'grid'" class="flex min-h-0 flex-1 flex-col">
                <div class="min-h-0 flex-1 overflow-y-auto rounded-lg border border-gray-100 p-3 dark:border-gray-700">
                    <div class="grid grid-cols-3 justify-items-center gap-4 sm:grid-cols-5">
                        <button type="button" @click="showEditor()"
                                class="flex h-[69px] w-[69px] flex-col items-center justify-center avatar rounded-full border-2 border-dashed
                                       border-gray-900 text-gray-900 transition hover:bg-gray-100
                                       dark:border-gray-300 dark:text-gray-100 dark:hover:bg-gray-700">
                            <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/>
                            </svg>
                            <span class="mt-0.5 text-[10px] font-medium">{{ __('Add new') }}</span>
                        </button>

                        <template x-for="image in images" :key="image.id">
                            <button type="button" @click="choose(image)"
                                    class="h-[69px] w-[69px] overflow-hidden avatar rounded-full ring-2 ring-transparent transition hover:ring-gray-400"
                                    :class="selectedId === image.id ? '!ring-gray-900 dark:!ring-white' : ''">
                                <img :src="image.url" alt="" class="h-[69px] w-[69px] object-cover">
                            </button>
                        </template>
                    </div>

                    <p x-show="loading" class="mt-3 text-sm text-gray-500">{{ __('Loading...') }}</p>
                    <p x-show="!loading && images.length === 0" class="mt-4 text-center text-sm text-gray-500">
                        {{ __('No images yet — add your first one.') }}
                    </p>
                </div>
            </div>

            <!-- Editor mode -->
            <div x-show="mode === 'editor'" class="flex min-h-0 flex-1 flex-col">
                <input type="file" accept="image/*" x-ref="fileInput" @change="pickFile($event)"
                       class="mb-3 block w-full cursor-pointer rounded-md border border-gray-200 p-2 text-sm text-gray-600
                              file:mr-3 file:rounded-md file:border-0 file:bg-gray-900 file:px-3 file:py-1.5 file:text-sm
                              file:font-medium file:text-white dark:border-gray-700 dark:text-gray-300
                              dark:file:bg-white dark:file:text-gray-900">

                <div x-show="hasFile" class="image-cropper h-[280px] overflow-hidden rounded-lg bg-gray-100 sm:h-[360px] dark:bg-gray-900">
                    <img x-ref="editorImage" alt="" class="block max-w-full" style="max-height: 100%;">
                </div>

                <div x-show="hasFile" class="mt-3 flex flex-wrap items-center gap-2">
                    <x-ui.button type="button" variant="outline" class="!h-9 !w-9 !px-0" @click="zoom(-0.1)">&minus;</x-ui.button>
                    <x-ui.button type="button" variant="outline" class="!h-9 !w-9 !px-0" @click="zoom(0.1)">+</x-ui.button>
                    <span class="text-xs text-gray-500">{{ __('Drag to reposition · fixed 69x69 circle') }}</span>
                </div>

                <div class="mt-4 flex items-center justify-between gap-2">
                    <x-ui.button type="button" variant="ghost" @click="showGrid()">{{ __('Back') }}</x-ui.button>
                    <x-ui.button type="button" x-show="hasFile" ::disabled="saving" @click="done()">
                        <span x-text="saving ? '{{ __('Uploading...') }}' : '{{ __('Done') }}'"></span>
                    </x-ui.button>
                </div>
            </div>
        </div>
    </div>
</div>
