<?php

namespace App\Models;

use App\Domain\Access\Role;
use App\Domain\Practice\Models\Doctor;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Str;
use PHPOpenSourceSaver\JWTAuth\Contracts\JWTSubject;
use Spatie\Permission\Traits\HasRoles;

/**
 * @property int $id
 * @property string $name
 * @property string $email
 * @property string|null $phone
 * @property bool $is_active
 * @property int $token_version
 */
class User extends Authenticatable implements JWTSubject
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, HasRoles, Notifiable;

    /**
     * Roles and permissions live on the `web` guard; requests authenticated
     * by the JWT (`api`) guard are checked against the same set.
     */
    protected string $guard_name = Role::GUARD;

    /** JWT claim and session key holding the token version. */
    public const TOKEN_VERSION_CLAIM = 'tv';

    public const TOKEN_VERSION_SESSION_KEY = 'auth.token_version';

    /** @var array<string, mixed> */
    protected $attributes = [
        'token_version' => 0,
    ];

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'email',
        'phone',
        'password',
        'is_active',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = [
        'password',
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
            'token_version' => 'integer',
        ];
    }

    /**
     * Deactivation revokes every JWT and session issued so far: the token
     * version moves on and the remember-me token is rotated, so neither
     * works again if the account is reactivated.
     */
    protected static function booted(): void
    {
        static::saving(function (User $user) {
            if ($user->exists && $user->isDirty('is_active') && ! $user->is_active) {
                $user->token_version = $user->token_version + 1;
                $user->setRememberToken(Str::random(60));
            }
        });
    }

    public function isSuperAdmin(): bool
    {
        return $this->hasRole(Role::SUPER_ADMIN);
    }

    /**
     * The doctor profile, if this user has one.
     *
     * @return HasOne<Doctor, $this>
     */
    public function doctor(): HasOne
    {
        return $this->hasOne(Doctor::class);
    }

    /**
     * Get the identifier that will be stored in the subject claim of the JWT.
     */
    public function getJWTIdentifier(): mixed
    {
        return $this->getKey();
    }

    /**
     * Return a key value array, containing any custom claims to be added to the JWT.
     *
     * Informational only: authorization always re-reads permissions from the
     * database, so role changes apply before the token expires.
     *
     * @return array<string, mixed>
     */
    public function getJWTCustomClaims(): array
    {
        return [
            'roles' => $this->getRoleNames()->values()->all(),
            // Checked by EnsureUserIsActive against the current value.
            self::TOKEN_VERSION_CLAIM => $this->token_version,
        ];
    }
}
