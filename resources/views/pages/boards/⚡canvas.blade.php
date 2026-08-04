<?php

use App\Enums\TeamPermission;
use App\Models\Board;
use App\Models\BoardList;
use App\Models\Card;
use App\Services\Boards\BoardListService;
use App\Services\Cards\CardService;
use Flux\Flux;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\Computed;
use Livewire\Attributes\On;
use Livewire\Component;

new class extends Component {
    public Board $board;

    public string $newListName = '';

    /**
     * New card titles, keyed by the list they belong to.
     *
     * @var array<int, string>
     */
    public array $newCardTitle = [];

    public function mount(Board $board): void
    {
        $this->board = $board;
    }

    public function addList(BoardListService $lists): void
    {
        Gate::authorize('create', [BoardList::class, $this->board]);

        $validated = $this->validate([
            'newListName' => ['required', 'string', 'max:255'],
        ]);

        $lists->create($this->board, $validated['newListName']);

        $this->reset('newListName');

        unset($this->lists);
    }

    public function renameList(int $listId, string $name, BoardListService $lists): void
    {
        $list = $this->listOnBoard($listId);

        Gate::authorize('update', $list);

        $name = trim($name);

        if ($name === '') {
            return;
        }

        $lists->rename($list, mb_substr($name, 0, 255));

        unset($this->lists);
    }

    public function archiveList(int $listId, BoardListService $lists): void
    {
        $list = $this->listOnBoard($listId);

        Gate::authorize('update', $list);

        $lists->archive($list);

        unset($this->lists);

        Flux::toast(variant: 'success', text: __('List archived.'));
    }

    public function deleteList(int $listId, BoardListService $lists): void
    {
        $list = $this->listOnBoard($listId);

        Gate::authorize('delete', $list);

        $lists->delete($list);

        unset($this->lists);

        Flux::toast(variant: 'success', text: __('List deleted.'));
    }

    public function moveList(int $listId, int $index, BoardListService $lists): void
    {
        $list = $this->listOnBoard($listId);

        Gate::authorize('reorder', $list);

        $lists->moveToIndex($list, max($index, 0));

        unset($this->lists);
    }

    public function addCard(int $listId, CardService $cards): void
    {
        $list = $this->listOnBoard($listId);

        Gate::authorize('create', [Card::class, $list]);

        $title = trim($this->newCardTitle[$listId] ?? '');

        $this->validateOnly("newCardTitle.{$listId}", [
            "newCardTitle.{$listId}" => ['required', 'string', 'max:255'],
        ]);

        $cards->create($list, Auth::user(), mb_substr($title, 0, 255));

        $this->newCardTitle[$listId] = '';

        unset($this->lists);
    }

    /**
     * Move a card into a list at a given index.
     *
     * Called by the drag-and-drop handler and usable directly from tests.
     */
    public function moveCard(int $cardId, int $listId, int $index, CardService $cards): void
    {
        $card = $this->cardOnBoard($cardId);
        $destination = $this->listOnBoard($listId);

        Gate::authorize('move', $card);

        $cards->move($card, $destination, max($index, 0));

        unset($this->lists);
    }

    public function toggleCardComplete(int $cardId, CardService $cards): void
    {
        $card = $this->cardOnBoard($cardId);

        Gate::authorize('update', $card);

        $cards->toggleComplete($card);

        unset($this->lists);
    }

    public function archiveCard(int $cardId, CardService $cards): void
    {
        $card = $this->cardOnBoard($cardId);

        Gate::authorize('archive', $card);

        $cards->archive($card);

        unset($this->lists);

        Flux::toast(variant: 'success', text: __('Card archived.'));
    }

    /**
     * Re-read the board after the card modal changes something.
     */
    #[On('card-saved')]
    public function refreshLists(): void
    {
        unset($this->lists);
    }

    /**
     * Get the board's lists with their cards, ready to render.
     *
     * @return Collection<int, BoardList>
     */
    #[Computed]
    public function lists(): Collection
    {
        return $this->board->lists()
            ->active()
            ->with(['cards' => fn ($query) => $query->active()])
            ->get();
    }

    #[Computed]
    public function canCreateList(): bool
    {
        return Gate::allows('create', [BoardList::class, $this->board]);
    }

    /**
     * Whether drag handles should be active for lists.
     */
    #[Computed]
    public function canReorderLists(): bool
    {
        $list = $this->lists->first();

        return $list !== null && Gate::allows('reorder', $list);
    }

    /**
     * Whether cards should be draggable within and between lists.
     */
    #[Computed]
    public function canMoveCards(): bool
    {
        return $this->board->team !== null
            && Auth::user()->hasTeamPermission($this->board->team, TeamPermission::MoveCard);
    }

    /**
     * Resolve a list, ensuring it belongs to this board.
     */
    private function listOnBoard(int $listId): BoardList
    {
        return $this->board->lists()->whereKey($listId)->firstOrFail();
    }

    /**
     * Resolve a card, ensuring it belongs to this board.
     */
    private function cardOnBoard(int $cardId): Card
    {
        return Card::query()->onBoard($this->board)->whereKey($cardId)->firstOrFail();
    }
}; ?>

