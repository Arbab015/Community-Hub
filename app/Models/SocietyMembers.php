<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\Pivot;

class SocietyMember extends Pivot
{
    protected $table = 'society_members';
    protected $guarded = [];
}