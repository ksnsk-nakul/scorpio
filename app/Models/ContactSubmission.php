<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ContactSubmission extends Model
{
    protected $fillable = ['user_id', 'name', 'email', 'message', 'page_slug', 'read'];
}
