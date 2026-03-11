<?php

namespace Pterodactyl\Http\Requests\Api\Client\Servers\Plugins;

use Pterodactyl\Models\Permission;
use Pterodactyl\Contracts\Http\ClientPermissionsRequest;
use Pterodactyl\Http\Requests\Api\Client\ClientApiRequest;

class SearchPluginsRequest extends ClientApiRequest implements ClientPermissionsRequest
{
    public function permission(): string
    {
        return Permission::ACTION_FILE_READ;
    }

    public function rules(): array
    {
        return [
            'query' => 'required|string|min:2|max:80',
            'sources' => 'sometimes',
            'version' => 'nullable|string|max:32',
            'limit' => 'nullable|integer|min:1|max:25',
            'offset' => 'nullable|integer|min:0',
        ];
    }
}
