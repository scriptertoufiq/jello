@props(['attachment', 'mode' => null])

{{-- The card modal deletes through Livewire; the card page posts a form. --}}
@if ($mode === 'livewire')
    <flux:button
        variant="ghost"
        size="xs"
        icon="trash"
        wire:click="deleteAttachment({{ $attachment->id }})"
        :aria-label="__('Remove attachment')"
        data-test="attachment-delete-button"
    />
@elseif ($mode === 'form')
    <form method="POST" action="{{ route('attachments.destroy', ['attachment' => $attachment]) }}">
        @csrf
        @method('DELETE')

        <flux:button
            type="submit"
            variant="ghost"
            size="xs"
            icon="trash"
            :aria-label="__('Remove attachment')"
            data-test="attachment-delete-button"
        />
    </form>
@endif
