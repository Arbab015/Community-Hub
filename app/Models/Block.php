<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

class Block extends Model
{
  use HasUuids;

  protected $guarded = [];


  public function uniqueIds(): array
  {
    return ['uuid'];
  }

  public function society(){
    return $this->belongsTo(Society::class, 'society_id');
  }


  public function properties(){
    return $this->hasMany(Property::class, 'block_id');
  }

}
