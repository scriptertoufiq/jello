<x-layouts::app :title="__('Boards')">
    <section class="w-full">
        <div class="flex flex-wrap items-center justify-between gap-4">
            <div>
                <flux:heading size="xl">{{ __('Boards') }}</flux:heading>
                <flux:subheading>
                    {{ __('Boards in the :name workspace', ['name' => $team->name]) }}
                </flux:subheading>
            </div>

            <div class="flex items-center gap-2">
                <flux:button
                    variant="ghost"
                    :icon="$showingArchived ? 'squares-2x2' : 'archive-box'"
                    :href="route('boards.index', $showingArchived ? [] : ['archived' => 1])"
                    wire:navigate
                    data-test="boards-archived-toggle"
                >
                    {{ $showingArchived ? __('Active boards') : __('Archived') }}
                </flux:button>

                @can('create', [App\Models\Board::class, $team])
                    <flux:button
                        variant="primary"
                        icon="plus"
                        x-data=""
                        x-on:click.prevent="$dispatch('open-modal', 'create-board')"
                        data-test="boards-new-board-button"
                    >
                        {{ __('New board') }}
                    </flux:button>
                @endcan
            </div>
        </div>

        @if (session('status'))
            <flux:callout variant="success" class="mt-6" data-test="boards-status">
                {{ session('status') }}
            </flux:callout>
        @endif

        <div class="mt-6 grid gap-4 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4">
            @forelse ($boards as $board)
                <a
                    href="{{ route('boards.show', ['board' => $board]) }}"
                    wire:navigate
                    class="group flex flex-col overflow-hidden rounded-xl border border-zinc-200 bg-white transition hover:border-zinc-300 hover:shadow-md dark:border-zinc-700 dark:bg-zinc-900 dark:hover:border-zinc-600"
                    data-test="board-card"
                >
                    <div class="h-20 bg-gradient-to-br {{ config('boards.backgrounds.'.$board->background, 'from-slate-500 to-slate-700') }}"></div>

                    <div class="flex flex-1 flex-col gap-2 p-4">
                        <div class="flex items-start justify-between gap-2">
                            <span class="font-medium text-zinc-900 dark:text-zinc-100">{{ $board->name }}</span>

                            @if ($board->isPrivate())
                                <flux:tooltip :content="__('Private board')">
                                    <flux:icon.lock-closed variant="micro" class="mt-1 shrink-0 text-zinc-400" />
                                </flux:tooltip>
                            @endif
                        </div>

                        @if ($board->description)
                            <flux:text class="line-clamp-2 text-sm text-zinc-500 dark:text-zinc-400">
                                {{ $board->description }}
                            </flux:text>
                        @endif

                        @if ($board->isArchived())
                            <flux:badge color="amber" size="sm" class="mt-auto self-start">{{ __('Archived') }}</flux:badge>
                        @endif
                    </div>
                </a>
            @empty
                <div class="col-span-full rounded-xl border border-dashed border-zinc-300 py-16 text-center dark:border-zinc-700">
                    <flux:text class="text-zinc-500 dark:text-zinc-400">
                        {{ $showingArchived ? __('No archived boards.') : __('No boards yet. Create your first one to get started.') }}
                    </flux:text>
                </div>
            @endforelse
        </div>
    </section>

    @can('create', [App\Models\Board::class, $team])
        <flux:modal name="create-board" :show="$errors->isNotEmpty()" focusable class="max-w-lg">
            <form method="POST" action="{{ route('boards.store') }}" class="space-y-6">
                @csrf

                <div>
                    <flux:heading size="lg">{{ __('Create a board') }}</flux:heading>
                    <flux:subheading>{{ __('Boards hold your lists and cards.') }}</flux:subheading>
                </div>

                <flux:input
                    name="name"
                    :label="__('Board name')"
                    :value="old('name')"
                    type="text"
                    required
                    autofocus
                    data-test="create-board-name"
                />

                <flux:textarea
                    name="description"
                    :label="__('Description')"
                    :value="old('description')"
                    rows="2"
                    data-test="create-board-description"
                />

                <flux:field>
                    <flux:label>{{ __('Background') }}</flux:label>

                    <div class="flex flex-wrap gap-2" x-data="{ background: '{{ old('background', 'slate') }}' }">
                        @foreach (config('boards.backgrounds') as $key => $gradient)
                            <label class="cursor-pointer">
                                <input type="radio" name="background" value="{{ $key }}" x-model="background" class="peer sr-only">
                                <span
                                    class="block size-9 rounded-lg bg-gradient-to-br {{ $gradient }} ring-offset-2 ring-offset-white peer-checked:ring-2 peer-checked:ring-zinc-900 dark:ring-offset-zinc-900 dark:peer-checked:ring-white"
                                ></span>
                            </label>
                        @endforeach
                    </div>

                    <flux:error name="background" />
                </flux:field>

                <flux:select name="visibility" :label="__('Visibility')" data-test="create-board-visibility">
                    @foreach (App\Enums\BoardVisibility::options() as $option)
                        <flux:select.option
                            value="{{ $option['value'] }}"
                            :selected="old('visibility', 'workspace') === $option['value']"
                        >
                            {{ $option['label'] }}
                        </flux:select.option>
                    @endforeach
                </flux:select>

                <div class="flex justify-end space-x-2 rtl:space-x-reverse">
                    <flux:modal.close>
                        <flux:button variant="filled" type="button">{{ __('Cancel') }}</flux:button>
                    </flux:modal.close>

                    <flux:button variant="primary" type="submit" data-test="create-board-submit">
                        {{ __('Create board') }}
                    </flux:button>
                </div>
            </form>
        </flux:modal>
    @endcan
</x-layouts::app>
