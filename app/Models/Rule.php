<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Rule extends Model
{
  protected $guarded = [];


  protected $casts = [
    'related_to' => 'array',
  ];

  public function societyOwner()
  {
    return $this->belongsTo(User::class, 'society_owner_id');
  }
}
