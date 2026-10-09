<?php

namespace App\Models;

use Database\Factories\UserFactory;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Carbon;
use Laravel\Fortify\Contracts\PasskeyUser;
use Laravel\Fortify\PasskeyAuthenticatable;
use Laravel\Fortify\TwoFactorAuthenticatable;

/**
 * @property int $id
 * @property int|null $role_id
 * @property string $name
 * @property string|null $username
 * @property string $email
 * @property Carbon|null $email_verified_at
 * @property string $password
 * @property bool $is_active
 * @property string|null $two_factor_secret
 * @property string|null $two_factor_recovery_codes
 * @property Carbon|null $two_factor_confirmed_at
 * @property string|null $remember_token
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
class User extends Authenticatable implements MustVerifyEmail, PasskeyUser
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable, PasskeyAuthenticatable, TwoFactorAuthenticatable;

    protected $fillable = [
        'role_id',
        'name',
        'username',
        'email',
        'password',
        'is_active',
    ];

    protected $hidden = [
        'password',
        'two_factor_secret',
        'two_factor_recovery_codes',
        'remember_token',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'is_active' => 'boolean',
            'two_factor_confirmed_at' => 'datetime',
        ];
    }

    public function role(): BelongsTo
    {
        return $this->belongsTo(Role::class);
    }

    public function employee(): HasOne
    {
        return $this->hasOne(Employee::class);
    }

    public function dailyCashflowsClosed(): HasMany
    {
        return $this->hasMany(DailyCashflow::class, 'closing_by');
    }

    public function dailyCashflowsVerified(): HasMany
    {
        return $this->hasMany(DailyCashflow::class, 'verified_by');
    }

    public function hasRole(string|array $roles): bool
    {
        if (! $this->role) {
            return false;
        }

        if (is_array($roles)) {
            return in_array($this->role->name, $roles, true);
        }

        return $this->role->name === $roles;
    }

    public function isAdminPusat(): bool
    {
        return $this->hasRole('admin_pusat');
    }

    public function isStaffCabang(): bool
    {
        return $this->hasRole('staff_cabang');
    }

    // Aliases untuk kompatibilitas kode
    public function isOwner(): bool
    {
        return $this->isAdminPusat();
    }

    public function isSuperAdmin(): bool
    {
        return $this->isAdminPusat();
    }

    public function isFinance(): bool
    {
        return $this->isAdminPusat();
    }

    public function isStoreManager(): bool
    {
        return $this->isStaffCabang();
    }

    public function isCrew(): bool
    {
        return $this->isStaffCabang();
    }
}
