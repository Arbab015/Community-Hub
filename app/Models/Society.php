<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

class Society extends Model
{
    use HasUuids;
    protected $guarded = [];

    public function uniqueIds(): array
    {
        return ['uuid'];
    }

    // Single main attachment (society  picture)
    public function attachment()
    {
        return $this->morphOne(Attachment::class, 'attachable')->where('is_main', true);
    }

    // for multiple documents related to society
    public function attachments()
    {
        return $this->morphMany(Attachment::class, 'attachable')->where('is_main', false);
    }

    // Societies owned by user
    public function owner()
    {
        return $this->belongsTo(User::class, 'owner_id');
    }

    public function posts()
    {
        return $this->hasMany(Post::class, 'society_id');
    }

    // society members
    public function members()
    {
        return $this->belongsToMany(
            User::class,
            'member_societies'
        );
    }

    // property blocks
    public function blocks()
    {
        return $this->hasMany(Block::class, 'society_id');
    }
}
