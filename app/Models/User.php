<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Str;
use Spatie\Permission\Traits\HasRoles;

class User extends Authenticatable
{
    use HasFactory, Notifiable, HasRoles;

    protected $fillable = [
        'name', 'email', 'password', 'avatar',
        'username', 'github_token',
        'google_id', 'github_id', 'email_verified_at',
    ];

    protected $hidden = ['password', 'remember_token', 'github_token'];

    protected $casts = [
        'password'     => 'hashed',
        'github_token' => 'encrypted',
    ];

    protected static function booted(): void
    {
        static::creating(function (self $user) {
            if (empty($user->username)) {
                $base = Str::slug($user->name ?? explode('@', $user->email)[0]);
                $user->username = static::uniqueUsername($base);
            }
        });
    }

    public static function uniqueUsername(string $base): string
    {
        $slug = $base;
        $i = 2;
        while (static::where('username', $slug)->exists()) {
            $slug = "{$base}{$i}";
            $i++;
        }
        return $slug;
    }

    public function pages(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(Page::class);
    }

    public function serviceCards(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(ServiceCard::class);
    }

    public function workspaces(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(Workspace::class);
    }

    public function tasks(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(Task::class, 'assignee_id');
    }

    public function comments(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(Comment::class);
    }
}
