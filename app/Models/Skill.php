<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Skill extends Model
{
    protected $fillable = ['user_id', 'name', 'icon', 'sort_order', 'is_seeded'];
    protected $casts    = ['is_seeded' => 'boolean'];
}
