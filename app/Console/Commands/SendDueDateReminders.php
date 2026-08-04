<?php

namespace App\Console\Commands;

use App\Models\Card;
use App\Notifications\CardDueSoon;
use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Notification;

class SendDueDateReminders extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'boards:send-due-date-reminders {--hours=24 : How far ahead to look}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Notify assignees about cards that fall due within the given window';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $hours = max((int) $this->option('hours'), 1);

        $cards = Card::query()
            ->active()
            ->whereNull('completed_at')
            ->whereNotNull('due_date')
            ->whereBetween('due_date', [now(), now()->addHours($hours)])
            ->whereHas('assignees')
            ->whereHas('list', fn (Builder $query) => $query
                ->whereNull('board_lists.archived_at')
                ->whereHas('board', fn (Builder $query) => $query->whereNull('boards.archived_at')),
            )
            ->with(['assignees', 'list.board.team'])
            ->get();

        foreach ($cards as $card) {
            Notification::send($card->assignees, new CardDueSoon($card));
        }

        $this->info(__('Reminded assignees on :count card(s).', ['count' => $cards->count()]));

        return self::SUCCESS;
    }
}
