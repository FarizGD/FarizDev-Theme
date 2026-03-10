<?php

namespace Pterodactyl\Models;

use Pterodactyl\Rules\Username;
use Pterodactyl\Facades\Activity;
use Illuminate\Support\Collection;
use Illuminate\Validation\Rules\In;
use Illuminate\Auth\Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Database\Eloquent\Builder;
use Pterodactyl\Contracts\Models\Identifiable;
use Pterodactyl\Models\Traits\HasAccessTokens;
use Illuminate\Auth\Passwords\CanResetPassword;
use Pterodactyl\Traits\Helpers\AvailableLanguages;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\Access\Authorizable;
use Pterodactyl\Models\Traits\HasRealtimeIdentifier;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\MorphToMany;
use Illuminate\Contracts\Auth\Authenticatable as AuthenticatableContract;
use Illuminate\Contracts\Auth\Access\Authorizable as AuthorizableContract;
use Illuminate\Contracts\Auth\CanResetPassword as CanResetPasswordContract;
use Pterodactyl\Notifications\SendPasswordReset as ResetPasswordNotification;

/**
 * Pterodactyl\Models\User.
 *
 * @property int $id
 * @property string|null $external_id
 * @property string $uuid
 * @property string $username
 * @property string $email
 * @property string|null $name_first
 * @property string|null $name_last
 * @property string $password
 * @property string|null $remember_token
 * @property string $language
 * @property bool $root_admin
 * @property bool $use_totp
 * @property string|null $totp_secret
 * @property \Illuminate\Support\Carbon|null $totp_authenticated_at
 * @property bool $gravatar
 * @property array|null $admin_permissions
 * @property bool $admin_file_blocking_enabled
 * @property string|null $admin_file_blocking_terms
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property \Illuminate\Database\Eloquent\Collection|\Pterodactyl\Models\ApiKey[] $apiKeys
 * @property int|null $api_keys_count
 * @property string $name
 * @property \Illuminate\Notifications\DatabaseNotificationCollection|\Illuminate\Notifications\DatabaseNotification[] $notifications
 * @property int|null $notifications_count
 * @property \Illuminate\Database\Eloquent\Collection|\Pterodactyl\Models\RecoveryToken[] $recoveryTokens
 * @property int|null $recovery_tokens_count
 * @property \Illuminate\Database\Eloquent\Collection|\Pterodactyl\Models\Server[] $servers
 * @property int|null $servers_count
 * @property \Illuminate\Database\Eloquent\Collection|\Pterodactyl\Models\UserSSHKey[] $sshKeys
 * @property int|null $ssh_keys_count
 * @property \Illuminate\Database\Eloquent\Collection|\Pterodactyl\Models\ApiKey[] $tokens
 * @property int|null $tokens_count
 *
 * @method static \Database\Factories\UserFactory factory(...$parameters)
 * @method static Builder|User newModelQuery()
 * @method static Builder|User newQuery()
 * @method static Builder|User query()
 * @method static Builder|User whereCreatedAt($value)
 * @method static Builder|User whereEmail($value)
 * @method static Builder|User whereExternalId($value)
 * @method static Builder|User whereGravatar($value)
 * @method static Builder|User whereId($value)
 * @method static Builder|User whereLanguage($value)
 * @method static Builder|User whereNameFirst($value)
 * @method static Builder|User whereNameLast($value)
 * @method static Builder|User wherePassword($value)
 * @method static Builder|User whereRememberToken($value)
 * @method static Builder|User whereRootAdmin($value)
 * @method static Builder|User whereTotpAuthenticatedAt($value)
 * @method static Builder|User whereTotpSecret($value)
 * @method static Builder|User whereUpdatedAt($value)
 * @method static Builder|User whereUseTotp($value)
 * @method static Builder|User whereUsername($value)
 * @method static Builder|User whereUuid($value)
 *
 * @mixin \Eloquent
 */
