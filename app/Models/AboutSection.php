<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AboutSection extends Model
{
    protected $fillable = ['user_id', 'bio', 'overview', 'is_seeded'];
    protected $casts    = ['overview' => 'array', 'is_seeded' => 'boolean'];
}
