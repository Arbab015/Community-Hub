<?php

namespace App\Events;

use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PresenceChannel;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class PusherNotification implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    protected $user;
    protected $latest_notification;
    protected $post_id;
    public function __construct($user,  $latest_notification, $post)
    {
        $this->user = $user;
        $this->latest_notification = $latest_notification;
        $this->post_id = $post->id;

        logger(['this user', $this->user]);
        logger(['this post id dddddddddddddddddddd', $this->post_id]);
        logger(['this latest_notification', $this->latest_notification]);
    }


    public function broadcastOn()
    {
        return new Channel('notification.' . $this->user->id);
    }

    public function broadcastWith()
    {
        return [
            'post_id' => $this->post_id,
            'user_id' => $this->user->id,
            'latest_notification' =>  $this->latest_notification,
        ];
    }
}
