<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

class Property extends Model
{
    use HasUuids;

    protected $guarded = [];

    public function uniqueIds(): array
    {
        return ['uuid'];
    }

    public function dimensions()
    {
        return $this->morphMany(Dimension::class, 'dimensionable');
    }

    public function attachment()
    {
        return $this->morphOne(Attachment::class, 'attachable')->where('is_main', true);
    }

    // for multiple documents related to society
    public function attachments()
    {
        return $this->morphMany(Attachment::class, 'attachable')->where('is_main', false);
    }

    public function block()
    {
        return $this->belongsTo(Block::class, 'block_id');
    }

    public function floors()
    {
        return $this->hasMany(Floor::class, 'property_id');
    }

    public function owner()
    {
        return $this->belongsTo(User::class, 'owner_id');
    }
}
