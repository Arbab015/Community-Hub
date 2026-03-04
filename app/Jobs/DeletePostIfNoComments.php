<?php

namespace App\Jobs;

use App\Events\PusherNotification;
use App\Models\Post;
use App\Notifications\PostAutoDeletionNotification;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Notifications\AnonymousNotifiable;
use Illuminate\Support\Facades\Auth;

class DeletePostIfNoComments implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $postId;

    public function __construct($postId)
    {
        $this->postId = $postId;
    }

    public function handle()
    {
        logger("Checking post for deletion", ['post_id' => $this->postId]);
        $post = Post::with('user')->find($this->postId);
        $user =  $post->user;
        if ($post && $post->comments()->count() === 0) {
            $post->user->notifyNow(new PostAutoDeletionNotification($post));
            $latest_notification = $post->user->notifications()->latest()->first();
            broadcast(new PusherNotification($user, $latest_notification, $post))->toOthers();
            $post->delete();
            logger("Post deleted and notification sent", ['post_id' => $this->postId]);
        }
    }
}