<?php

use App\Models\Attachment;
use App\Models\Board;
use App\Models\Card;
use App\Models\Checklist;
use App\Models\ChecklistItem;
use App\Models\Comment;
use App\Models\Label;
use App\Models\User;
use App\Services\Cards\AttachmentService;
use App\Services\Cards\CardAssignmentService;
use App\Services\Cards\CardService;
use App\Services\Cards\ChecklistService;
use App\Services\Cards\CommentService;
use Flux\Flux;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\Computed;
use Livewire\Attributes\On;
use Livewire\Component;
use Livewire\WithFileUploads;

/**
 * The card detail view, shown over the board rather than on its own page.
 *
 * Lives outside the canvas component so the canvas's polling cannot re-render
 * the modal while someone is typing in it.
 */
new class extends Component {
    use WithFileUploads;

    public Board $board;

    public ?int $cardId = null;

    public string $title = '';

    public string $description = '';

    public string $startDate = '';

    public string $dueDate = '';

    public string $commentBody = '';

    public string $newChecklistName = '';

    /**
     * New checklist item text, keyed by checklist id.
     *
     * @var array<int, string>
     */
    public array $newChecklistItem = [];

    public $attachment = null;

    public function mount(Board $board): void
    {
        $this->board = $board;
    }

    /**
     * Load a card into the modal.
     *
     * Flux opens the modal on the click itself; this fills it in.
     */
    #[On('open-card')]
    public function openCard(int $cardId): void
    {
        $card = $this->resolveCard($cardId);

        $this->cardId = $card->id;
        $this->title = $card->title;
        $this->description = (string) $card->description;
        $this->startDate = $card->start_date?->format('Y-m-d') ?? '';
        $this->dueDate = $card->due_date?->format('Y-m-d\TH:i') ?? '';

        $this->reset('commentBody', 'newChecklistName', 'newChecklistItem', 'attachment');
        $this->resetValidation();

        unset($this->card);
    }

    public function saveCard(CardService $cards): void
    {
        $card = $this->currentCard();

        Gate::authorize('update', $card);

        $validated = $this->validate([
            'title' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:20000'],
            'startDate' => ['nullable', 'date'],
            'dueDate' => ['nullable', 'date', 'after_or_equal:startDate'],
        ]);

        $cards->update($card, [
            'title' => $validated['title'],
            'description' => $validated['description'] ?: null,
            'start_date' => $validated['startDate'] ?: null,
            'due_date' => $validated['dueDate'] ?: null,
        ]);

        $this->refreshBoard();

        Flux::toast(variant: 'success', text: __('Card updated.'));
    }

    public function toggleComplete(CardService $cards): void
    {
        $card = $this->currentCard();

        Gate::authorize('update', $card);

        $cards->toggleComplete($card);

        $this->refreshBoard();
    }

    public function archiveCard(CardService $cards): void
    {
        $card = $this->currentCard();

        Gate::authorize('archive', $card);

        $cards->archive($card);

        $this->closeModal();

        Flux::toast(variant: 'success', text: __('Card archived.'));
    }

    public function addComment(CommentService $comments): void
    {
        $card = $this->currentCard();

        Gate::authorize('create', [Comment::class, $card]);

        $validated = $this->validate([
            'commentBody' => ['required', 'string', 'max:5000'],
        ]);

        $comments->create($card, Auth::user(), $validated['commentBody']);

        $this->reset('commentBody');

        unset($this->card);
    }

    public function deleteComment(int $commentId, CommentService $comments): void
    {
        $comment = $this->currentCard()->comments()->findOrFail($commentId);

        Gate::authorize('delete', $comment);

        $comments->delete($comment);

        unset($this->card);
    }

    public function addChecklist(ChecklistService $checklists): void
    {
        $card = $this->currentCard();

        Gate::authorize('create', [Checklist::class, $card]);

        $validated = $this->validate([
            'newChecklistName' => ['required', 'string', 'max:255'],
        ]);

        $checklists->create($card, $validated['newChecklistName']);

        $this->reset('newChecklistName');

        unset($this->card);
    }

    public function deleteChecklist(int $checklistId, ChecklistService $checklists): void
    {
        $checklist = $this->currentCard()->checklists()->findOrFail($checklistId);

        Gate::authorize('delete', $checklist);

        $checklists->delete($checklist);

        unset($this->card);
    }

    public function addChecklistItem(int $checklistId, ChecklistService $checklists): void
    {
        $checklist = $this->currentCard()->checklists()->findOrFail($checklistId);

        Gate::authorize('update', $checklist);

        $content = trim($this->newChecklistItem[$checklistId] ?? '');

        if ($content === '') {
            return;
        }

        $checklists->addItem($checklist, mb_substr($content, 0, 500));

        $this->newChecklistItem[$checklistId] = '';

        unset($this->card);
    }

    public function toggleChecklistItem(int $itemId, ChecklistService $checklists): void
    {
        $item = ChecklistItem::whereHas(
            'checklist',
            fn ($query) => $query->where('card_id', $this->currentCard()->id),
        )->findOrFail($itemId);

        Gate::authorize('update', $item->checklist);

        $checklists->toggleItem($item);

        unset($this->card);
    }

    public function toggleLabel(int $labelId, CardAssignmentService $assignments): void
    {
        $card = $this->currentCard();

        Gate::authorize('update', $card);

        $label = $this->board->labels()->findOrFail($labelId);

        $assignments->toggleLabel($card, $label);

        $this->refreshBoard();
    }

    public function assign(int $userId, CardAssignmentService $assignments): void
    {
        $card = $this->currentCard();

        Gate::authorize('update', $card);

        $user = $this->board->team->members()->findOrFail($userId);

        $assignments->assign($card, $user, Auth::user());

        $this->refreshBoard();
    }

    public function unassign(int $userId, CardAssignmentService $assignments): void
    {
        $card = $this->currentCard();

        Gate::authorize('update', $card);

        $user = User::findOrFail($userId);

        $assignments->unassign($card, $user, Auth::user());

        $this->refreshBoard();
    }

    public function uploadAttachment(AttachmentService $attachments): void
    {
        $card = $this->currentCard();

        Gate::authorize('create', [Attachment::class, $card]);

        $this->validate([
            'attachment' => ['required', 'file', 'max:'.config('boards.attachments.max_kilobytes')],
        ]);

        $attachments->store($card, Auth::user(), $this->attachment);

        $this->reset('attachment');

        unset($this->card);
    }

    public function deleteAttachment(int $attachmentId, AttachmentService $attachments): void
    {
        $attachment = $this->currentCard()->attachments()->findOrFail($attachmentId);

        Gate::authorize('delete', $attachment);

        $attachments->delete($attachment);

        unset($this->card);
    }

    /**
     * Get the card currently shown, with everything the modal renders.
     */
    #[Computed]
    public function card(): ?Card
    {
        if ($this->cardId === null) {
            return null;
        }

        return Card::query()
            ->onBoard($this->board)
            ->with([
                'list',
                'labels',
                'assignees',
                'checklists.items',
                'comments.author',
                'attachments',
                'activities.user',
            ])
            ->find($this->cardId);
    }

    /**
     * Get the labels defined on this board.
     *
     * @return Collection<int, Label>
     */
    #[Computed]
    public function boardLabels(): Collection
    {
        return $this->board->labels()->get();
    }

    /**
     * Get the workspace members who are not yet assigned to this card.
     *
     * @return Collection<int, User>
     */
    #[Computed]
    public function assignableMembers(): Collection
    {
        $assigned = $this->card?->assignees->pluck('id') ?? collect();

        return $this->board->team->members()
            ->whereNotIn('users.id', $assigned)
            ->orderBy('name')
            ->get();
    }

    /**
     * Resolve a card id to a card on this board, or fail.
     */
    private function resolveCard(int $cardId): Card
    {
        $card = Card::query()->onBoard($this->board)->whereKey($cardId)->firstOrFail();

        Gate::authorize('view', $card);

        return $card;
    }

    /**
     * Get the card the modal is currently working with.
     */
    private function currentCard(): Card
    {
        abort_if($this->cardId === null, 404);

        return $this->resolveCard($this->cardId);
    }

    /**
     * Tell the canvas to re-read its lists.
     */
    private function refreshBoard(): void
    {
        unset($this->card);

        $this->dispatch('card-saved');
    }

    private function closeModal(): void
    {
        $this->cardId = null;

        $this->dispatch('card-saved');

        // Livewire dispatches browser events on `window`, but Flux's modal
        // listens on `document`, which a window event never reaches. The view
        // bridges this by re-dispatching from an element so it bubbles.
        $this->dispatch('close-card-modal');
    }
}; ?>

