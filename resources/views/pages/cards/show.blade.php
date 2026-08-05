<x-layouts::app :title="$card->title">
    @php($labelColors = config('boards.label_colors'))

    <section class="mx-auto w-full max-w-4xl">
        <div class="flex flex-wrap items-center justify-between gap-3">
            <flux:breadcrumbs>
                <flux:breadcrumbs.item :href="route('boards.index')" wire:navigate>{{ __('Boards') }}</flux:breadcrumbs.item>
                <flux:breadcrumbs.item :href="route('boards.show', ['board' => $board])" wire:navigate>{{ $board->name }}</flux:breadcrumbs.item>
                <flux:breadcrumbs.item>{{ $card->list->name }}</flux:breadcrumbs.item>
            </flux:breadcrumbs>

            <div class="flex items-center gap-2">
                @can('update', $card)
                    <form method="POST" action="{{ route('cards.complete', ['card' => $card]) }}">
                        @csrf
                        <flux:button
                            type="submit"
                            size="sm"
                            :variant="$card->isComplete() ? 'filled' : 'primary'"
                            :icon="$card->isComplete() ? 'arrow-uturn-left' : 'check'"
                            data-test="card-complete-button"
                        >
                            {{ $card->isComplete() ? __('Reopen') : __('Mark complete') }}
                        </flux:button>
                    </form>
                @endcan

                @can('archive', $card)
                    <form method="POST" action="{{ route('cards.archive', ['card' => $card]) }}">
                        @csrf
                        <flux:button type="submit" size="sm" variant="ghost" icon="archive-box" data-test="card-archive-button">
                            {{ __('Archive') }}
                        </flux:button>
                    </form>
                @endcan
            </div>
        </div>

        @if (session('status'))
            <flux:callout variant="success" class="mt-4" data-test="card-status">{{ session('status') }}</flux:callout>
        @endif

        <div class="mt-6 grid gap-6 lg:grid-cols-3">
            <div class="space-y-6 lg:col-span-2">
                {{-- Title and description --}}
                <div class="rounded-xl border border-zinc-200 p-5 dark:border-zinc-700">
                    @can('update', $card)
                        <form method="POST" action="{{ route('cards.update', ['card' => $card]) }}" class="space-y-4">
                            @csrf
                            @method('PATCH')

                            <flux:input
                                name="title"
                                :label="__('Title')"
                                :value="old('title', $card->title)"
                                required
                                data-test="card-title-input"
                            />

                            <flux:textarea
                                name="description"
                                :label="__('Description')"
                                :value="old('description', $card->description)"
                                rows="5"
                                :placeholder="__('Add more detail…')"
                                data-test="card-description-input"
                            />

                            <div class="grid gap-4 sm:grid-cols-2">
                                <flux:field>
                                    <flux:label>{{ __('Start date') }}</flux:label>
                                    <flux:input
                                        type="date"
                                        name="start_date"
                                        :value="old('start_date', $card->start_date?->format('Y-m-d'))"
                                        data-test="card-start-date"
                                    />
                                    <flux:error name="start_date" />
                                </flux:field>

                                <flux:field>
                                    <flux:label>{{ __('Due date') }}</flux:label>
                                    <flux:input
                                        type="datetime-local"
                                        name="due_date"
                                        :value="old('due_date', $card->due_date?->format('Y-m-d\TH:i'))"
                                        data-test="card-due-date"
                                    />
                                    <flux:error name="due_date" />
                                </flux:field>
                            </div>

                            <flux:button variant="primary" type="submit" data-test="card-save-button">
                                {{ __('Save card') }}
                            </flux:button>
                        </form>
                    @else
                        <flux:heading size="lg">{{ $card->title }}</flux:heading>
                        <flux:text class="mt-2 whitespace-pre-line">{{ $card->description ?: __('No description.') }}</flux:text>
                    @endcan
                </div>

                {{-- Checklists --}}
                <div class="rounded-xl border border-zinc-200 p-5 dark:border-zinc-700">
                    <flux:heading size="lg">{{ __('Checklists') }}</flux:heading>

                    <div class="mt-4 space-y-5">
                        @forelse ($card->checklists as $checklist)
                            <div data-test="checklist">
                                <div class="flex items-center justify-between gap-2">
                                    <span class="font-medium">{{ $checklist->name }}</span>

                                    <div class="flex items-center gap-2">
                                        <flux:badge size="sm" color="zinc">
                                            {{ $checklist->completedCount() }}/{{ $checklist->items->count() }}
                                        </flux:badge>

                                        @can('delete', $checklist)
                                            <form method="POST" action="{{ route('checklists.destroy', ['checklist' => $checklist]) }}">
                                                @csrf
                                                @method('DELETE')
                                                <flux:button type="submit" variant="ghost" size="xs" icon="trash" data-test="checklist-delete-button" />
                                            </form>
                                        @endcan
                                    </div>
                                </div>

                                <ul class="mt-2 space-y-1">
                                    @foreach ($checklist->items as $item)
                                        <li class="flex items-center gap-2" data-test="checklist-item">
                                            @can('update', $checklist)
                                                <form method="POST" action="{{ route('checklist-items.toggle', ['item' => $item]) }}">
                                                    @csrf
                                                    <button type="button" onclick="this.closest('form').submit()" class="flex items-center" data-test="checklist-item-toggle">
                                                        @if ($item->is_completed)
                                                            <flux:icon.check-circle variant="solid" class="size-5 text-green-600" />
                                                        @else
                                                            <flux:icon.check-circle class="size-5 text-zinc-400" />
                                                        @endif
                                                    </button>
                                                </form>
                                            @endcan

                                            <span @class(['text-sm', 'line-through opacity-60' => $item->is_completed])>
                                                {{ $item->content }}
                                            </span>
                                        </li>
                                    @endforeach
                                </ul>

                                @can('update', $checklist)
                                    <form method="POST" action="{{ route('checklist-items.store', ['checklist' => $checklist]) }}" class="mt-2">
                                        @csrf
                                        <flux:input name="content" size="sm" :placeholder="__('Add an item…')" data-test="checklist-item-input" />
                                    </form>
                                @endcan
                            </div>
                        @empty
                            <flux:text class="text-sm text-zinc-500 dark:text-zinc-400">{{ __('No checklists yet.') }}</flux:text>
                        @endforelse
                    </div>

                    @can('create', [App\Models\Checklist::class, $card])
                        <form method="POST" action="{{ route('checklists.store', ['card' => $card]) }}" class="mt-4">
                            @csrf
                            <flux:input name="name" size="sm" :placeholder="__('Add a checklist…')" data-test="checklist-input" />
                        </form>
                    @endcan
                </div>

                {{-- Comments --}}
                <div class="rounded-xl border border-zinc-200 p-5 dark:border-zinc-700">
                    <flux:heading size="lg">{{ __('Comments') }}</flux:heading>

                    @can('create', [App\Models\Comment::class, $card])
                        <form method="POST" action="{{ route('comments.store', ['card' => $card]) }}" class="mt-4 space-y-2">
                            @csrf
                            <flux:textarea name="body" rows="3" :placeholder="__('Write a comment…')" data-test="comment-input" />
                            <flux:button variant="primary" size="sm" type="submit" data-test="comment-submit">
                                {{ __('Comment') }}
                            </flux:button>
                        </form>
                    @endcan

                    <div class="mt-5 space-y-4">
                        @forelse ($card->comments as $comment)
                            <div class="flex gap-3" data-test="comment">
                                <flux:avatar size="sm" :name="$comment->author->name" />

                                <div class="min-w-0 flex-1">
                                    <div class="flex items-center gap-2">
                                        <span class="text-sm font-medium">{{ $comment->author->name }}</span>
                                        <flux:text class="text-xs text-zinc-500">{{ $comment->created_at?->diffForHumans() }}</flux:text>
                                    </div>

                                    <flux:text class="mt-1 whitespace-pre-line text-sm">{{ $comment->body }}</flux:text>
                                </div>

                                @can('delete', $comment)
                                    <form method="POST" action="{{ route('comments.destroy', ['comment' => $comment]) }}">
                                        @csrf
                                        @method('DELETE')
                                        <flux:button type="submit" variant="ghost" size="xs" icon="trash" data-test="comment-delete-button" />
                                    </form>
                                @endcan
                            </div>
                        @empty
                            <flux:text class="text-sm text-zinc-500 dark:text-zinc-400">{{ __('No comments yet.') }}</flux:text>
                        @endforelse
                    </div>
                </div>
            </div>

            {{-- Sidebar --}}
            <div class="space-y-6">
                {{-- Assignees --}}
                <div class="rounded-xl border border-zinc-200 p-5 dark:border-zinc-700">
                    <flux:heading size="sm">{{ __('Assignees') }}</flux:heading>

                    <div class="mt-3 space-y-2">
                        @forelse ($card->assignees as $assignee)
                            <div class="flex items-center justify-between gap-2" data-test="card-assignee">
                                <div class="flex items-center gap-2">
                                    <flux:avatar size="xs" :name="$assignee->name" />
                                    <span class="text-sm">{{ $assignee->name }}</span>
                                </div>

                                <form method="POST" action="{{ route('card-assignees.destroy', ['card' => $card, 'user' => $assignee]) }}">
                                    @csrf
                                    @method('DELETE')
                                    <flux:button type="submit" variant="ghost" size="xs" icon="x-mark" data-test="card-unassign-button" />
                                </form>
                            </div>
                        @empty
                            <flux:text class="text-sm text-zinc-500 dark:text-zinc-400">{{ __('Nobody assigned.') }}</flux:text>
                        @endforelse
                    </div>

                    <form method="POST" action="{{ route('card-assignees.store', ['card' => $card]) }}" class="mt-3 flex gap-2">
                        @csrf
                        <flux:select name="user_id" size="sm" class="flex-1" data-test="card-assignee-select">
                            @foreach ($team->members as $member)
                                <flux:select.option value="{{ $member->id }}">{{ $member->name }}</flux:select.option>
                            @endforeach
                        </flux:select>

                        <flux:button type="submit" size="sm" icon="plus" data-test="card-assign-button" />
                    </form>
                </div>

                {{-- Labels --}}
                <div class="rounded-xl border border-zinc-200 p-5 dark:border-zinc-700">
                    <flux:heading size="sm">{{ __('Labels') }}</flux:heading>

                    <div class="mt-3 flex flex-wrap gap-2">
                        @foreach ($board->labels as $label)
                            @php($applied = $card->labels->contains($label))

                            <form method="POST" action="{{ route('cards.labels.toggle', ['card' => $card, 'label' => $label]) }}">
                                @csrf
                                <button
                                    type="submit"
                                    @class([
                                        'flex items-center gap-1.5 rounded-md px-2 py-1 text-xs text-white transition',
                                        $labelColors[$label->color] ?? 'bg-zinc-500',
                                        'opacity-40 hover:opacity-70' => ! $applied,
                                    ])
                                    data-test="card-label-toggle"
                                >
                                    @if ($applied)
                                        <flux:icon.check variant="micro" />
                                    @endif
                                    {{ $label->name }}
                                </button>
                            </form>
                        @endforeach
                    </div>
                </div>

                {{-- Attachments --}}
                <div class="rounded-xl border border-zinc-200 p-5 dark:border-zinc-700">
                    <flux:heading size="sm">{{ __('Attachments') }}</flux:heading>

                    <div class="mt-3" data-test="card-attachment">
                        <x-attachment-list :attachments="$card->attachments" delete-mode="form" />
                    </div>

                    @can('create', [App\Models\Attachment::class, $card])
                        <form
                            method="POST"
                            action="{{ route('attachments.store', ['card' => $card]) }}"
                            enctype="multipart/form-data"
                            class="mt-3 space-y-2"
                        >
                            @csrf
                            <flux:input type="file" name="file" size="sm" data-test="attachment-input" />
                            <flux:error name="file" />
                            <flux:button type="submit" size="sm" variant="filled" data-test="attachment-submit">
                                {{ __('Upload') }}
                            </flux:button>
                        </form>
                    @endcan
                </div>

                {{-- Activity --}}
                <div class="rounded-xl border border-zinc-200 p-5 dark:border-zinc-700">
                    <flux:heading size="sm">{{ __('Activity') }}</flux:heading>

                    <ul class="mt-3 space-y-3">
                        @forelse ($card->activities as $activity)
                            <li class="text-sm" data-test="card-activity">
                                <flux:text class="text-zinc-700 dark:text-zinc-300">{{ $activity->describe() }}</flux:text>
                                <flux:text class="text-xs text-zinc-500">{{ $activity->created_at?->diffForHumans() }}</flux:text>
                            </li>
                        @empty
                            <li>
                                <flux:text class="text-sm text-zinc-500 dark:text-zinc-400">{{ __('Nothing yet.') }}</flux:text>
                            </li>
                        @endforelse
                    </ul>
                </div>
            </div>
        </div>
    </section>
</x-layouts::app>
