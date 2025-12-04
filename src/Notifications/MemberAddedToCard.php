<?php

namespace Eavio\ProjectBoard\Notifications;

use Eavio\ProjectBoard\Models\Card;
use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class MemberAddedToCard extends Notification implements ShouldQueue
{
    use Queueable;

    public Card $card;
    public User $addedBy;

    public function __construct(Card $card, User $addedBy)
    {
        $this->card = $card;
        $this->addedBy = $addedBy;
    }

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject("You were added to '{$this->card->title}'")
            ->greeting("Hello {$notifiable->name}!")
            ->line("{$this->addedBy->name} added you to the card '{$this->card->title}'.")
            ->action('View Board', url('/nova/projects-board'))
            ->line('Thank you for using our application!');
    }

    public function toArray(object $notifiable): array
    {
        return [
            'card_id' => $this->card->id,
            'added_by' => $this->addedBy->id,
        ];
    }
}