<div wire:poll.10s class="h-full" data-test="board-canvas">
    <div
        @if ($this->canReorderLists) wire:sort="moveList($item, $position)" @endif
        class="flex h-full items-start gap-4 overflow-x-auto pb-4"
    >
        @foreach ($this->lists as $list)
            <div
                wire:key="list-{{ $list->id }}"
                wire:sort:item="{{ $list->id }}"
                class="flex w-72 shrink-0 flex-col rounded-xl bg-zinc-100 dark:bg-zinc-900"
                data-test="board-list"
            >
                <div
                    @if ($this->canReorderLists) wire:sort:handle @endif
                    @class([
                        'flex items-center justify-between gap-2 px-3 pt-3',
                        'cursor-grab active:cursor-grabbing' => $this->canReorderLists,
                    ])
                >
                    <div class="flex min-w-0 items-center gap-2">
                        <span class="truncate text-sm font-semibold text-zinc-900 dark:text-zinc-100">{{ $list->name }}</span>
                        <flux:badge size="sm" color="zinc">{{ $list->cards->count() }}</flux:badge>
                    </div>

                    @canany(['update', 'delete'], $list)
                        <flux:dropdown position="bottom" align="end" wire:sort:ignore>
                            <flux:button variant="ghost" size="xs" icon="ellipsis-horizontal" data-test="list-menu-button" />

                            <flux:menu>
                                @can('update', $list)
                                    <flux:menu.item
                                        icon="archive-box"
                                        wire:click="archiveList({{ $list->id }})"
                                        data-test="list-archive-button"
                                    >
                                        {{ __('Archive list') }}
                                    </flux:menu.item>
                                @endcan

                                @can('delete', $list)
                                    <flux:menu.item
                                        variant="danger"
                                        icon="trash"
                                        wire:click="deleteList({{ $list->id }})"
                                        wire:confirm="{{ __('Delete this list and all of its cards?') }}"
                                        data-test="list-delete-button"
                                    >
                                        {{ __('Delete list') }}
                                    </flux:menu.item>
                                @endcan
                            </flux:menu>
                        </flux:dropdown>
                    @endcanany
                </div>

                <div
                    @if ($this->canMoveCards)
                        wire:sort:group="cards"
                        wire:sort="moveCard($item, {{ $list->id }}, $position)"
                    @endif
                    class="flex min-h-4 flex-col gap-2 p-3"
                    data-test="list-cards"
                >
                    @foreach ($list->cards as $card)
                        <div
                            wire:key="card-{{ $card->id }}"
                            wire:sort:item="{{ $card->id }}"
                            @class([
                                'group rounded-lg border border-zinc-200 bg-white p-3 shadow-sm dark:border-zinc-700 dark:bg-zinc-800',
                                'cursor-grab active:cursor-grabbing' => $this->canMoveCards,
                            ])
                            data-test="board-card"
                        >
                            <div class="flex items-start gap-2">
                                <button
                                    type="button"
                                    wire:sort:ignore
                                    wire:click="toggleCardComplete({{ $card->id }})"
                                    class="mt-0.5 shrink-0 text-zinc-400 hover:text-zinc-600 dark:hover:text-zinc-200"
                                    aria-label="{{ $card->isComplete() ? __('Reopen card') : __('Mark card complete') }}"
                                    data-test="card-complete-toggle"
                                >
                                    @if ($card->isComplete())
                                        <flux:icon.check-circle variant="solid" class="size-5 text-green-600" />
                                    @else
                                        <flux:icon.check-circle class="size-5" />
                                    @endif
                                </button>

                                <div class="min-w-0 flex-1">
                                    {{-- Flux opens the modal on the click; Livewire fills it in. --}}
                                    <flux:modal.trigger name="card-detail">
                                        <button
                                            type="button"
                                            wire:sort:ignore
                                            wire:click="$dispatch('open-card', { cardId: {{ $card->id }} })"
                                            @class([
                                                'block w-full text-left text-sm text-zinc-900 hover:underline dark:text-zinc-100',
                                                'line-through opacity-60' => $card->isComplete(),
                                            ])
                                            data-test="card-link"
                                        >
                                            {{ $card->title }}
                                        </button>
                                    </flux:modal.trigger>

                                    @if ($card->due_date)
                                        <flux:badge
                                            size="sm"
                                            :color="$card->isOverdue() ? 'red' : 'zinc'"
                                            class="mt-2"
                                            icon="clock"
                                        >
                                            {{ $card->due_date->format('M j') }}
                                        </flux:badge>
                                    @endif
                                </div>

                                @can('archive', $card)
                                    <flux:button
                                        variant="ghost"
                                        size="xs"
                                        icon="archive-box"
                                        class="opacity-0 transition group-hover:opacity-100"
                                        wire:sort:ignore
                                        wire:click="archiveCard({{ $card->id }})"
                                        data-test="card-archive-button"
                                    />
                                @endcan
                            </div>
                        </div>
                    @endforeach
                </div>

                @can('create', [App\Models\Card::class, $list])
                    <div class="px-3 pb-3">
                        <form wire:submit="addCard({{ $list->id }})">
                            <flux:input
                                wire:model="newCardTitle.{{ $list->id }}"
                                :placeholder="__('Add a card…')"
                                size="sm"
                                data-test="add-card-input"
                            />
                            <flux:error name="newCardTitle.{{ $list->id }}" />
                        </form>
                    </div>
                @endcan
            </div>
        @endforeach

        @if ($this->canCreateList)
            <div wire:sort:ignore class="w-72 shrink-0 rounded-xl bg-zinc-100 p-3 dark:bg-zinc-900">
                <form wire:submit="addList">
                    <flux:input
                        wire:model="newListName"
                        :placeholder="__('Add a list…')"
                        size="sm"
                        data-test="add-list-input"
                    />
                    <flux:error name="newListName" />
                </form>
            </div>
        @endif

        @if ($this->lists->isEmpty() && ! $this->canCreateList)
            <div class="flex w-full items-center justify-center rounded-xl border border-dashed border-zinc-300 py-16 dark:border-zinc-700">
                <flux:text class="text-zinc-500 dark:text-zinc-400">{{ __('This board has no lists yet.') }}</flux:text>
            </div>
        @endif
    </div>
</div>
