<?php

namespace Pterodactyl\Http\Controllers\Api\Client\Servers;

use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Http;
use Pterodactyl\Facades\Activity;
use Pterodactyl\Models\Server;
use Pterodactyl\Repositories\Wings\DaemonFileRepository;
use Pterodactyl\Http\Controllers\Api\Client\ClientApiController;
use Pterodactyl\Http\Requests\Api\Client\Servers\Plugins\InstallPluginRequest;
use Pterodactyl\Http\Requests\Api\Client\Servers\Plugins\SearchPluginsRequest;
use Symfony\Component\HttpFoundation\Response;

class PluginController extends ClientApiController
{
    public function __construct(private DaemonFileRepository $fileRepository)
    {
        parent::__construct();
    }

    public function search(SearchPluginsRequest $request, Server $server): JsonResponse
    {
        $query = trim($request->input('query'));
        $version = $request->input('version');
        $limit = (int)($request->input('limit', 12));
        $offset = (int)($request->input('offset', 0));

        $sources = $this->normalizeSources($request->input('sources'));
        $results = [];
        $errors = [];

        foreach ($sources as $source) {
            try {
                $batch = match ($source) {
                    'spigot' => $this->searchSpigot($query, $version, $limit, $offset),
                    'modrinth' => $this->searchModrinth($query, $version, $limit, $offset),
                    'hangar' => $this->searchHangar($query, $version, $limit, $offset),
                    default => [],
                };

                $results = array_merge($results, $batch);
            } catch (\Throwable $exception) {
                $errors[$source] = $exception->getMessage();
            }
        }

        return new JsonResponse([
            'data' => $results,
            'errors' => $errors,
        ]);
    }

