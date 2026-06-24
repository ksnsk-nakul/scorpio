<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ThirdPartySetting extends Model
{
    protected $fillable = ['provider', 'key', 'value', 'group', 'is_active'];

    protected $casts = ['is_active' => 'boolean'];

    public static function getValue(string $provider, string $key): ?string
    {
        return static::where('provider', $provider)
            ->where('key', $key)
            ->where('is_active', true)
            ->value('value');
    }
}
