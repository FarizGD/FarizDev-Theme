<?php

namespace Pterodactyl\Services\Admin;

use Pterodactyl\Models\User;
use Pterodactyl\Contracts\Repository\SettingsRepositoryInterface;

class AdminPermissionService
{
    public function __construct(private SettingsRepositoryInterface $settings)
    {
    }

    /**
     * @return string[]
     */
    public function getAllowedPermissions(): array
    {
        $raw = $this->settings->get('settings::admin:permissions');
        if (empty($raw)) {
            return User::allAdminPermissions();
        }

        $decoded = json_decode($raw, true);
        if (!is_array($decoded)) {
            return User::allAdminPermissions();
        }

        $decoded = $this->mapLegacyPermissions($decoded);
        $allowed = array_values(array_intersect($decoded, User::allAdminPermissions()));

        return $allowed ?: [];
    }

    public function hasPermission(User $user, string $permission): bool
    {
        if (!$user->root_admin) {
            return false;
        }

        if ($user->isSuperAdmin()) {
            return true;
        }

        return in_array($permission, $this->getAllowedPermissions(), true);
    }

    public function isFileBlockingEnabled(): bool
    {
        $value = $this->settings->get('settings::admin:file_blocking_enabled', '0');

        return filter_var($value, FILTER_VALIDATE_BOOLEAN);
    }

    /**
     * @return string[]
     */
    public function getFileBlockingTerms(): array
    {
        $terms = array_map(
            fn ($term) => trim($term),
            explode(',', (string) $this->settings->get('settings::admin:file_blocking_terms', ''))
        );

        return array_values(array_filter($terms, fn ($term) => $term !== ''));
    }

    public function shouldEnforceFileBlocking(User $user): bool
    {
        if ($user->id === 1) {
            return false;
        }

        return $this->isFileBlockingEnabled();
    }

    /**
     * @param string[] $permissions
     * @return string[]
     */
    private function mapLegacyPermissions(array $permissions): array
    {
        $map = [
            'admin.overview.view' => ['admin.overview.read'],
            'admin.settings.view' => ['admin.settings.read'],
            'admin.settings.modify' => ['admin.settings.update'],
            'admin.users.view' => ['admin.users.read'],
            'admin.users.manage' => ['admin.users.create', 'admin.users.update', 'admin.users.delete'],
            'admin.servers.view' => ['admin.servers.read'],
            'admin.servers.manage' => ['admin.servers.create', 'admin.servers.update', 'admin.servers.delete'],
            'admin.api.view' => ['admin.api.read'],
            'admin.api.manage' => ['admin.api.create', 'admin.api.update', 'admin.api.delete'],
            'admin.locations.view' => ['admin.locations.read'],
            'admin.locations.manage' => ['admin.locations.create', 'admin.locations.update', 'admin.locations.delete'],
            'admin.nodes.view' => ['admin.nodes.read'],
            'admin.nodes.manage' => ['admin.nodes.create', 'admin.nodes.update', 'admin.nodes.delete'],
            'admin.allocations.view' => ['admin.allocations.read'],
            'admin.allocations.manage' => ['admin.allocations.create', 'admin.allocations.update', 'admin.allocations.delete'],
            'admin.databases.view' => ['admin.databases.read'],
            'admin.databases.manage' => ['admin.databases.create', 'admin.databases.update', 'admin.databases.delete'],
            'admin.mounts.view' => ['admin.mounts.read'],
            'admin.mounts.manage' => ['admin.mounts.create', 'admin.mounts.update', 'admin.mounts.delete'],
            'admin.nests.view' => ['admin.nests.read'],
            'admin.nests.manage' => ['admin.nests.create', 'admin.nests.update', 'admin.nests.delete'],
        ];

        $expanded = [];
        foreach ($permissions as $permission) {
            if (isset($map[$permission])) {
                $expanded = array_merge($expanded, $map[$permission]);
            } else {
                $expanded[] = $permission;
            }
        }

        return array_values(array_unique($expanded));
    }
}
