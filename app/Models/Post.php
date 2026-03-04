<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

class Post extends Model
{
    use HasUuids;
    protected $guarded = [];
    public function uniqueIds(): array
    {
        return ['uuid'];
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function society()
    {
        return $this->belongsTo(Society::class);
    }

    public function comments()
    {
        return $this->hasMany(Comment::class);
    }

    // morph relation
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


    // pivot 
    public function tags()
    {
        return $this->belongsToMany(Tag::class);
    }

    // reports
    public function reports()
    {
        return $this->morphMany(Report::class, 'reportable');
    }
}