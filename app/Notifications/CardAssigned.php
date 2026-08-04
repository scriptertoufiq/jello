<?php

namespace App\Notifications;

use App\Models\Card;
use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class CardAssigned extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        private readonly Card $card,
        private readonly User $assignedBy,
    ) {}

    /**
     * Get the delivery channels.
     *
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['mail', 'database'];
    }

    /**
     * Build the mail representation.
     */
    public function toMail(User $notifiable): MailMessage
    {
        $board = $this->card->board();

        return (new MailMessage)
            ->subject(__('You were assigned to ":title"', ['title' => $this->card->title]))
            ->greeting(__('Hi :name,', ['name' => $notifiable->name]))
            ->line(__(':actor assigned you to a card on :board.', [
                'actor' => $this->assignedBy->name,
                'board' => $board->name,
            ]))
            ->action(__('Open card'), $this->cardUrl())
            ->line(__('Thanks for keeping things moving.'));
    }

    /**
     * Get the database representation.
     *
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        return [
            'type' => 'card.assigned',
            'card_id' => $this->card->id,
            'card_title' => $this->card->title,
            'board_name' => $this->card->board()->name,
            'assigned_by' => $this->assignedBy->name,
            'url' => $this->cardUrl(),
        ];
    }

    /**
     * Build the absolute URL to the card.
     */
    private function cardUrl(): string
    {
        return route('cards.show', [
            'current_team' => $this->card->board()->team->slug,
            'card' => $this->card->id,
        ]);
    }
}
