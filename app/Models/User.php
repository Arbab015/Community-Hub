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
    protected $fillable = [
        'uuid',
        'first_name',
        'last_name',
        'email',
        'password',
        'dob',
        'country',
        'cnic_passport',
        'gender',
        'marital_status',
        'profession',
        'contact',
        'emergency_contact',
        'present_address',
        'permanent_address',
    ];

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
        ];
    }

    public function uniqueIds(): array
    {
        return ['uuid'];
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
}
