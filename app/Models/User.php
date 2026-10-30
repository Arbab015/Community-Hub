<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Spatie\Permission\Traits\HasRoles;

class User extends Authenticatable
{
    use HasFactory, HasRoles, HasUuids, Notifiable;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $guarded = [];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'is_filer' => 'boolean',
        ];
    }

    public function uniqueIds(): array
    {
        return ['uuid'];
    }

    /**
     * First and last name joined, for receipts, reports and audit columns.
     */
    protected function fullName(): \Illuminate\Database\Eloquent\Casts\Attribute
    {
        return \Illuminate\Database\Eloquent\Casts\Attribute::make(
            get: fn () => trim(($this->first_name ?? '').' '.($this->last_name ?? '')) ?: null,
        );
    }

    // Single main attachment (user picture)
    public function attachment()
    {
        return $this->morphOne(Attachment::class, 'attachable')->where('is_main', true);
    }

    // Societies owned by users
    public function societies()
    {
        return $this->hasMany(Society::class, 'owner_id');
    }

    // member to which societies
    public function memberSocieties()
    {
        return $this->belongsToMany(
            Society::class,
            'member_societies'
        );
    }

    //   user has many posts
    public function posts()
    {
        return $this->hasMany(Post::class);
    }

    //   user has many comments
    public function comments()
    {
        return $this->hasMany(Comment::class);
    }

    public function properties()
    {
        return $this->hasMany(Property::class, 'owner_id');
    }

    public function propertyUnits()
    {
        return $this->hasMany(PropertyUnit::class, 'owner_id');
    }

    // Ownership history records where this user is the owner.
    public function propertyOwnerships()
    {
        return $this->hasMany(PropertyOwner::class, 'owner_id');
    }

    public function imports()
    {
        return $this->hasMany(Import::class);
    }
}
