<?php

use App\Models\Board;
use App\Models\Card;
use App\Services\Cards\CardService;
use App\Services\Search\BoardSearchService;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Collection as SupportCollection;
use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\Computed;
use Livewire\Attributes\On;
use Livewire\Attributes\Url;
use Livewire\Component;

/**
 * A flat, sortable list of every card on a board.
 *
 * An alternative to the Kanban canvas for scanning work by due date or title
 * rather than by column. Opens the same card modal the canvas does.
 */
new class extends Component {
    public Board $board;

    /**
     * Sort mode: manual keeps the board's own ordering and groups by list.
     */
    #[Url(as: 'sort', keep: false)]
    public string $sort = 'manual';

    #[Url(as: 'hide-done', keep: false)]
    public bool $hideCompleted = false;

    public function mount(Board $board): void
    {
        $this->board = $board;
    }

    public function toggleComplete(int $cardId, CardService $cards): void
    {
        $card = Card::query()->onBoard($this->board)->whereKey($cardId)->firstOrFail();

        Gate::authorize('update', $card);

        $cards->toggleComplete($card);

        unset($this->cards);

        $this->dispatch('card-saved');
    }

    /**
     * Re-read the board when the card modal changes something.
     */
    #[On('card-saved')]
    public function refreshCards(): void
    {
        unset($this->cards);
    }

    /**
     * Get the cards to render.
     *
     * @return Collection<int, Card>
     */
    #[Computed]
    public function cards(): Collection
    {
        return app(BoardSearchService::class)->forBoard(
            $this->board,
            in_array($this->sort, ['manual', 'due', 'title'], true) ? $this->sort : 'manual',
            $this->hideCompleted,
        );
    }

    /**
     * Whether rows should be grouped under their list heading.
     */
    #[Computed]
    public function grouped(): bool
    {
        return $this->sort === 'manual';
    }

    /**
     * Get the cards grouped into sections for rendering.
     *
     * In board order each list becomes its own section; the flat sorts return
     * a single unnamed section.
     *
     * @return SupportCollection<int|string, SupportCollection<int, Card>>
     */
    #[Computed]
    public function sections(): SupportCollection
    {
        if (! $this->grouped) {
            return collect(['' => collect($this->cards->all())]);
        }

        return collect($this->cards->all())
            ->groupBy(fn (Card $card): string => $card->list->name);
    }
}; ?>


