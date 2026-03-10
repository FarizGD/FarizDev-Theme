<?php

namespace Pterodactyl\Http\Requests\Admin\Settings;

use Illuminate\Validation\Rule;
use Pterodactyl\Traits\Helpers\AvailableLanguages;
use Pterodactyl\Http\Requests\Admin\AdminFormRequest;

class BaseSettingsFormRequest extends AdminFormRequest
{
    use AvailableLanguages;

    public function rules(): array
    {
        return [
            'app:name' => 'required|string|max:191',
            'pterodactyl:auth:2fa_required' => 'required|integer|in:0,1,2',
            'app:locale' => ['required', 'string', Rule::in(array_keys($this->getAvailableLanguages()))],
            'admin:permissions' => 'array',
            'admin:permissions.*' => 'string',
            'admin:file_blocking_enabled' => 'nullable|boolean',
            'admin:file_blocking_terms' => 'nullable|string',
        ];
    }

    public function attributes(): array
    {
        return [
            'app:name' => 'Company Name',
            'pterodactyl:auth:2fa_required' => 'Require 2-Factor Authentication',
            'app:locale' => 'Default Language',
            'admin:permissions' => 'Admin Permissions',
            'admin:file_blocking_enabled' => 'File Blocking Enabled',
            'admin:file_blocking_terms' => 'File Blocking Terms',
        ];
    }
}
