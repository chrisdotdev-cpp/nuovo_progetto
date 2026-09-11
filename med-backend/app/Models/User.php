<?php

namespace App\Models;

use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

/**
 * Account applicativo. Il profilo clinico/professionale vive in Patient o Doctor.
 *
 * @property string $role  admin|medico|paziente
 */
class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasApiTokens, HasFactory, Notifiable, SoftDeletes;

    // Costanti di ruolo: evitano stringhe magiche sparse nel codice
    public const ROLE_ADMIN    = 'admin';
    public const ROLE_MEDICO   = 'medico';
    public const ROLE_PAZIENTE = 'paziente';

    protected $fillable = [
        'name',
        'email',
        'password',
        'role',
        'status',
        'phone',
        'avatar_path',
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'last_login_at'     => 'datetime',
            'password'          => 'hashed',
        ];
    }

    /* ---------------------------------------------------------------------
     | Relazioni
     * -------------------------------------------------------------------*/

    public function patient(): HasOne
    {
        return $this->hasOne(Patient::class);
    }

    public function doctor(): HasOne
    {
        return $this->hasOne(Doctor::class);
    }

    public function notifications(): HasMany
    {
        return $this->hasMany(AppNotification::class)->latest();
    }

    public function activityLogs(): HasMany
    {
        return $this->hasMany(ActivityLog::class);
    }

    /* ---------------------------------------------------------------------
     | Helper di ruolo
     * -------------------------------------------------------------------*/

    public function isAdmin(): bool
    {
        return $this->role === self::ROLE_ADMIN;
    }

    public function isDoctor(): bool
    {
        return $this->role === self::ROLE_MEDICO;
    }

    public function isPatient(): bool
    {
        return $this->role === self::ROLE_PAZIENTE;
    }

    public function isActive(): bool
    {
        return $this->status === 'attivo';
    }

    /* ---------------------------------------------------------------------
     | Scopes
     * -------------------------------------------------------------------*/

    public function scopeRole(Builder $query, string $role): Builder
    {
        return $query->where('role', $role);
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('status', 'attivo');
    }

    /** Ricerca full-text semplice su nome ed email, usata dalle tabelle admin. */
    public function scopeSearch(Builder $query, ?string $term): Builder
    {
        if (blank($term)) {
            return $query;
        }

        return $query->where(function (Builder $q) use ($term) {
            $q->where('name', 'like', "%{$term}%")
              ->orWhere('email', 'like', "%{$term}%");
        });
    }
}
