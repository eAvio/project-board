<?php

namespace Eavio\ProjectBoard\Notifications;

use Eavio\ProjectBoard\Models\Comment;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class CardMentioned extends Notification implements ShouldQueue
{
    use Queueable;

    public $comment;

    /**
     * Create a new notification instance.
     */
    public function __construct(Comment $comment)
    {
        $this->comment = $comment;
    }

    /**
     * Get the notification's delivery channels.
     *
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    /**
     * Get the mail representation of the notification.
     */
    public function toMail(object $notifiable): MailMessage
    {
        $cardTitle = $this->comment->commentable ? $this->comment->commentable->title : 'a card';
        
        return (new MailMessage)
            ->subject("You were mentioned in '{$cardTitle}'")
            ->greeting("Hello {$notifiable->name}!")
            ->line("{$this->comment->user->name} mentioned you in a comment:")
            ->line('"' . \Illuminate\Support\Str::limit($this->comment->content, 200) . '"')
            ->action('View Board', url('/nova/projects-board'))
            ->line('Thank you for using our application!');
    }

    /**
     * Get the array representation of the notification.
     *
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        return [
            //
        ];
    }
}
