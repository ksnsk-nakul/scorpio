<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class TaskActivity extends Model
{
    protected $fillable = ['task_id', 'user_id', 'field', 'from', 'to'];

    public function task(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(Task::class);
    }

    public function user(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
