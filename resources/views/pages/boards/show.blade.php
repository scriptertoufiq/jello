<x-layouts::app :title="$board->name">
    <section class="flex h-full w-full flex-col">
        <div class="flex flex-wrap items-center justify-between gap-4 rounded-xl bg-gradient-to-r {{ config('boards.backgrounds.'.$board->background, 'from-slate-500 to-slate-700') }} px-5 py-4">
            <div class="min-w-0">
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

            <div class="flex items-center gap-2">
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
                                    x-on:click.prevent="$dispatch('open-modal', 'board-settings')"
                                    data-test="board-settings-button"
                                >
                                    {{ __('Board settings') }}
                                </flux:menu.item>
                            @endcan

                            @can('archive', $board)
                                <flux:menu.separator />

                                @if ($board->isArchived())
                                    <flux:menu.item
                                        icon="arrow-uturn-left"
                                        x-data=""
                                        x-on:click.prevent="$refs.restoreBoard.submit()"
                                        data-test="board-restore-button"
                                    >
                                        {{ __('Restore board') }}
                                    </flux:menu.item>
                                @else
                                    <flux:menu.item
                                        icon="archive-box"
                                        x-data=""
                                        x-on:click.prevent="$refs.archiveBoard.submit()"
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
                                    x-on:click.prevent="$dispatch('open-modal', 'delete-board')"
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
    </section>

    @can('archive', $board)
        <form x-ref="archiveBoard" method="POST" action="{{ route('boards.archive', ['board' => $board]) }}" class="hidden">
            @csrf
        </form>

        <form x-ref="restoreBoard" method="POST" action="{{ route('boards.restore', ['board' => $board]) }}" class="hidden">
            @csrf
        </form>
    @endcan

    @can('update', $board)
        <flux:modal name="board-settings" :show="$errors->isNotEmpty()" focusable class="max-w-lg">
            <form method="POST" action="{{ route('boards.update', ['board' => $board]) }}" class="space-y-6">
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
