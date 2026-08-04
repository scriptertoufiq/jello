<x-layouts::app :title="__('Search')">
    <section class="mx-auto w-full max-w-4xl">
        <flux:heading size="xl">{{ __('Search cards') }}</flux:heading>
        <flux:subheading>
            {{ __('Across every board you can see in :name', ['name' => $team->name]) }}
        </flux:subheading>

        <form method="GET" action="{{ route('search') }}" class="mt-6 space-y-3">
            <flux:input
                name="term"
                :value="$filters['term']"
                :placeholder="__('Search titles and descriptions…')"
                icon="magnifying-glass"
                data-test="search-term"
            />

            <div class="grid gap-3 sm:grid-cols-4">
                <flux:select name="board" size="sm" data-test="search-board">
                    <flux:select.option value="">{{ __('Any board') }}</flux:select.option>
                    @foreach ($boards as $board)
                        <flux:select.option value="{{ $board->id }}" :selected="$filters['board'] === $board->id">
                            {{ $board->name }}
                        </flux:select.option>
                    @endforeach
                </flux:select>

                <flux:select name="assignee" size="sm" data-test="search-assignee">
                    <flux:select.option value="">{{ __('Anyone') }}</flux:select.option>
                    @foreach ($members as $member)
                        <flux:select.option value="{{ $member->id }}" :selected="$filters['assignee'] === $member->id">
                            {{ $member->name }}
                        </flux:select.option>
                    @endforeach
                </flux:select>

                <flux:select name="due" size="sm" data-test="search-due">
                    <flux:select.option value="">{{ __('Any due date') }}</flux:select.option>
                    <flux:select.option value="overdue" :selected="$filters['due'] === 'overdue'">{{ __('Overdue') }}</flux:select.option>
                    <flux:select.option value="today" :selected="$filters['due'] === 'today'">{{ __('Due today') }}</flux:select.option>
                    <flux:select.option value="week" :selected="$filters['due'] === 'week'">{{ __('Due this week') }}</flux:select.option>
                    <flux:select.option value="none" :selected="$filters['due'] === 'none'">{{ __('No due date') }}</flux:select.option>
                </flux:select>

                <div class="flex gap-2">
                    <flux:button variant="primary" type="submit" class="flex-1" data-test="search-submit">
                        {{ __('Search') }}
                    </flux:button>

                    <flux:button variant="ghost" :href="route('search')" wire:navigate data-test="search-reset">
                        {{ __('Reset') }}
                    </flux:button>
                </div>
            </div>
        </form>

        <div class="mt-8">
            <flux:text class="text-sm text-zinc-500 dark:text-zinc-400" data-test="search-count">
                {{ trans_choice(':count card|:count cards', $cards->total(), ['count' => $cards->total()]) }}
            </flux:text>

            <div class="mt-3 space-y-2" data-test="search-results">
                @forelse ($cards as $card)
                    <x-card-row :card="$card" />
                @empty
                    <div class="rounded-xl border border-dashed border-zinc-300 py-12 text-center dark:border-zinc-700">
                        <flux:text class="text-zinc-500 dark:text-zinc-400">{{ __('No cards match those filters.') }}</flux:text>
                    </div>
                @endforelse
            </div>

            <div class="mt-6">
                {{ $cards->links() }}
            </div>
        </div>
    </section>
</x-layouts::app>
