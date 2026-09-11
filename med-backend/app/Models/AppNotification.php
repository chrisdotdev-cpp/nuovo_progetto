<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Notifica in-app. */
class AppNotification extends Model
{
    use HasFactory;

    protected $fillable = ['user_id', 'category', 'level', 'title', 'body', 'link', 'read_at'];

    protected function casts(): array
    {
        return ['read_at' => 'datetime'];
    }

    protected $appends = ['is_read'];

    public function getIsReadAttribute(): bool
    {
        return $this->read_at !== null;
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function scopeUnread(Builder $query): Builder
    {
        return $query->whereNull('read_at');
    }

    public function scopeCategory(Builder $query, ?string $category): Builder
    {
        return blank($category) ? $query : $query->where('category', $category);
    }

    public function markAsRead(): void
    {
        if ($this->read_at === null) {
            $this->forceFill(['read_at' => now()])->save();
        }
    }
}
