<?php

namespace Pterodactyl\Http\Controllers\Admin\Settings;

use Illuminate\View\View;
use Illuminate\Http\RedirectResponse;
use Prologue\Alerts\AlertsMessageBag;
use Illuminate\Contracts\Console\Kernel;
use Pterodactyl\Http\Controllers\Controller;
use Pterodactyl\Traits\Helpers\AvailableLanguages;
use Pterodactyl\Models\User;
use Pterodactyl\Services\Helpers\SoftwareVersionService;
use Pterodactyl\Contracts\Repository\SettingsRepositoryInterface;
use Pterodactyl\Http\Requests\Admin\Settings\BaseSettingsFormRequest;

class IndexController extends Controller
{
    use AvailableLanguages;

    /**
     * IndexController constructor.
     */
    public function __construct(
        private AlertsMessageBag $alert,
        private Kernel $kernel,
        private SettingsRepositoryInterface $settings,
        private SoftwareVersionService $versionService,
    ) {
    }

    /**
     * Render the UI for basic Panel settings.
     */
    public function index(): View
    {
        $rawPermissions = $this->settings->get('settings::admin:permissions');
        $decodedPermissions = json_decode((string) $rawPermissions, true);
        $allowedPermissions = is_array($decodedPermissions)
            ? array_values(array_intersect($decodedPermissions, User::allAdminPermissions()))
            : User::allAdminPermissions();

        return view('admin.settings.index', [
            'version' => $this->versionService,
            'languages' => $this->getAvailableLanguages(true),
            'adminPermissions' => User::adminPermissionDefinitions(),
            'adminPermissionTree' => [
                'Users' => [
                    'admin.users.create' => 'Create',
                    'admin.users.read' => 'Read',
                    'admin.users.update' => 'Update',
                    'admin.users.delete' => 'Delete',
                ],
                'Servers' => [
                    'admin.servers.create' => 'Create',
                    'admin.servers.read' => 'Read',
                    'admin.servers.update' => 'Update',
                    'admin.servers.delete' => 'Delete',
                ],
                'API Keys' => [
                    'admin.api.create' => 'Create',
                    'admin.api.read' => 'Read',
                    'admin.api.update' => 'Update',
                    'admin.api.delete' => 'Delete',
                ],
                'Locations' => [
                    'admin.locations.create' => 'Create',
                    'admin.locations.read' => 'Read',
                    'admin.locations.update' => 'Update',
                    'admin.locations.delete' => 'Delete',
                ],
                'Nodes' => [
                    'admin.nodes.create' => 'Create',
                    'admin.nodes.read' => 'Read',
                    'admin.nodes.update' => 'Update',
                    'admin.nodes.delete' => 'Delete',
                ],
                'Allocations' => [
                    'admin.allocations.create' => 'Create',
                    'admin.allocations.read' => 'Read',
                    'admin.allocations.update' => 'Update',
                    'admin.allocations.delete' => 'Delete',
                ],
                'Databases' => [
                    'admin.databases.create' => 'Create',
                    'admin.databases.read' => 'Read',
                    'admin.databases.update' => 'Update',
                    'admin.databases.delete' => 'Delete',
                ],
                'Mounts' => [
                    'admin.mounts.create' => 'Create',
                    'admin.mounts.read' => 'Read',
                    'admin.mounts.update' => 'Update',
                    'admin.mounts.delete' => 'Delete',
                ],
                'Nests & Eggs' => [
                    'admin.nests.create' => 'Create',
                    'admin.nests.read' => 'Read',
                    'admin.nests.update' => 'Update',
                    'admin.nests.delete' => 'Delete',
                ],
                'Settings' => [
                    'admin.settings.read' => 'Read',
                    'admin.settings.update' => 'Update',
                ],
                'Overview' => [
                    'admin.overview.read' => 'Read',
                ],
            ],
            'adminAllowedPermissions' => $allowedPermissions,
            'adminFileBlockingEnabled' => filter_var(
                $this->settings->get('settings::admin:file_blocking_enabled', '0'),
                FILTER_VALIDATE_BOOLEAN
            ),
            'adminFileBlockingTerms' => (string) $this->settings->get('settings::admin:file_blocking_terms', ''),
        ]);
    }

    /**
     * Handle settings update.
     *
     * @throws \Pterodactyl\Exceptions\Model\DataValidationException
     * @throws \Pterodactyl\Exceptions\Repository\RecordNotFoundException
     */
    public function update(BaseSettingsFormRequest $request): RedirectResponse
    {
        $normalized = $request->normalize();

        $permissions = $request->input('admin:permissions');
        if (!is_array($permissions)) {
            $permissions = [];
        }
        $permissions = array_values(array_intersect($permissions, User::allAdminPermissions()));
        $this->settings->set('settings::admin:permissions', json_encode($permissions));

        $fileBlockingEnabled = $request->boolean('admin:file_blocking_enabled');
        $this->settings->set('settings::admin:file_blocking_enabled', $fileBlockingEnabled ? '1' : '0');

        $terms = trim((string) $request->input('admin:file_blocking_terms')) ?: null;
        $this->settings->set('settings::admin:file_blocking_terms', $terms);

        foreach ($normalized as $key => $value) {
            if (str_starts_with($key, 'admin:')) {
                continue;
            }

            $this->settings->set('settings::' . $key, $value);
        }

        $this->kernel->call('queue:restart');
        $this->alert->success('Panel settings have been updated successfully and the queue worker was restarted to apply these changes.')->flash();

        return redirect()->route('admin.settings');
    }
}
