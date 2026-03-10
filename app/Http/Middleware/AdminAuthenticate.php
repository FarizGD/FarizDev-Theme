<?php

namespace Pterodactyl\Http\Middleware;

use Illuminate\Support\Str;
use Illuminate\Http\Request;
use Pterodactyl\Services\Admin\AdminPermissionService;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;

class AdminAuthenticate
{
    public function __construct(private AdminPermissionService $permissions)
    {
    }

    /**
     * Handle an incoming request.
     *
     * @throws AccessDeniedHttpException
     */
    public function handle(Request $request, \Closure $next): mixed
    {
        if (!$request->user() || !$request->user()->root_admin) {
            throw new AccessDeniedHttpException();
        }

        $user = $request->user();
        $permission = $this->resolvePermission($request);
        if ($permission && !$this->permissions->hasPermission($user, $permission)) {
            throw new AccessDeniedHttpException('You do not have permission to access this admin area.');
        }

        return $next($request);
    }

    /**
     * Resolve the permission required for the current route.
     */
    protected function resolvePermission(Request $request): ?string
    {
        $route = $request->route();
        if (!$route) {
            return null;
        }

        $name = $route->getName();
        if (!$name) {
            return null;
        }

        $method = strtoupper($request->getMethod());
        $action = match ($method) {
            'POST' => 'create',
            'PUT', 'PATCH' => 'update',
            'DELETE' => 'delete',
            default => 'read',
        };

        if ($name === 'admin.index') {
            return 'admin.overview.read';
        }

        if (Str::startsWith($name, 'admin.settings')) {
            return $action === 'read' ? 'admin.settings.read' : 'admin.settings.update';
        }

        if (Str::startsWith($name, 'admin.api')) {
            return "admin.api.$action";
        }

        if (Str::startsWith($name, 'admin.users')) {
            return $this->resolveCrudPermission($name, $action, 'admin.users');
        }

        if (Str::startsWith($name, 'admin.servers')) {
            if ($this->isCreationRoute($name)) {
                return 'admin.servers.create';
            }

            return "admin.servers.$action";
        }

        if (Str::startsWith($name, 'admin.locations')) {
            return "admin.locations.$action";
        }

        if (Str::startsWith($name, 'admin.databases')) {
            return "admin.databases.$action";
        }

        if (Str::startsWith($name, 'admin.mounts')) {
            return "admin.mounts.$action";
        }

        if (Str::startsWith($name, 'admin.nests')) {
            if ($this->isCreationRoute($name)) {
                return 'admin.nests.create';
            }

            return "admin.nests.$action";
        }

        if (Str::startsWith($name, 'admin.nodes.view.allocation')) {
            return "admin.allocations.$action";
        }

        if (Str::startsWith($name, 'admin.nodes')) {
            if ($this->isCreationRoute($name)) {
                return 'admin.nodes.create';
            }

            return "admin.nodes.$action";
        }

        return null;
    }

    /**
     * Resolve a basic CRUD permission based on route name and method.
     */
    protected function resolveCrudPermission(string $name, string $action, string $base): ?string
    {
        if ($this->isCreationRoute($name)) {
            return "{$base}.create";
        }

        return "{$base}.{$action}";
    }

    /**
     * Determine if a route is for creation screens.
     */
    protected function isCreationRoute(string $name): bool
    {
        return Str::endsWith($name, '.new') || Str::endsWith($name, '.create');
    }
}
