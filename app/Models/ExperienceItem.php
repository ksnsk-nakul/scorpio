<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ExperienceItem extends Model
{
    protected $fillable = ['user_id', 'period', 'title', 'company', 'description', 'sort_order', 'is_seeded'];
    protected $casts    = ['is_seeded' => 'boolean'];
}
