@props(['card'])

@php($labelColors = config('boards.label_colors'))

<a
    href="{{ route('cards.show', ['card' => $card]) }}"
    wire:navigate
    class="flex flex-wrap items-center justify-between gap-3 rounded-lg border border-zinc-200 p-3 transition hover:border-zinc-300 hover:bg-zinc-50 dark:border-zinc-700 dark:hover:border-zinc-600 dark:hover:bg-zinc-800/50"
    data-test="card-row"
>
    <div class="min-w-0 flex-1">
        <div class="flex items-center gap-2">
            @if ($card->isComplete())
                <flux:icon.check-circle variant="solid" class="size-4 shrink-0 text-green-600" />
            @endif

            <span @class([
                'truncate text-sm font-medium text-zinc-900 dark:text-zinc-100',
                'line-through opacity-60' => $card->isComplete(),
            ])>
                {{ $card->title }}
            </span>
        </div>

        <flux:text class="mt-1 text-xs text-zinc-500 dark:text-zinc-400">
            {{ $card->list->board->name }} &middot; {{ $card->list->name }}
        </flux:text>
    </div>

    <div class="flex shrink-0 flex-wrap items-center gap-2">
        @foreach ($card->labels as $label)
            <span class="rounded px-1.5 py-0.5 text-[11px] text-white {{ $labelColors[$label->color] ?? 'bg-zinc-500' }}">
                {{ $label->name }}
            </span>
        @endforeach

        @if ($card->due_date)
            <flux:badge size="sm" :color="$card->isOverdue() ? 'red' : 'zinc'" icon="clock">
                {{ $card->due_date->format('M j') }}
            </flux:badge>
        @endif

        @foreach ($card->assignees as $assignee)
            <flux:tooltip :content="$assignee->name">
                <flux:avatar size="xs" :name="$assignee->name" />
            </flux:tooltip>
        @endforeach
    </div>
</a>
