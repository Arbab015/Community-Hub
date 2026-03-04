<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class PostAutoDeletionNotification extends Notification
{
    use Queueable;
    public $post;

    public function __construct($post)
    {
        $this->post = $post;
        logger('Notification constructor called', ['post' => $post->id]);
    }

    public function via(object $notifiable): array
    {
        return ['mail', 'database'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject('Your Post Was Auto Deleted')
            ->line('Your post titled "' . $this->post->title . '" was automatically deleted.')
            ->line('Reason: No comments were received within 1 minute.')
            ->line('You can create a new post anytime.')
            ->line('Thank you for using our application!');
    }

    public function toArray(object $notifiable): array
    {
        return [
            'title' => 'Alert ',
            'message' => 'Your post was automatically deleted due to no comments.',
        ];
    }
}