<x-layouts::app :title="__('My tasks')">
    <section class="mx-auto w-full max-w-4xl">
        <flux:heading size="xl">{{ __('My tasks') }}</flux:heading>
        <flux:subheading>
            {{ __('Open cards assigned to you across the :name workspace', ['name' => $team->name]) }}
        </flux:subheading>

        @if ($overdue->isNotEmpty())
            <div class="mt-8">
                <div class="flex items-center gap-2">
                    <flux:heading size="lg">{{ __('Overdue') }}</flux:heading>
                    <flux:badge color="red" size="sm">{{ $overdue->count() }}</flux:badge>
                </div>

                <div class="mt-3 space-y-2" data-test="my-tasks-overdue">
                    @foreach ($overdue as $card)
                        <x-card-row :card="$card" />
                    @endforeach
                </div>
            </div>
        @endif

        <div class="mt-8">
            <flux:heading size="lg">{{ __('Upcoming') }}</flux:heading>

            <div class="mt-3 space-y-2" data-test="my-tasks-upcoming">
                @forelse ($upcoming as $card)
                    <x-card-row :card="$card" />
                @empty
                    <div class="rounded-xl border border-dashed border-zinc-300 py-12 text-center dark:border-zinc-700">
                        <flux:text class="text-zinc-500 dark:text-zinc-400">
                            {{ $overdue->isEmpty()
                                ? __('Nothing is assigned to you right now.')
                                : __('Nothing upcoming.') }}
                        </flux:text>
                    </div>
                @endforelse
            </div>
        </div>
    </section>
</x-layouts::app>
