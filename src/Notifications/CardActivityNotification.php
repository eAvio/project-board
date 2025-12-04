<?php

namespace Eavio\ProjectBoard\Notifications;

use Eavio\ProjectBoard\Models\Card;
use Eavio\ProjectBoard\Models\Activity;
use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class CardActivityNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public Card $card;
    public User $actor;
    public string $activityText;

    public function __construct(Card $card, User $actor, string $activityText)
    {
        $this->card = $card;
        $this->actor = $actor;
        $this->activityText = $activityText;
    }

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject("Activity on '{$this->card->title}'")
            ->greeting("Hello {$notifiable->name}!")
            ->line("{$this->actor->name} {$this->activityText} on the card '{$this->card->title}'.")
            ->action('View Board', url('/nova/projects-board'))
            ->line('Thank you for using our application!');
    }

    public function toArray(object $notifiable): array
    {
        return [
            'card_id' => $this->card->id,
            'actor_id' => $this->actor->id,
            'activity' => $this->activityText,
        ];
    }
}