    public function install(InstallPluginRequest $request, Server $server): JsonResponse
    {
        $source = $request->input('source');
        $pluginId = $request->input('plugin_id');
        $version = $request->input('version');
        $ignoreCompatibility = (bool)$request->input('ignore_compatibility', false);

        $payload = match ($source) {
            'spigot' => $this->resolveSpigotDownload($pluginId, $version),
            'modrinth' => $this->resolveModrinthDownload($pluginId, $version),
            'hangar' => $this->resolveHangarDownload($pluginId, $version),
            default => null,
        };

        if (!$payload || empty($payload['download_url'])) {
            return new JsonResponse([
                'message' => 'Unable to resolve a download URL for the requested plugin.',
            ], Response::HTTP_BAD_REQUEST);
        }

        if (!$payload['compatible'] && !$ignoreCompatibility) {
            return new JsonResponse([
                'message' => 'This plugin does not appear to support the selected server version.',
            ], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        $this->fileRepository->setServer($server)->pull(
            $payload['download_url'],
            '/plugins',
            [
                'filename' => $payload['filename'],
                'use_header' => true,
                'foreground' => true,
            ]
        );

        Activity::event('server:plugin.install')
            ->property('source', $source)
            ->property('plugin_id', $pluginId)
            ->property('version', $payload['version'])
            ->log();

        return new JsonResponse([
            'object' => 'plugin_install',
            'attributes' => [
                'download_url' => $payload['download_url'],
                'filename' => $payload['filename'],
                'version' => $payload['version'],
                'compatible' => $payload['compatible'],
            ],
        ], Response::HTTP_ACCEPTED);
    }

    private function normalizeSources($sources): array
    {
        $defaults = ['spigot', 'modrinth', 'hangar'];
        if (empty($sources)) {
            return $defaults;
        }

        if (is_string($sources)) {
            $sources = array_filter(array_map('trim', explode(',', $sources)));
        }

        if (!is_array($sources)) {
            return $defaults;
        }

        $allowed = ['spigot', 'modrinth', 'hangar'];
        $filtered = array_values(array_intersect($allowed, $sources));

        return $filtered ?: $defaults;
    }

    private function searchSpigot(string $query, ?string $version, int $limit, int $offset): array
    {
        $page = (int)floor($offset / max($limit, 1));
        $response = Http::timeout(8)->get(
            'https://api.spiget.org/v2/search/resources/' . rawurlencode($query),
            [
                'size' => $limit,
                'page' => $page,
            ]
        );

        $response->throw();
        $items = $response->json() ?? [];

        $results = [];
        foreach ($items as $item) {
            $resourceId = (string)($item['id'] ?? '');
            if ($resourceId === '') {
                continue;
            }

            $details = Http::timeout(8)->get(
                'https://api.spiget.org/v2/resources/' . $resourceId
            );
            $details->throw();
            $resource = $details->json() ?? [];

            $testedVersions = $resource['testedVersions'] ?? [];
            $compatible = $version ? in_array($version, $testedVersions, true) : true;
            $downloadable = empty($resource['premium']) && empty($resource['external']);

            $results[] = [
                'source' => 'spigot',
                'id' => $resourceId,
                'slug' => null,
                'name' => $resource['name'] ?? ($item['name'] ?? 'Unknown'),
                'summary' => $resource['tag'] ?? ($item['tag'] ?? ''),
                'author' => $resource['author']['name'] ?? null,
                'icon' => $resource['icon']['url'] ?? null,
                'downloads' => $resource['downloads'] ?? ($item['downloads'] ?? null),
                'latest_version' => $resource['version'] ?? null,
                'supported_versions' => $testedVersions,
                'compatible' => $compatible,
                'downloadable' => $downloadable,
                'compatibility_reason' => $compatible
                    ? null
                    : 'No tested version for the selected Minecraft version.',
            ];
        }

        return $results;
    }

    private function searchModrinth(string $query, ?string $version, int $limit, int $offset): array
    {
        $facets = [['project_type:plugin']];
        if ($version) {
            $facets[] = ["versions:$version"];
        }

        $response = Http::timeout(8)->withHeaders([
            'User-Agent' => 'FarizDev-Theme',
        ])->get('https://api.modrinth.com/v2/search', [
            'query' => $query,
            'limit' => $limit,
            'offset' => $offset,
            'facets' => json_encode($facets),
        ]);

        $response->throw();
        $payload = $response->json() ?? [];
        $hits = $payload['hits'] ?? [];

        $results = [];
        foreach ($hits as $hit) {
            $projectId = $hit['project_id'] ?? $hit['id'] ?? null;
            if (!$projectId) {
                continue;
            }

            $versions = $this->fetchModrinthVersions($projectId, $version);
            $latest = $versions[0] ?? null;
            $compatible = $latest !== null;

            $results[] = [
                'source' => 'modrinth',
                'id' => (string)$projectId,
                'slug' => $hit['slug'] ?? null,
                'name' => $hit['title'] ?? 'Unknown',
                'summary' => $hit['description'] ?? '',
                'author' => $hit['author'] ?? null,
                'icon' => $hit['icon_url'] ?? null,
                'downloads' => $hit['downloads'] ?? null,
                'latest_version' => $latest['version_number'] ?? null,
                'supported_versions' => $latest['game_versions'] ?? [],
                'compatible' => $compatible,
                'downloadable' => true,
                'compatibility_reason' => $compatible ? null : 'No compatible release found for the selected version.',
            ];
        }

        return $results;
    }

    private function searchHangar(string $query, ?string $version, int $limit, int $offset): array
    {
        $response = Http::timeout(8)
            ->withHeaders(['User-Agent' => 'FarizDev-Theme'])
            ->get('https://hangar.papermc.io/api/v1/projects', [
                'query' => $query,
                'platform' => 'PAPER',
                'version' => $version,
                'limit' => $limit,
                'offset' => $offset,
            ]);

        $response->throw();
        $payload = $response->json() ?? [];
        $items = $payload['result'] ?? [];

        $results = [];
        foreach ($items as $item) {
            $namespace = $item['namespace'] ?? [];
            $owner = $namespace['owner'] ?? null;
            $slug = $namespace['slug'] ?? null;

            if (!$owner || !$slug) {
                continue;
            }

            $versions = $this->fetchHangarVersions($owner, $slug, $version);
            $latest = $versions[0] ?? null;
            $compatible = $latest !== null;

            $results[] = [
                'source' => 'hangar',
                'id' => $owner . '/' . $slug,
                'slug' => $slug,
                'name' => $item['name'] ?? 'Unknown',
                'summary' => $item['description'] ?? '',
                'author' => $owner,
                'icon' => $item['avatarUrl'] ?? null,
                'downloads' => $item['stats']['downloads'] ?? null,
                'latest_version' => $latest['name'] ?? null,
                'supported_versions' => $latest['platformDependenciesFormatted']['PAPER'] ?? [],
                'compatible' => $compatible,
                'downloadable' => true,
                'compatibility_reason' => $compatible ? null : 'No compatible release found for the selected version.',
            ];
        }

        return $results;
    }

    private function resolveSpigotDownload(string $resourceId, ?string $version): array
    {
        $details = Http::timeout(8)->get('https://api.spiget.org/v2/resources/' . $resourceId);
        $details->throw();
        $resource = $details->json() ?? [];

        $testedVersions = $resource['testedVersions'] ?? [];
        $compatible = $version ? in_array($version, $testedVersions, true) : true;
        if (!empty($resource['premium']) || !empty($resource['external'])) {
            return [
                'download_url' => null,
                'filename' => null,
                'version' => $resource['version'] ?? null,
                'compatible' => false,
            ];
        }

        return [
            'download_url' => 'https://api.spiget.org/v2/resources/' . $resourceId . '/download',
            'filename' => $resource['file']['name'] ?? null,
            'version' => $resource['version'] ?? null,
            'compatible' => $compatible,
        ];
    }

    private function resolveModrinthDownload(string $projectId, ?string $version): array
    {
        $versions = $this->fetchModrinthVersions($projectId, $version);
        $latest = $versions[0] ?? null;

        if (!$latest) {
            return [
                'download_url' => null,
                'filename' => null,
                'version' => null,
                'compatible' => false,
            ];
        }

        $file = $latest['files'][0] ?? null;

        return [
            'download_url' => $file['url'] ?? null,
            'filename' => $file['filename'] ?? null,
            'version' => $latest['version_number'] ?? null,
            'compatible' => true,
        ];
    }

    private function resolveHangarDownload(string $slug, ?string $version): array
    {
        [$owner, $projectSlug] = array_pad(explode('/', $slug, 2), 2, null);
        if (!$owner || !$projectSlug) {
            return [
                'download_url' => null,
                'filename' => null,
                'version' => null,
                'compatible' => false,
            ];
        }

        $versions = $this->fetchHangarVersions($owner, $projectSlug, $version);
        $latest = $versions[0] ?? null;
        if (!$latest) {
            return [
                'download_url' => null,
                'filename' => null,
                'version' => null,
                'compatible' => false,
            ];
        }

        $versionId = $latest['id'] ?? null;
        if (!$versionId) {
            return [
                'download_url' => null,
                'filename' => null,
                'version' => $latest['name'] ?? null,
                'compatible' => false,
            ];
        }

        $versionResponse = Http::timeout(8)
            ->withHeaders(['User-Agent' => 'FarizDev-Theme'])
            ->get('https://hangar.papermc.io/api/v1/versions/' . $versionId);
        $versionResponse->throw();
        $versionPayload = $versionResponse->json() ?? [];

        $download = $versionPayload['downloads']['PAPER'] ?? null;
        $downloadUrl = $download['externalUrl'] ?? $download['downloadUrl'] ?? null;

        if ($downloadUrl && str_starts_with($downloadUrl, '/')) {
            $downloadUrl = 'https://hangar.papermc.io' . $downloadUrl;
        }

        return [
            'download_url' => $downloadUrl,
            'filename' => $download['fileInfo']['name'] ?? null,
            'version' => $latest['name'] ?? null,
            'compatible' => true,
        ];
    }

    private function fetchModrinthVersions(string $projectId, ?string $version): array
    {
        $params = [
            'loaders' => json_encode(['paper', 'spigot']),
        ];
        if ($version) {
            $params['game_versions'] = json_encode([$version]);
        }

        $response = Http::timeout(8)->withHeaders([
            'User-Agent' => 'FarizDev-Theme',
        ])->get('https://api.modrinth.com/v2/project/' . rawurlencode($projectId) . '/version', $params);

        $response->throw();

        return $response->json() ?? [];
    }

    private function fetchHangarVersions(string $owner, string $slug, ?string $version): array
    {
        $params = [
            'platform' => 'PAPER',
            'limit' => 1,
        ];

        if ($version) {
            $params['platformVersion'] = $version;
        }

        $response = Http::timeout(8)
            ->withHeaders(['User-Agent' => 'FarizDev-Theme'])
            ->get('https://hangar.papermc.io/api/v1/projects/' . rawurlencode($owner) . '/' . rawurlencode($slug) . '/versions', $params);

        $response->throw();
        $payload = $response->json() ?? [];

        return $payload['result'] ?? [];
    }
}
