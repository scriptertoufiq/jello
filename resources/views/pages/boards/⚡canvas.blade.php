<?php

use App\Models\Board;
use Livewire\Component;

new class extends Component {
    public Board $board;

    public function mount(Board $board): void
    {
        $this->board = $board;
    }
}; ?>

<div class="flex h-full items-center justify-center rounded-xl border border-dashed border-zinc-300 py-16 dark:border-zinc-700" data-test="board-canvas">
    <flux:text class="text-zinc-500 dark:text-zinc-400">
        {{ __('Lists and cards are coming to this board.') }}
    </flux:text>
</div>
