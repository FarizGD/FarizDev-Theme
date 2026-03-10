<?php

namespace Pterodactyl\Http\Middleware\Api\Application;

use Illuminate\Http\Request;
use Pterodactyl\Services\Admin\AdminPermissionService;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;

class AuthenticateApplicationUser
{
    public function __construct(private AdminPermissionService $permissions)
    {
    }

    /**
     * Authenticate that the currently authenticated user is an administrator
     * and should be allowed to proceed through the application API.
     */
    public function handle(Request $request, \Closure $next): mixed
    {
        /** @var \Pterodactyl\Models\User|null $user */
        $user = $request->user();
        if (!$user || !$user->root_admin) {
            throw new AccessDeniedHttpException('This account does not have permission to access the API.');
        }

        $method = strtoupper($request->getMethod());
        $action = match ($method) {
            'POST' => 'create',
            'PUT', 'PATCH' => 'update',
            'DELETE' => 'delete',
            default => 'read',
        };

        if (!$this->permissions->hasPermission($user, "admin.api.$action")) {
            throw new AccessDeniedHttpException('This account does not have permission to access the API.');
        }

        return $next($request);
    }
}
