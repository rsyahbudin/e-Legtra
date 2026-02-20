<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Str;
use Laravel\Fortify\TwoFactorAuthenticatable;

class User extends Authenticatable
{
    /** @use HasFactory<\Database\Factories\UserFactory> */
    use HasFactory, Notifiable, TwoFactorAuthenticatable;

    protected $table = 'LGL_USER';

    protected $primaryKey = 'LGL_ROW_ID';

    const CREATED_AT = 'USER_CREATED_DT';

    const UPDATED_AT = 'USER_UPDATED_DT';

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'USER_FULLNAME',
        'USER_EMAIL',
        'USER_PASSWORD',
        'USER_ROLE_ID',
        'DIV_ID',
        'DEPT_ID',
        'USER_ID_NUMBER',
        'USER_EMAIL_VERIFIED_DT',
        'USER_CREATED_BY',
        'USER_UPDATED_BY',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = [
        'USER_PASSWORD',
        'USER_REMEMBER_TOKEN',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'USER_PASSWORD' => 'hashed',
        ];
    }

    public function getAuthPasswordName(): string
    {
        return 'USER_PASSWORD';
    }

    public function getAuthPassword(): ?string
    {
        return $this->USER_PASSWORD;
    }

    public function getRememberTokenName(): string
    {
        return 'USER_REMEMBER_TOKEN';
    }

    // public function getAuthIdentifierName()
    // {
    //     return 'USER_EMAIL';
    // }

    public function getNameAttribute(): ?string
    {
        return $this->USER_FULLNAME;
    }

    public function getEmailAttribute(): ?string
    {
        return $this->USER_EMAIL;
    }

    /**
     * Get the user's initials
     */
    public function initials(): string
    {
        return Str::of($this->USER_FULLNAME)
            ->explode(' ')
            ->take(2)
            ->map(fn ($word) => Str::substr($word, 0, 1))
            ->implode('');
    }

    /**
     * Get the role for this user.
     */
    public function role(): BelongsTo
    {
        return $this->belongsTo(Role::class, 'USER_ROLE_ID');
    }

    /**
     * Get the division for this user.
     */
    public function division(): BelongsTo
    {
        return $this->belongsTo(Division::class, 'DIV_ID');
    }

    /**
     * Get the department for this user.
     */
    public function department(): BelongsTo
    {
        return $this->belongsTo(Department::class, 'DEPT_ID');
    }

    /**
     * Get contracts assigned to this user as PIC.
     */
    public function assignedContracts(): HasMany
    {
        return $this->hasMany(Contract::class, 'CONTR_PIC_ID');
    }

    /**
     * Get contracts created by this user.
     */
    public function createdContracts(): HasMany
    {
        return $this->hasMany(Contract::class, 'CONTR_CREATED_BY');
    }

    /**
     * Get notifications for this user.
     */
    public function internalNotifications(): HasMany
    {
        return $this->hasMany(Notification::class, 'user_id');
    }

    /**
     * Get unread notifications count.
     */
    public function getUnreadNotificationsCountAttribute(): int
    {
        return $this->internalNotifications()->unread()->count();
    }

    /**
     * Get all super admin and legal users for notifications.
     */
    public static function getAdminAndLegalUsers()
    {
        return static::whereHas('role', function ($q) {
            $q->whereIn('ROLE_SLUG', ['super-admin', 'legal']);
        })->get();
    }

    /**
     * Check if user has a specific role.
     */
    public function hasRole(string $slug): bool
    {
        return $this->role && $this->role->ROLE_SLUG === $slug;
    }

    /**
     * Check if user has any of the given roles.
     */
    public function hasAnyRole(array $slugs): bool
    {
        return $this->role && in_array($this->role->ROLE_SLUG, $slugs);
    }

    /**
     * Check if user has a specific permission.
     */
    public function hasPermission(string $slug): bool
    {
        if (! $this->role) {
            return false;
        }

        // Super admin has all permissions
        if ($this->role->ROLE_SLUG === 'super-admin') {
            return true;
        }

        return $this->role->hasPermission($slug);
    }

    /**
     * Check if user is a super admin.
     */
    public function isSuperAdmin(): bool
    {
        return $this->hasRole('super-admin');
    }

    /**
     * Check if user is legal team.
     */
    public function isLegal(): bool
    {
        return $this->hasRole('legal');
    }

    /**
     * Check if user is a PIC.
     */
    public function isPic(): bool
    {
        return $this->hasRole('pic');
    }

    /**
     * Check if user is management.
     */
    public function isManagement(): bool
    {
        return $this->hasRole('management');
    }

    /**
     * Check if user can manage other users.
     */
    public function canManageUsers(): bool
    {
        return $this->hasPermission('users.manage');
    }

    /**
     * Check if user can manage contracts.
     */
    public function canManageContracts(): bool
    {
        return $this->hasPermission('contracts.edit');
    }
}
