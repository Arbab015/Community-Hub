<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;


class Comment extends Model
{ 
     use HasUuids;
    protected $guarded = [];


     public function uniqueIds(): array
    {
        return ['uuid'];
    }
    // Single image
    public function attachment()
    {
        return $this->morphOne(Attachment::class, 'attachable')->where('is_main', true);
    }


    public function post()
    {
        return $this->belongsTo(Post::class);
    }


    // Parent comment
    public function parent()
    {
        return $this->belongsTo(Comment::class, 'parent_id')->with('user');
    }

    public function replies()
    {
        return $this->hasMany(Comment::class, 'parent_id')
            ->with('user', 'attachment', 'reactions',  'userReaction'); // recursive
    }


    public function user()
    {
        return $this->belongsTo(User::class)->with('attachment');
    }
    // morhph relation
    public function reactions()
    {
        return $this->morphMany(Reaction::class, 'reactionable');
    }

    public function likes()
    {
        return $this->reactions()->where('type', 'like');
    }

    public function dislikes()
    {
        return $this->reactions()->where('type', 'dislike');
    }


    public function userReaction()
    {
        return $this->morphOne(Reaction::class, 'reactionable')
            ->where('user_id', auth()->id());
    }

    //reports
    public function reports()
    {
        return $this->morphMany(Report::class, 'reportable');
    }
}