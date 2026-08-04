<?php

namespace App\Notifications;

use App\Models\Card;
use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class CardDueSoon extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(private readonly Card $card) {}

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
        return (new MailMessage)
            ->subject(__('":title" is due soon', ['title' => $this->card->title]))
            ->greeting(__('Hi :name,', ['name' => $notifiable->name]))
            ->line(__('This card is due :when.', [
                'when' => $this->card->due_date?->diffForHumans() ?? __('soon'),
            ]))
            ->action(__('Open card'), $this->cardUrl());
    }

    /**
     * Get the database representation.
     *
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        return [
            'type' => 'card.due_soon',
            'card_id' => $this->card->id,
            'card_title' => $this->card->title,
            'board_name' => $this->card->board()->name,
            'due_date' => $this->card->due_date?->toIso8601String(),
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
