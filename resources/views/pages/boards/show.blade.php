<x-layouts::app :title="$board->name">
    <section class="flex h-full w-full flex-col">
        <div
            @class([
                'relative flex flex-wrap items-end justify-between gap-4 overflow-hidden rounded-xl px-5 py-4',
                'bg-gradient-to-r '.$board->backgroundGradient() => ! $board->hasThumbnail(),
                // Give the cover image room to read as a background rather than a sliver.
                'min-h-40' => $board->hasThumbnail(),
            ])
        >
            @if ($board->hasThumbnail())
                <img
                    src="{{ $board->thumbnailUrl() }}"
                    alt=""
                    class="absolute inset-0 size-full object-cover"
                    data-test="board-thumbnail"
                >
                {{-- Darkest at the bottom, where the title and buttons sit. --}}
                <div class="absolute inset-0 bg-gradient-to-t from-black/80 via-black/45 to-black/20"></div>
            @endif

            <div class="relative min-w-0">
                <div class="flex items-center gap-2">
                    <flux:heading size="lg" class="truncate !text-white">{{ $board->name }}</flux:heading>

                    @if ($board->isPrivate())
                        <flux:badge color="zinc" size="sm" icon="lock-closed">{{ __('Private') }}</flux:badge>
                    @endif

                    @if ($board->isArchived())
                        <flux:badge color="amber" size="sm">{{ __('Archived') }}</flux:badge>
                    @endif
                </div>

                @if ($board->description)
                    <p class="mt-1 truncate text-sm text-white/80">{{ $board->description }}</p>
                @endif
            </div>

            <div class="relative flex items-center gap-2">
                <flux:button variant="ghost" size="sm" icon="arrow-left" :href="route('boards.index')" wire:navigate class="!text-white">
                    {{ __('All boards') }}
                </flux:button>

                @canany(['update', 'archive', 'delete'], $board)
                    <flux:dropdown position="bottom" align="end">
                        <flux:button variant="ghost" size="sm" icon="ellipsis-horizontal" class="!text-white" data-test="board-menu-button" />

                        <flux:menu>
                            @can('update', $board)
                                <flux:menu.item
                                    icon="cog-6-tooth"
                                    x-data=""
                                    x-on:click.prevent="$dispatch('modal-show', { name: 'board-settings' })"
                                    data-test="board-settings-button"
                                >
                                    {{ __('Board settings') }}
                                </flux:menu.item>
                            @endcan

                            @can('manageMembers', $board)
                                <flux:menu.item
                                    icon="users"
                                    x-data=""
                                    x-on:click.prevent="$dispatch('modal-show', { name: 'board-members' })"
                                    data-test="board-members-button"
                                >
                                    {{ __('Board members') }}
                                </flux:menu.item>
                            @endcan

                            @can('archive', $board)
                                <flux:menu.separator />

                                @if ($board->isArchived())
                                    <flux:menu.item
                                        icon="arrow-uturn-left"
                                        x-data=""
                                        x-on:click.prevent="document.getElementById('restore-board-form').submit()"
                                        data-test="board-restore-button"
                                    >
                                        {{ __('Restore board') }}
                                    </flux:menu.item>
                                @else
                                    <flux:menu.item
                                        icon="archive-box"
                                        x-data=""
                                        x-on:click.prevent="document.getElementById('archive-board-form').submit()"
                                        data-test="board-archive-button"
                                    >
                                        {{ __('Archive board') }}
                                    </flux:menu.item>
                                @endif
                            @endcan

                            @can('delete', $board)
                                <flux:menu.separator />

                                <flux:menu.item
                                    variant="danger"
                                    icon="trash"
                                    x-data=""
                                    x-on:click.prevent="$dispatch('modal-show', { name: 'delete-board' })"
                                    data-test="board-delete-button"
                                >
                                    {{ __('Delete board') }}
                                </flux:menu.item>
                            @endcan
                        </flux:menu>
                    </flux:dropdown>
                @endcanany
            </div>
        </div>

        @if (session('status'))
            <flux:callout variant="success" class="mt-4" data-test="board-status">
                {{ session('status') }}
            </flux:callout>
        @endif

        <div class="mt-4 flex-1">
            <livewire:pages::boards.canvas :board="$board" />
        </div>

        <livewire:pages::boards.card-modal :board="$board" />
    </section>

    @can('archive', $board)
        <form id="archive-board-form" method="POST" action="{{ route('boards.archive', ['board' => $board]) }}" class="hidden">
            @csrf
        </form>

        <form id="restore-board-form" method="POST" action="{{ route('boards.restore', ['board' => $board]) }}" class="hidden">
            @csrf
        </form>
    @endcan

    @can('update', $board)
        <flux:modal name="board-settings" :show="$errors->isNotEmpty()" focusable class="max-w-lg">
            <form
                method="POST"
                action="{{ route('boards.update', ['board' => $board]) }}"
                enctype="multipart/form-data"
                class="space-y-6"
            >
                @csrf
                @method('PATCH')

                <div>
                    <flux:heading size="lg">{{ __('Board settings') }}</flux:heading>
                    <flux:subheading>{{ __('Rename the board, change its look, or control who can see it.') }}</flux:subheading>
                </div>

                <flux:input
                    name="name"
                    :label="__('Board name')"
                    :value="old('name', $board->name)"
                    type="text"
                    required
                    data-test="board-settings-name"
                />

                <flux:textarea
                    name="description"
                    :label="__('Description')"
                    :value="old('description', $board->description)"
                    rows="2"
                    data-test="board-settings-description"
                />

                <flux:field>
                    <flux:label>{{ __('Background') }}</flux:label>

                    <div class="flex flex-wrap gap-2">
                        @foreach (config('boards.backgrounds') as $key => $gradient)
                            <label class="cursor-pointer">
                                <input
                                    type="radio"
                                    name="background"
                                    value="{{ $key }}"
                                    @checked(old('background', $board->background) === $key)
                                    class="peer sr-only"
                                >
                                <span
                                    class="block size-9 rounded-lg bg-gradient-to-br {{ $gradient }} ring-offset-2 ring-offset-white peer-checked:ring-2 peer-checked:ring-zinc-900 dark:ring-offset-zinc-900 dark:peer-checked:ring-white"
                                ></span>
                            </label>
                        @endforeach
                    </div>

                    <flux:error name="background" />
                </flux:field>

                <flux:field>
                    <flux:label>{{ __('Thumbnail') }}</flux:label>
                    <flux:description>{{ __('Optional cover image. Replaces the background swatch.') }}</flux:description>

                    @if ($board->hasThumbnail())
                        <div class="mb-2 flex items-center gap-3">
                            <img
                                src="{{ $board->thumbnailUrl() }}"
                                alt=""
                                class="h-16 w-28 rounded-lg object-cover"
                                data-test="board-settings-thumbnail-preview"
                            >

                            <flux:checkbox
                                name="remove_thumbnail"
                                value="1"
                                :label="__('Remove thumbnail')"
                                data-test="board-settings-remove-thumbnail"
                            />
                        </div>
                    @endif

                    <flux:input type="file" name="thumbnail" accept="image/*" data-test="board-settings-thumbnail" />
                    <flux:error name="thumbnail" />
                </flux:field>

                <flux:select name="visibility" :label="__('Visibility')" data-test="board-settings-visibility">
                    @foreach (App\Enums\BoardVisibility::options() as $option)
                        <flux:select.option
                            value="{{ $option['value'] }}"
                            :selected="old('visibility', $board->visibility->value) === $option['value']"
                        >
                            {{ $option['label'] }}
                        </flux:select.option>
                    @endforeach
                </flux:select>

                <div class="flex justify-end space-x-2 rtl:space-x-reverse">
                    <flux:modal.close>
                        <flux:button variant="filled" type="button">{{ __('Cancel') }}</flux:button>
                    </flux:modal.close>

                    <flux:button variant="primary" type="submit" data-test="board-settings-submit">
                        {{ __('Save changes') }}
                    </flux:button>
                </div>
            </form>
        </flux:modal>
    @endcan

    @can('manageMembers', $board)
        <flux:modal name="board-members" focusable class="max-w-lg">
            <div class="space-y-6">
                <div>
                    <flux:heading size="lg">{{ __('Board members') }}</flux:heading>
                    <flux:subheading>
                        {{ $board->isPrivate()
                            ? __('Only these people can open this private board.')
                            : __('This board is visible to the whole workspace. Adding members here takes effect if you make it private.') }}
                    </flux:subheading>
                </div>

                <div class="space-y-2">
                    @forelse ($board->members as $member)
                        <div class="flex items-center justify-between gap-3 rounded-lg border border-zinc-200 p-3 dark:border-zinc-700" data-test="board-member-row">
                            <div class="flex items-center gap-2">
                                <flux:avatar size="xs" :name="$member->name" />
                                <div>
                                    <div class="text-sm font-medium">{{ $member->name }}</div>
                                    <flux:text class="text-xs text-zinc-500">{{ $member->pivot->role->label() }}</flux:text>
                                </div>
                            </div>

                            <form method="POST" action="{{ route('board-members.destroy', ['board' => $board, 'member' => $member]) }}">
                                @csrf
                                @method('DELETE')
                                <flux:button type="submit" variant="ghost" size="xs" icon="x-mark" data-test="board-member-remove" />
                            </form>
                        </div>
                    @empty
                        <flux:text class="text-sm text-zinc-500 dark:text-zinc-400">{{ __('No board members yet.') }}</flux:text>
                    @endforelse
                </div>

                <form method="POST" action="{{ route('board-members.store', ['board' => $board]) }}" class="flex items-end gap-2">
                    @csrf

                    <flux:select name="user_id" :label="__('Add a workspace member')" size="sm" class="flex-1" data-test="board-member-select">
                        @foreach ($board->team->members as $candidate)
                            @unless ($board->members->contains($candidate))
                                <flux:select.option value="{{ $candidate->id }}">{{ $candidate->name }}</flux:select.option>
                            @endunless
                        @endforeach
                    </flux:select>

                    <flux:select name="role" size="sm" data-test="board-member-role">
                        @foreach (App\Enums\TeamRole::assignable() as $role)
                            <flux:select.option value="{{ $role['value'] }}">{{ $role['label'] }}</flux:select.option>
                        @endforeach
                    </flux:select>

                    <flux:button type="submit" size="sm" icon="plus" data-test="board-member-add" />
                </form>

                <flux:error name="user_id" />
            </div>
        </flux:modal>
    @endcan

    @can('delete', $board)
        <flux:modal name="delete-board" focusable class="max-w-lg">
            <form method="POST" action="{{ route('boards.destroy', ['board' => $board]) }}" class="space-y-6">
                @csrf
                @method('DELETE')

                <div>
                    <flux:heading size="lg">{{ __('Delete board') }}</flux:heading>
                    <flux:subheading>
                        {{ __('Are you sure you want to delete :name? Its lists and cards will be deleted too.', ['name' => $board->name]) }}
                    </flux:subheading>
                </div>

                <div class="flex justify-end space-x-2 rtl:space-x-reverse">
                    <flux:modal.close>
                        <flux:button variant="filled" type="button">{{ __('Cancel') }}</flux:button>
                    </flux:modal.close>

                    <flux:button variant="danger" type="submit" data-test="delete-board-confirm">
                        {{ __('Delete board') }}
                    </flux:button>
                </div>
            </form>
        </flux:modal>
    @endcan
</x-layouts::app>