<flux:modal
    name="card-detail"
    class="w-full max-w-3xl"
    x-on:close-card-modal.window="$dispatch('modal-close', { name: 'card-detail' })"
    data-test="card-modal"
>
    @php($card = $this->card)

    @if ($card === null)
        <div class="flex items-center justify-center py-16">
            <flux:text class="text-zinc-500 dark:text-zinc-400">{{ __('Loading card…') }}</flux:text>
        </div>
    @else
        @php($labelColors = config('boards.label_colors'))

        <div class="space-y-6">
            {{--
                Flux renders its own close button absolutely at `top-0 end-0
                mt-4 me-4`, so the header keeps a right inset to stop these
                controls colliding with it.
            --}}
            <div class="flex items-start justify-between gap-4 pe-12">
                <div class="min-w-0">
                    <flux:heading size="lg" class="break-words" data-test="card-modal-title">{{ $card->title }}</flux:heading>
                    <flux:subheading>{{ __('in :list', ['list' => $card->list->name]) }}</flux:subheading>
                </div>

                <div class="flex shrink-0 items-center gap-2">
                    @can('update', $card)
                        <flux:button
                            size="sm"
                            :variant="$card->isComplete() ? 'filled' : 'primary'"
                            :icon="$card->isComplete() ? 'arrow-uturn-left' : 'check'"
                            wire:click="toggleComplete"
                            data-test="card-modal-complete"
                        >
                            {{ $card->isComplete() ? __('Reopen') : __('Complete') }}
                        </flux:button>
                    @endcan

                    <flux:tooltip :content="__('Open as a full page')" position="bottom">
                        <flux:button
                            size="sm"
                            variant="ghost"
                            icon="arrow-top-right-on-square"
                            :href="route('cards.show', ['card' => $card])"
                            wire:navigate
                            :aria-label="__('Open as a full page')"
                            data-test="card-modal-open-page"
                        />
                    </flux:tooltip>
                </div>
            </div>

            <div class="grid gap-6 lg:grid-cols-3">
                <div class="space-y-6 lg:col-span-2">
                    @can('update', $card)
                        <form wire:submit="saveCard" class="space-y-4">
                            <flux:input wire:model="title" :label="__('Title')" required data-test="card-modal-title-input" />

                            <flux:textarea
                                wire:model="description"
                                :label="__('Description')"
                                rows="4"
                                :placeholder="__('Add more detail…')"
                                data-test="card-modal-description-input"
                            />

                            <div class="grid gap-4 sm:grid-cols-2">
                                <flux:field>
                                    <flux:label>{{ __('Start date') }}</flux:label>
                                    <flux:input type="date" wire:model="startDate" data-test="card-modal-start-date" />
                                    <flux:error name="startDate" />
                                </flux:field>

                                <flux:field>
                                    <flux:label>{{ __('Due date') }}</flux:label>
                                    <flux:input type="datetime-local" wire:model="dueDate" data-test="card-modal-due-date" />
                                    <flux:error name="dueDate" />
                                </flux:field>
                            </div>

                            <flux:button variant="primary" size="sm" type="submit" data-test="card-modal-save">
                                {{ __('Save card') }}
                            </flux:button>
                        </form>
                    @else
                        <flux:text class="whitespace-pre-line">{{ $card->description ?: __('No description.') }}</flux:text>
                    @endcan

                    {{-- Checklists --}}
                    <div>
                        <flux:heading size="sm">{{ __('Checklists') }}</flux:heading>

                        <div class="mt-3 space-y-4">
                            @forelse ($card->checklists as $checklist)
                                <div wire:key="checklist-{{ $checklist->id }}" data-test="card-modal-checklist">
                                    <div class="flex items-center justify-between gap-2">
                                        <span class="text-sm font-medium">{{ $checklist->name }}</span>

                                        <div class="flex items-center gap-2">
                                            <flux:badge size="sm" color="zinc">
                                                {{ $checklist->completedCount() }}/{{ $checklist->items->count() }}
                                            </flux:badge>

                                            @can('delete', $checklist)
                                                <flux:button
                                                    variant="ghost"
                                                    size="xs"
                                                    icon="trash"
                                                    wire:click="deleteChecklist({{ $checklist->id }})"
                                                    data-test="card-modal-checklist-delete"
                                                />
                                            @endcan
                                        </div>
                                    </div>

                                    <ul class="mt-2 space-y-1">
                                        @foreach ($checklist->items as $item)
                                            <li class="flex items-center gap-2" wire:key="item-{{ $item->id }}">
                                                @can('update', $checklist)
                                                    <button
                                                        type="button"
                                                        wire:click="toggleChecklistItem({{ $item->id }})"
                                                        class="flex items-center"
                                                        data-test="card-modal-item-toggle"
                                                    >
                                                        @if ($item->is_completed)
                                                            <flux:icon.check-circle variant="solid" class="size-5 text-green-600" />
                                                        @else
                                                            <flux:icon.check-circle class="size-5 text-zinc-400" />
                                                        @endif
                                                    </button>
                                                @endcan

                                                <span @class(['text-sm', 'line-through opacity-60' => $item->is_completed])>
                                                    {{ $item->content }}
                                                </span>
                                            </li>
                                        @endforeach
                                    </ul>

                                    @can('update', $checklist)
                                        <form wire:submit="addChecklistItem({{ $checklist->id }})" class="mt-2">
                                            <flux:input
                                                wire:model="newChecklistItem.{{ $checklist->id }}"
                                                size="sm"
                                                :placeholder="__('Add an item…')"
                                                data-test="card-modal-item-input"
                                            />
                                        </form>
                                    @endcan
                                </div>
                            @empty
                                <flux:text class="text-sm text-zinc-500 dark:text-zinc-400">{{ __('No checklists yet.') }}</flux:text>
                            @endforelse
                        </div>

                        @can('create', [App\Models\Checklist::class, $card])
                            <form wire:submit="addChecklist" class="mt-3">
                                <flux:input
                                    wire:model="newChecklistName"
                                    size="sm"
                                    :placeholder="__('Add a checklist…')"
                                    data-test="card-modal-checklist-input"
                                />
                            </form>
                        @endcan
                    </div>

                    {{-- Comments --}}
                    <div>
                        <flux:heading size="sm">{{ __('Comments') }}</flux:heading>

                        @can('create', [App\Models\Comment::class, $card])
                            <form wire:submit="addComment" class="mt-3 space-y-2">
                                <flux:textarea
                                    wire:model="commentBody"
                                    rows="2"
                                    :placeholder="__('Write a comment…')"
                                    data-test="card-modal-comment-input"
                                />
                                <flux:button variant="primary" size="sm" type="submit" data-test="card-modal-comment-submit">
                                    {{ __('Comment') }}
                                </flux:button>
                            </form>
                        @endcan

                        <div class="mt-4 space-y-3">
                            @forelse ($card->comments as $comment)
                                <div class="flex gap-2" wire:key="comment-{{ $comment->id }}" data-test="card-modal-comment">
                                    <flux:avatar size="xs" :name="$comment->author->name" />

                                    <div class="min-w-0 flex-1">
                                        <div class="flex items-center gap-2">
                                            <span class="text-sm font-medium">{{ $comment->author->name }}</span>
                                            <flux:text class="text-xs text-zinc-500">{{ $comment->created_at?->diffForHumans() }}</flux:text>
                                        </div>
                                        <flux:text class="whitespace-pre-line text-sm">{{ $comment->body }}</flux:text>
                                    </div>

                                    @can('delete', $comment)
                                        <flux:button
                                            variant="ghost"
                                            size="xs"
                                            icon="trash"
                                            wire:click="deleteComment({{ $comment->id }})"
                                            data-test="card-modal-comment-delete"
                                        />
                                    @endcan
                                </div>
                            @empty
                                <flux:text class="text-sm text-zinc-500 dark:text-zinc-400">{{ __('No comments yet.') }}</flux:text>
                            @endforelse
                        </div>
                    </div>
                </div>

                {{-- Sidebar --}}
                <div class="space-y-5">
                    <div>
                        <flux:heading size="sm">{{ __('Assignees') }}</flux:heading>

                        <div class="mt-2 space-y-1">
                            @forelse ($card->assignees as $assignee)
                                <div class="flex items-center justify-between gap-2" wire:key="assignee-{{ $assignee->id }}" data-test="card-modal-assignee">
                                    <div class="flex items-center gap-2">
                                        <flux:avatar size="xs" :name="$assignee->name" />
                                        <span class="text-sm">{{ $assignee->name }}</span>
                                    </div>

                                    @can('update', $card)
                                        <flux:button
                                            variant="ghost"
                                            size="xs"
                                            icon="x-mark"
                                            wire:click="unassign({{ $assignee->id }})"
                                            data-test="card-modal-unassign"
                                        />
                                    @endcan
                                </div>
                            @empty
                                <flux:text class="text-sm text-zinc-500 dark:text-zinc-400">{{ __('Nobody assigned.') }}</flux:text>
                            @endforelse
                        </div>

                        @can('update', $card)
                            <flux:dropdown position="bottom" align="start" class="mt-2">
                                <flux:button size="xs" variant="ghost" icon="plus" data-test="card-modal-assign-menu">
                                    {{ __('Assign') }}
                                </flux:button>

                                <flux:menu>
                                    @foreach ($this->assignableMembers as $member)
                                        <flux:menu.item wire:click="assign({{ $member->id }})" data-test="card-modal-assign-option">
                                            {{ $member->name }}
                                        </flux:menu.item>
                                    @endforeach
                                </flux:menu>
                            </flux:dropdown>
                        @endcan
                    </div>

                    <div>
                        <flux:heading size="sm">{{ __('Labels') }}</flux:heading>

                        <div class="mt-2 flex flex-wrap gap-1.5">
                            @foreach ($this->boardLabels as $label)
                                @php($applied = $card->labels->contains($label))

                                <button
                                    type="button"
                                    wire:click="toggleLabel({{ $label->id }})"
                                    wire:key="label-{{ $label->id }}"
                                    @class([
                                        'flex items-center gap-1 rounded px-2 py-1 text-xs text-white transition',
                                        $labelColors[$label->color] ?? 'bg-zinc-500',
                                        'opacity-40 hover:opacity-70' => ! $applied,
                                    ])
                                    data-test="card-modal-label"
                                >
                                    @if ($applied)
                                        <flux:icon.check variant="micro" />
                                    @endif
                                    {{ $label->name }}
                                </button>
                            @endforeach
                        </div>
                    </div>

                    <div>
                        <flux:heading size="sm">{{ __('Attachments') }}</flux:heading>

                        <div class="mt-2" data-test="card-modal-attachment">
                            <x-attachment-list :attachments="$card->attachments" delete-mode="livewire" />
                        </div>

                        @can('create', [App\Models\Attachment::class, $card])
                            <form wire:submit="uploadAttachment" class="mt-2 space-y-2">
                                <flux:input type="file" wire:model="attachment" size="sm" data-test="card-modal-attachment-input" />
                                <flux:error name="attachment" />

                                <flux:button size="xs" variant="filled" type="submit" data-test="card-modal-attachment-submit">
                                    {{ __('Upload') }}
                                </flux:button>
                            </form>
                        @endcan
                    </div>

                    <div>
                        <flux:heading size="sm">{{ __('Activity') }}</flux:heading>

                        <ul class="mt-2 max-h-48 space-y-2 overflow-y-auto">
                            @forelse ($card->activities as $activity)
                                <li class="text-xs" wire:key="activity-{{ $activity->id }}" data-test="card-modal-activity">
                                    <flux:text class="text-zinc-700 dark:text-zinc-300">{{ $activity->describe() }}</flux:text>
                                    <flux:text class="text-zinc-500">{{ $activity->created_at?->diffForHumans() }}</flux:text>
                                </li>
                            @empty
                                <li><flux:text class="text-xs text-zinc-500">{{ __('Nothing yet.') }}</flux:text></li>
                            @endforelse
                        </ul>
                    </div>

                    @can('archive', $card)
                        <flux:button
                            variant="ghost"
                            size="sm"
                            icon="archive-box"
                            class="w-full"
                            wire:click="archiveCard"
                            data-test="card-modal-archive"
                        >
                            {{ __('Archive card') }}
                        </flux:button>
                    @endcan
                </div>
            </div>
        </div>
    @endif
</flux:modal>
