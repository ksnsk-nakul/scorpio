<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class Project extends Model
{
    protected $fillable = [
        'workspace_id','name','slug','description',
        'github_repo','github_project_id','github_webhook_secret','cover_image','status','sort_order',
    ];

    protected $hidden = ['github_webhook_secret'];

    protected static function booted(): void
    {
        static::creating(fn ($p) => $p->slug ??= Str::slug($p->name));
    }

    public function workspace(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(Workspace::class);
    }

    public function tasks(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(Task::class)->orderBy('sort_order');
    }

    public function rootTasks(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(Task::class)->whereNull('parent_id')->orderBy('sort_order');
    }

    public function media(): \Illuminate\Database\Eloquent\Relations\MorphMany
    {
        return $this->morphMany(Media::class, 'mediable');
    }
}
