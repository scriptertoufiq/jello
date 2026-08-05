@props(['attachments', 'deleteMode' => null])

{{--
    Attachments: images render as clickable thumbnails, everything else as a
    compact file row. Both the preview and download URLs run the attachment
    policy before serving bytes.

    `deleteMode` picks how the delete control submits: 'livewire' calls the
    card modal's action, 'form' posts from the standalone card page, and null
    hides it entirely.
--}}
@php($images = $attachments->filter->isImage())
@php($files = $attachments->reject->isImage())

<div class="space-y-3">
    @if ($images->isNotEmpty())
        <div class="grid grid-cols-2 gap-2" data-test="attachment-previews">
            @foreach ($images as $image)
                <div
                    wire:key="preview-{{ $image->id }}"
                    class="group relative overflow-hidden rounded-lg border border-zinc-200 dark:border-zinc-700"
                >
                    <a href="{{ $image->previewUrl() }}" target="_blank" rel="noopener" title="{{ $image->name }}">
                        <img
                            src="{{ $image->previewUrl() }}"
                            alt="{{ $image->name }}"
                            loading="lazy"
                            class="aspect-video w-full bg-zinc-100 object-cover transition group-hover:opacity-90 dark:bg-zinc-800"
                            data-test="attachment-preview-image"
                        >
                    </a>

                    <div class="absolute inset-x-0 bottom-0 flex items-end justify-between gap-2 bg-gradient-to-t from-black/70 to-transparent p-2">
                        <span class="truncate text-[11px] font-medium text-white" title="{{ $image->name }}">
                            {{ $image->name }}
                        </span>

                        @can('delete', $image)
                            <span class="shrink-0 opacity-0 transition group-hover:opacity-100">
                                <x-attachment-delete-button :attachment="$image" :mode="$deleteMode" />
                            </span>
                        @endcan
                    </div>
                </div>
            @endforeach
        </div>
    @endif

    @if ($files->isNotEmpty())
        <div class="space-y-1" data-test="attachment-files">
            @foreach ($files as $file)
                <div wire:key="file-{{ $file->id }}" class="flex items-center gap-2" data-test="attachment-file">
                    <flux:icon.paper-clip variant="micro" class="shrink-0 text-zinc-400" />

                    <a
                        href="{{ $file->downloadUrl() }}"
                        class="min-w-0 flex-1 truncate text-sm text-blue-600 hover:underline dark:text-blue-400"
                    >
                        {{ $file->name }}
                    </a>

                    <flux:text class="shrink-0 text-xs text-zinc-500">{{ $file->humanSize() }}</flux:text>

                    @can('delete', $file)
                        <x-attachment-delete-button :attachment="$file" :mode="$deleteMode" />
                    @endcan
                </div>
            @endforeach
        </div>
    @endif

    @if ($attachments->isEmpty())
        <flux:text class="text-sm text-zinc-500 dark:text-zinc-400">{{ __('No attachments.') }}</flux:text>
    @endif
</div>
