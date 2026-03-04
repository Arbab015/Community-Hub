<?php

namespace App\Jobs;

use App\Events\PusherNotification;
use App\Models\Post;
use App\Models\User;
use App\Notifications\NewPostNotification;
use Exception;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Bus\Queueable;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;

class NewPostJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $postId;
    public $userId;

    public function __construct($postId, $userId)
    {
        $this->postId = $postId;
        $this->userId = $userId;
        logger(['this user', $this->postId]);
        logger(['this user', $this->userId]);
    }

    public function handle(): void
    {
        logger('eeeeeeeeee');
        Log::info('NewPostJob started', [
            'post_id' => $this->postId,
            'user_id' => $this->userId,
        ]);

        $post = Post::with('society.members')->find($this->postId);
        $user = User::find($this->userId);

        if (!$post) {
            Log::warning('NewPostJob: Post not found', [
                'post_id' => $this->postId
            ]);
            return;
        }
        if (!$user) {
            Log::warning('NewPostJob: User not found', [
                'user_id' => $this->userId
            ]);
            return;
        }

        $members = $post->society?->members;
        if (!$members) {
            Log::warning('NewPostJob: No society members found', [
                'post_id' => $this->postId
            ]);
            return;
        }

        $filteredMembers = $members->where('id', '!=', $user->id);
        Log::info('NewPostJob: Sending notifications', [
            'total_members' => $members->count(),
            'notified_members' => $filteredMembers->count(),
        ]);

        foreach ($filteredMembers as $member) {
            try {
                $member->notify(new NewPostNotification($post, $user));
                $latest_notification = $member->notifications()->latest()->first();
                broadcast(new PusherNotification($member, $latest_notification, $post))->toOthers();
                Log::info('Notification sent', [
                    'member_id' => $member->id
                ]);
            } catch (Exception $e) {
                Log::error('Notification failed', [
                    'member_id' => $member->id,
                    'error' => $e->getMessage()
                ]);
            }
        }
        Log::info('NewPostJob completed successfully');
    }
}
