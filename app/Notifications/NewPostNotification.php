<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;
use Illuminate\Notifications\Messages\DatabaseMessage;
use Illuminate\Notifications\Messages\MailMessage;

class NewPostNotification extends Notification
{
    use Queueable;
    protected $post;
    protected $user;
    public function __construct($post, $user)
    {
        $this->post = $post;
        $this->user = $user;
    }

    public function via(object $notifiable): array
    {
        return ['mail', 'database'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $url = Route('posts.view', [
            'type' => $this->post->category . 's',
            'slug' => $this->post->slug,
        ]);
        return (new MailMessage)
            ->subject('New Post Created')
            ->line($this->user->first_name . ' ' .
                $this->user->last_name .
                ' has created a new post.')
            ->action('View Post', $url);
    }


    public function toArray(object $notifiable): array
    {
        return [
            'avator' => $this->user->attachment->link,
            'title' => 'New Post Created',
            'message' => ucfirst($this->user->first_name) . ' ' .
                ucfirst($this->user->last_name) . ' has created a new post.',
        ];
    }
}