#[Attributes\Identifiable('user')]
class User extends Model implements
    AuthenticatableContract,
    AuthorizableContract,
    CanResetPasswordContract,
    Identifiable
{
    use Authenticatable;
    use Authorizable;
    use AvailableLanguages;
    use CanResetPassword;
    /** @use \Pterodactyl\Models\Traits\HasAccessTokens<\Pterodactyl\Models\ApiKey> */
    use HasAccessTokens;
    use Notifiable;
    /** @use \Illuminate\Database\Eloquent\Factories\HasFactory<\Database\Factories\UserFactory> */
    use HasFactory;
    use HasRealtimeIdentifier;

    public const USER_LEVEL_USER = 0;
    public const USER_LEVEL_ADMIN = 1;

    /**
     * The resource name for this model when it is transformed into an
     * API representation using fractal.
     */
    public const RESOURCE_NAME = 'user';

    /**
     * Level of servers to display when using access() on a user.
     */
    protected string $accessLevel = 'all';

    /**
     * The table associated with the model.
     */
    protected $table = 'users';

    /**
     * A list of mass-assignable variables.
     */
    protected $fillable = [
        'external_id',
        'username',
        'email',
        'name_first',
        'name_last',
        'password',
        'language',
        'use_totp',
        'totp_secret',
        'totp_authenticated_at',
        'gravatar',
        'root_admin',
        'admin_permissions',
        'admin_file_blocking_enabled',
        'admin_file_blocking_terms',
    ];

    /**
     * Cast values to correct type.
     */
    protected $casts = [
        'root_admin' => 'boolean',
        'use_totp' => 'boolean',
        'gravatar' => 'boolean',
        'totp_authenticated_at' => 'datetime',
        'admin_permissions' => 'array',
        'admin_file_blocking_enabled' => 'boolean',
    ];

    /**
     * The attributes excluded from the model's JSON form.
     */
    protected $hidden = ['password', 'remember_token', 'totp_secret', 'totp_authenticated_at'];

    /**
     * Default values for specific fields in the database.
     */
    protected $attributes = [
        'external_id' => null,
        'root_admin' => false,
        'language' => 'en',
        'use_totp' => false,
        'totp_secret' => null,
        'admin_permissions' => null,
        'admin_file_blocking_enabled' => false,
        'admin_file_blocking_terms' => null,
    ];

    /**
     * Rules verifying that the data being stored matches the expectations of the database.
     */
    public static array $validationRules = [
        'uuid' => 'required|string|size:36|unique:users,uuid',
        'email' => 'required|email|between:1,191|unique:users,email',
        'external_id' => 'sometimes|nullable|string|max:191|unique:users,external_id',
        'username' => 'required|between:1,191|unique:users,username',
        'name_first' => 'required|string|between:1,191',
        'name_last' => 'required|string|between:1,191',
        'password' => 'sometimes|nullable|string',
        'root_admin' => 'boolean',
        'language' => 'string',
        'use_totp' => 'boolean',
        'totp_secret' => 'nullable|string',
        'admin_permissions' => 'nullable|array',
        'admin_permissions.*' => 'string',
        'admin_file_blocking_enabled' => 'boolean',
        'admin_file_blocking_terms' => 'nullable|string',
    ];

    /**
     * Admin permissions supported by the panel.
     *
     * @return array<string, array{label: string, description: string}>
     */
    public static function adminPermissionDefinitions(): array
    {
        return [
            'admin.overview.read' => [
                'label' => 'Overview: Read',
                'description' => 'View the admin overview dashboard.',
            ],
            'admin.settings.read' => [
                'label' => 'Settings: Read',
                'description' => 'View panel settings pages.',
            ],
            'admin.settings.update' => [
                'label' => 'Settings: Update',
                'description' => 'Update panel settings.',
            ],
            'admin.users.create' => [
                'label' => 'Users: Create',
                'description' => 'Create users.',
            ],
            'admin.users.read' => [
                'label' => 'Users: Read',
                'description' => 'View user listings and profiles.',
            ],
            'admin.users.update' => [
                'label' => 'Users: Update',
                'description' => 'Update users.',
            ],
            'admin.users.delete' => [
                'label' => 'Users: Delete',
                'description' => 'Delete users.',
            ],
            'admin.servers.create' => [
                'label' => 'Servers: Create',
                'description' => 'Create servers.',
            ],
            'admin.servers.read' => [
                'label' => 'Servers: Read',
                'description' => 'View other servers and their details.',
            ],
            'admin.servers.update' => [
                'label' => 'Servers: Update',
                'description' => 'Update servers and settings.',
            ],
            'admin.servers.delete' => [
                'label' => 'Servers: Delete',
                'description' => 'Delete servers.',
            ],
            'admin.api.create' => [
                'label' => 'API Keys: Create',
                'description' => 'Create application API keys.',
            ],
            'admin.api.read' => [
                'label' => 'API Keys: Read',
                'description' => 'View application API keys.',
            ],
            'admin.api.update' => [
                'label' => 'API Keys: Update',
                'description' => 'Update application API keys.',
            ],
            'admin.api.delete' => [
                'label' => 'API Keys: Delete',
                'description' => 'Revoke application API keys.',
            ],
            'admin.locations.create' => [
                'label' => 'Locations: Create',
                'description' => 'Create locations.',
            ],
            'admin.locations.read' => [
                'label' => 'Locations: Read',
                'description' => 'View locations and details.',
            ],
            'admin.locations.update' => [
                'label' => 'Locations: Update',
                'description' => 'Update locations.',
            ],
            'admin.locations.delete' => [
                'label' => 'Locations: Delete',
                'description' => 'Delete locations.',
            ],
            'admin.nodes.create' => [
                'label' => 'Nodes: Create',
                'description' => 'Create nodes.',
            ],
            'admin.nodes.read' => [
                'label' => 'Nodes: Read',
                'description' => 'View nodes and details.',
            ],
            'admin.nodes.update' => [
                'label' => 'Nodes: Update',
                'description' => 'Update nodes.',
            ],
            'admin.nodes.delete' => [
                'label' => 'Nodes: Delete',
                'description' => 'Delete nodes.',
            ],
            'admin.allocations.create' => [
                'label' => 'Allocations: Create',
                'description' => 'Create allocations.',
            ],
            'admin.allocations.read' => [
                'label' => 'Allocations: Read',
                'description' => 'View node allocations.',
            ],
            'admin.allocations.update' => [
                'label' => 'Allocations: Update',
                'description' => 'Update allocations.',
            ],
            'admin.allocations.delete' => [
                'label' => 'Allocations: Delete',
                'description' => 'Delete allocations.',
            ],
            'admin.databases.create' => [
                'label' => 'Databases: Create',
                'description' => 'Create database hosts.',
            ],
            'admin.databases.read' => [
                'label' => 'Databases: Read',
                'description' => 'View database hosts.',
            ],
            'admin.databases.update' => [
                'label' => 'Databases: Update',
                'description' => 'Update database hosts.',
            ],
            'admin.databases.delete' => [
                'label' => 'Databases: Delete',
                'description' => 'Delete database hosts.',
            ],
            'admin.mounts.create' => [
                'label' => 'Mounts: Create',
                'description' => 'Create mounts.',
            ],
            'admin.mounts.read' => [
                'label' => 'Mounts: Read',
                'description' => 'View mounts and relationships.',
            ],
            'admin.mounts.update' => [
                'label' => 'Mounts: Update',
                'description' => 'Update mounts.',
            ],
            'admin.mounts.delete' => [
                'label' => 'Mounts: Delete',
                'description' => 'Delete mounts.',
            ],
            'admin.nests.create' => [
                'label' => 'Nests: Create',
                'description' => 'Create nests and eggs.',
            ],
            'admin.nests.read' => [
                'label' => 'Nests: Read',
                'description' => 'View nests, eggs, and configuration.',
            ],
            'admin.nests.update' => [
                'label' => 'Nests: Update',
                'description' => 'Update nests and eggs.',
            ],
            'admin.nests.delete' => [
                'label' => 'Nests: Delete',
                'description' => 'Delete nests and eggs.',
            ],
        ];
    }

    /**
     * Returns a list of all admin permission keys.
     *
     * @return string[]
     */
    public static function allAdminPermissions(): array
    {
        return array_keys(self::adminPermissionDefinitions());
    }

    /**
     * True if this user bypasses all admin restrictions.
     */
    public function isSuperAdmin(): bool
    {
        return (int) $this->id === 1;
    }

    /**
     * Implement language verification by overriding Eloquence's gather
     * rules function.
     */
    public static function getRules(): array
    {
        $rules = parent::getRules();

        $rules['language'][] = new In(array_keys((new self())->getAvailableLanguages()));
        $rules['username'][] = new Username();

        return $rules;
    }

    /**
     * Return the user model in a format that can be passed over to Vue templates.
     */
    public function toVueObject(): array
    {
        return Collection::make($this->toArray())->except(['id', 'external_id'])
            ->merge(['identifier' => $this->identifier])
            ->toArray();
    }

    /**
     * Send the password reset notification.
     *
     * @param string $token
     */
    public function sendPasswordResetNotification($token)
    {
        Activity::event('auth:reset-password')
            ->withRequestMetadata()
            ->subject($this)
            ->log('sending password reset email');

        $this->notify(new ResetPasswordNotification($token));
    }

    /**
     * Store the username as a lowercase string.
     */
    public function setUsernameAttribute(string $value)
    {
        $this->attributes['username'] = mb_strtolower($value);
    }

    /**
     * Return a concatenated result for the accounts full name.
     */
    public function getNameAttribute(): string
    {
        return trim($this->name_first . ' ' . $this->name_last);
    }

    /**
     * Returns all servers that a user owns.
     *
     * @return \Illuminate\Database\Eloquent\Relations\HasMany<\Pterodactyl\Models\Server, $this>
     */
    public function servers(): HasMany
    {
        return $this->hasMany(Server::class, 'owner_id');
    }

    /**
     * @return \Illuminate\Database\Eloquent\Relations\HasMany<\Pterodactyl\Models\ApiKey, $this>
     */
    public function apiKeys(): HasMany
    {
        return $this->hasMany(ApiKey::class)
            ->where('key_type', ApiKey::TYPE_ACCOUNT);
    }

    /**
     * @return \Illuminate\Database\Eloquent\Relations\HasMany<\Pterodactyl\Models\RecoveryToken, $this>
     */
    public function recoveryTokens(): HasMany
    {
        return $this->hasMany(RecoveryToken::class);
    }

    /**
     * @return \Illuminate\Database\Eloquent\Relations\HasMany<\Pterodactyl\Models\UserSSHKey, $this>
     */
    public function sshKeys(): HasMany
    {
        return $this->hasMany(UserSSHKey::class);
    }

    /**
     * Returns all the activity logs where this user is the subject — not to
     * be confused by activity logs where this user is the _actor_.
     *
     * @return \Illuminate\Database\Eloquent\Relations\MorphToMany<\Pterodactyl\Models\ActivityLog, $this>
     */
    public function activity(): MorphToMany
    {
        return $this->morphToMany(ActivityLog::class, 'subject', 'activity_log_subjects');
    }

    /**
     * Returns all the servers that a user can access by way of being the owner of the
     * server, or because they are assigned as a subuser for that server.
     *
     * @return \Illuminate\Database\Eloquent\Builder<\Pterodactyl\Models\Server>
     */
    public function accessibleServers(): Builder
    {
        return Server::query()
            ->select('servers.*')
            ->leftJoin('subusers', 'subusers.server_id', '=', 'servers.id')
            ->where(function (Builder $builder) {
                $builder->where('servers.owner_id', $this->id)->orWhere('subusers.user_id', $this->id);
            })
            ->groupBy('servers.id');
    }
}
