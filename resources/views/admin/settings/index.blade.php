@extends('layouts.admin')
@include('partials/admin.settings.nav', ['activeTab' => 'basic'])

@section('title')
    Settings
@endsection

@section('content-header')
    <h1>Panel Settings<small>Configure Pterodactyl to your liking.</small></h1>
    <ol class="breadcrumb">
        <li><a href="{{ route('admin.index') }}">Admin</a></li>
        <li class="active">Settings</li>
    </ol>
@endsection

@section('content')
    @yield('settings::nav')
    <form action="{{ route('admin.settings') }}" method="POST">
        <div class="row">
            <div class="col-xs-12">
                <div class="box">
                    <div class="box-header with-border">
                        <h3 class="box-title">Panel Settings</h3>
                    </div>
                    <div class="box-body">
                        <div class="row">
                            <div class="form-group col-md-4">
                                <label class="control-label">Company Name</label>
                                <div>
                                    <input type="text" class="form-control" name="app:name" value="{{ old('app:name', config('app.name')) }}" />
                                    <p class="text-muted"><small>This is the name that is used throughout the panel and in emails sent to clients.</small></p>
                                </div>
                            </div>
                            <div class="form-group col-md-4">
                                <label class="control-label">Require 2-Factor Authentication</label>
                                <div>
                                    <div class="btn-group" data-toggle="buttons">
                                        @php
                                            $level = old('pterodactyl:auth:2fa_required', config('pterodactyl.auth.2fa_required'));
                                        @endphp
                                        <label class="btn btn-primary @if ($level == 0) active @endif">
                                            <input type="radio" name="pterodactyl:auth:2fa_required" autocomplete="off" value="0" @if ($level == 0) checked @endif> Not Required
                                        </label>
                                        <label class="btn btn-primary @if ($level == 1) active @endif">
                                            <input type="radio" name="pterodactyl:auth:2fa_required" autocomplete="off" value="1" @if ($level == 1) checked @endif> Admin Only
                                        </label>
                                        <label class="btn btn-primary @if ($level == 2) active @endif">
                                            <input type="radio" name="pterodactyl:auth:2fa_required" autocomplete="off" value="2" @if ($level == 2) checked @endif> All Users
                                        </label>
                                    </div>
                                    <p class="text-muted"><small>If enabled, any account falling into the selected grouping will be required to have 2-Factor authentication enabled to use the Panel.</small></p>
                                </div>
                            </div>
                            <div class="form-group col-md-4">
                                <label class="control-label">Default Language</label>
                                <div>
                                    <select name="app:locale" class="form-control">
                                        @foreach($languages as $key => $value)
                                            <option value="{{ $key }}" @if(config('app.locale') === $key) selected @endif>{{ $value }}</option>
                                        @endforeach
                                    </select>
                                    <p class="text-muted"><small>The default language to use when rendering UI components.</small></p>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="box-footer">
                        {!! csrf_field() !!}
                        <button type="submit" name="_method" value="PATCH" class="btn btn-sm btn-primary pull-right">Save</button>
                    </div>
                </div>
            </div>
        </div>
        <div class="row">
            <div class="col-xs-12">
                <div class="box">
                    <div class="box-header with-border">
                        <h3 class="box-title">Admin Permission Manager</h3>
                    </div>
                    <div class="box-body">
                        <div class="ptero-permission-panel ptero-scroll-panel">
                            <p class="text-muted small">Permissions are global for all admins. Admin ID 1 is never restricted.</p>
                            <div class="ptero-permission-tree">
                                @foreach($adminPermissionTree as $group => $items)
                                    <div class="ptero-permission-group">
                                        <div class="ptero-permission-group-title">{{ $group }}</div>
                                        <div class="ptero-permission-row">
                                            @foreach($items as $key => $label)
                                                <label class="ptero-switch compact">
                                                    <input
                                                        type="checkbox"
                                                        name="admin:permissions[]"
                                                        value="{{ $key }}"
                                                        {{ in_array($key, $adminAllowedPermissions, true) ? 'checked' : '' }}
                                                    />
                                                    <span class="ptero-switch-slider"></span>
                                                    <span class="ptero-switch-label">{{ $label }}</span>
                                                </label>
                                            @endforeach
                                        </div>
                                    </div>
                                @endforeach
                            </div>
                            <div class="ptero-file-blocking">
                                <div class="ptero-file-blocking-header">
                                    <label class="ptero-switch inline">
                                        <input
                                            type="checkbox"
                                            name="admin:file_blocking_enabled"
                                            value="1"
                                            {{ $adminFileBlockingEnabled ? 'checked' : '' }}
                                        />
                                        <span class="ptero-switch-slider"></span>
                                        <span class="ptero-switch-label">File Blocking</span>
                                    </label>
                                </div>
                                <p class="text-muted small">Blocks admin access to files that contain any of the words below (comma separated). Applies to File Manager and SFTP.</p>
                                <textarea
                                    name="admin:file_blocking_terms"
                                    class="form-control"
                                    rows="3"
                                    placeholder="apikey, secret, .env"
                                >{{ $adminFileBlockingTerms }}</textarea>
                            </div>
                        </div>
                    </div>
                    <div class="box-footer">
                        <button type="submit" name="_method" value="PATCH" class="btn btn-sm btn-primary pull-right">Save Admin Permissions</button>
                    </div>
                </div>
            </div>
        </div>
    </form>
@endsection
