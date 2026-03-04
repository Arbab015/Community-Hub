<?php

namespace App\Models;

use Spatie\Permission\Models\Role as SpatieRole;
use Spatie\Permission\Exceptions\RoleAlreadyExists;
use Spatie\Permission\Guard;

class Role extends SpatieRole
{
    protected $fillable = ['name', 'guard_name', 'user_id'];
    /**
     * Override Spatie create to include user_id uniqueness
     */

    
    public static function create(array $attributes = [])
    {
        $attributes['guard_name'] ??= Guard::getDefaultName(static::class);
        if (! isset($attributes['user_id'])) {
            throw new \InvalidArgumentException('user_id is required when creating a role');
        }
        $params = [
            'name'    => $attributes['name'],
            'user_id' => $attributes['user_id'],
        ];

        // check per user instead of per guard
        if (static::query()->where($params)->exists()) {
            throw RoleAlreadyExists::create(
                $attributes['name'],
                $attributes['guard_name']
            );
        }

        return static::query()->create($attributes);
    }

    public function creator()
    {
        return $this->belongsTo(User::class, 'user_id');
    }
}