<div wire:poll.15s class="w-full" data-test="board-list-view">
    @php($labelColors = config('boards.label_colors'))

    {{-- Toolbar --}}
    <div class="flex flex-wrap items-center justify-between gap-3">
        <flux:text class="text-sm text-zinc-500 dark:text-zinc-400" data-test="list-view-count">
            {{ trans_choice(':count card|:count cards', $this->cards->count(), ['count' => $this->cards->count()]) }}
        </flux:text>

        <div class="flex items-center gap-3">
            <flux:checkbox
                wire:model.live="hideCompleted"
                :label="__('Hide completed')"
                data-test="list-view-hide-completed"
            />

            <flux:select wire:model.live="sort" size="sm" class="w-44" data-test="list-view-sort">
                <flux:select.option value="manual">{{ __('Board order') }}</flux:select.option>
                <flux:select.option value="due">{{ __('Due date') }}</flux:select.option>
                <flux:select.option value="title">{{ __('Title') }}</flux:select.option>
            </flux:select>
        </div>
    </div>

    @if ($this->cards->isEmpty())
        <div class="mt-4 rounded-2xl border border-dashed border-zinc-300 py-16 text-center dark:border-zinc-700">
            <flux:text class="text-zinc-500 dark:text-zinc-400">
                {{ $this->hideCompleted
                    ? __('No open cards on this board.')
                    : __('This board has no cards yet.') }}
            </flux:text>
        </div>
    @else
        <div class="mt-4 space-y-4">
            @foreach ($this->sections as $sectionName => $sectionCards)
                <section
                    wire:key="section-{{ $loop->index }}"
                    class="overflow-hidden rounded-2xl border border-zinc-200 bg-white shadow-sm dark:border-zinc-700 dark:bg-zinc-900"
                >
                    @if ($this->grouped)
                        {{--
                            Same treatment as a board column header: a flat
                            tinted bar with a small uppercase label, clearly
                            distinct from the task rows beneath it.
                        --}}
                        @php($done = $sectionCards->filter->isComplete()->count())

                        <header
                            class="flex items-center justify-between gap-3 border-b border-zinc-200 bg-zinc-100 px-4 py-2.5 dark:border-zinc-700 dark:bg-zinc-800/70"
                            data-test="list-view-group"
                        >
                            <div class="flex min-w-0 items-center gap-2">
                                <h3 class="truncate text-xs font-bold uppercase tracking-wider text-zinc-600 dark:text-zinc-300">
                                    {{ $sectionName }}
                                </h3>

                                <span class="shrink-0 rounded-full bg-white px-2 py-0.5 text-[11px] font-semibold tabular-nums text-zinc-500 ring-1 ring-zinc-200 dark:bg-zinc-900 dark:text-zinc-400 dark:ring-zinc-700">
                                    {{ $sectionCards->count() }}
                                </span>
                            </div>

                            @if ($done > 0)
                                <flux:text class="shrink-0 text-[11px] tabular-nums text-zinc-500 dark:text-zinc-400">
                                    {{ __(':done of :total done', ['done' => $done, 'total' => $sectionCards->count()]) }}
                                </flux:text>
                            @endif
                        </header>
                    @endif

                    <ul class="divide-y divide-zinc-100 dark:divide-zinc-800">
                        @foreach ($sectionCards as $card)
                            <li
                                wire:key="row-{{ $card->id }}"
                                class="group flex items-start gap-3 px-4 py-3 transition hover:bg-zinc-50 dark:hover:bg-zinc-800/50"
                                data-test="list-view-row"
                            >
                                <div class="pt-0.5">
                                    @can('update', $card)
                                        <button
                                            type="button"
                                            wire:click="toggleComplete({{ $card->id }})"
                                            class="text-zinc-300 transition hover:text-green-600 dark:text-zinc-600 dark:hover:text-green-500"
                                            aria-label="{{ $card->isComplete() ? __('Reopen card') : __('Mark card complete') }}"
                                            data-test="list-view-complete"
                                        >
                                            @if ($card->isComplete())
                                                <flux:icon.check-circle variant="solid" class="size-5 text-green-600" />
                                            @else
                                                <flux:icon.check-circle class="size-5" />
                                            @endif
                                        </button>
                                    @else
                                        @if ($card->isComplete())
                                            <flux:icon.check-circle variant="solid" class="size-5 text-green-600" />
                                        @else
                                            <flux:icon.check-circle class="size-5 text-zinc-300 dark:text-zinc-600" />
                                        @endif
                                    @endcan
                                </div>

                                <div class="min-w-0 flex-1">
                                    <flux:modal.trigger name="card-detail">
                                        <button
                                            type="button"
                                            wire:click="$dispatch('open-card', { cardId: {{ $card->id }} })"
                                            @class([
                                                'block text-left text-sm font-medium text-zinc-900 hover:underline dark:text-zinc-100',
                                                'line-through opacity-50' => $card->isComplete(),
                                            ])
                                            data-test="list-view-card-link"
                                        >
                                            {{ $card->title }}
                                        </button>
                                    </flux:modal.trigger>

                                    @if (! $this->grouped || $card->labels->isNotEmpty())
                                        <div class="mt-1.5 flex flex-wrap items-center gap-1.5">
                                            @unless ($this->grouped)
                                                <flux:badge size="sm" color="zinc">{{ $card->list->name }}</flux:badge>
                                            @endunless

                                            @foreach ($card->labels as $label)
                                                <span class="rounded px-1.5 py-0.5 text-[11px] font-medium text-white {{ $labelColors[$label->color] ?? 'bg-zinc-500' }}">
                                                    {{ $label->name }}
                                                </span>
                                            @endforeach
                                        </div>
                                    @endif
                                </div>

                                <div class="flex shrink-0 items-center gap-3">
                                    @if ($card->due_date)
                                        <flux:badge size="sm" :color="$card->isOverdue() ? 'red' : 'zinc'" icon="clock">
                                            {{ $card->due_date->format('M j') }}
                                        </flux:badge>
                                    @endif

                                    <div class="flex -space-x-1.5">
                                        @foreach ($card->assignees as $assignee)
                                            <flux:tooltip :content="$assignee->name">
                                                <flux:avatar size="xs" :name="$assignee->name" class="ring-2 ring-white dark:ring-zinc-900" />
                                            </flux:tooltip>
                                        @endforeach
                                    </div>
                                </div>
                            </li>
                        @endforeach
                    </ul>
                </section>
            @endforeach
        </div>
    @endif
</div>
