<?php

namespace Pterodactyl\Http\Requests\Api\Client\Servers\Plugins;

use Pterodactyl\Models\Permission;
use Pterodactyl\Contracts\Http\ClientPermissionsRequest;
use Pterodactyl\Http\Requests\Api\Client\ClientApiRequest;

class InstallPluginRequest extends ClientApiRequest implements ClientPermissionsRequest
{
    public function permission(): string
    {
        return Permission::ACTION_FILE_CREATE;
    }

    public function rules(): array
    {
        return [
            'source' => 'required|string|in:spigot,modrinth,hangar',
            'plugin_id' => 'required|string|max:128',
            'version' => 'nullable|string|max:64',
            'ignore_compatibility' => 'sometimes|boolean',
        ];
    }
}
