<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Category extends Model
{
    protected $fillable = ['name', 'slug', 'emoji', 'position', 'admin_only'];

    protected function casts(): array
    {
        return ['admin_only' => 'boolean'];
    }

    public function community(): BelongsTo
    {
        return $this->belongsTo(Community::class);
    }

    public function posts(): HasMany
    {
        return $this->hasMany(Post::class);
    }
}
