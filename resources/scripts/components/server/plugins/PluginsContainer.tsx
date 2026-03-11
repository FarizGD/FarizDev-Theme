import React, { useMemo, useState } from 'react';
import tw from 'twin.macro';
import Button from '@/components/elements/Button';
import Input from '@/components/elements/Input';
import Select from '@/components/elements/Select';
import Checkbox from '@/components/elements/inputs/Checkbox';
import Spinner from '@/components/elements/Spinner';
import useFlash from '@/plugins/useFlash';
import FlashMessageRender from '@/components/FlashMessageRender';
import ServerContentBlock from '@/components/elements/ServerContentBlock';
import { ServerContext } from '@/state/server';
import searchPlugins from '@/api/server/plugins/searchPlugins';
import installPlugin from '@/api/server/plugins/installPlugin';
import { PluginSearchResult, PluginSource } from '@/api/server/plugins/types';

const versionOptions = [
    '1.21',
    '1.20.6',
    '1.20.5',
    '1.20.4',
    '1.20.2',
    '1.20.1',
    '1.19.4',
    '1.19.3',
    '1.19.2',
    '1.18.2',
    '1.17.1',
    '1.16.5',
    '1.12.2',
    '1.8.8',
];

const detectServerVersion = (
    variables: { name: string; envVariable: string; serverValue: string | null; defaultValue: string }[]
) => {
    const match = variables.find((variable) => {
        const key = (variable.envVariable || variable.name || '').toUpperCase();
        if (key === 'MC_VERSION' || key === 'MINECRAFT_VERSION') {
            return true;
        }

        if (key.includes('MINECRAFT') || key.includes('MC_')) {
            return key.includes('VERSION');
        }

        return false;
    });

    return match?.serverValue || match?.defaultValue || null;
};

const sourceLabels: Record<PluginSource, string> = {
    spigot: 'SpigotMC',
    modrinth: 'Modrinth',
    hangar: 'Hangar',
};

const PluginsContainer = () => {
    const uuid = ServerContext.useStoreState((state) => state.server.data!.identifier);
    const variables = ServerContext.useStoreState((state) => state.server.data?.variables || []);
    const detectedVersion = useMemo(() => detectServerVersion(variables as any), [variables]);

    const { clearFlashes, clearAndAddHttpError, addFlash } = useFlash();
    const [query, setQuery] = useState('');
    const [version, setVersion] = useState(detectedVersion || '1.20.4');
    const [useCurrentVersion, setUseCurrentVersion] = useState(!!detectedVersion);
    const [sources, setSources] = useState<PluginSource[]>(['spigot', 'modrinth', 'hangar']);
    const [results, setResults] = useState<PluginSearchResult[]>([]);
    const [page, setPage] = useState(1);
    const [hasMore, setHasMore] = useState(false);
    const [errors, setErrors] = useState<Record<string, string>>({});
    const [loading, setLoading] = useState(false);
    const [installing, setInstalling] = useState<string | null>(null);

    const effectiveVersion = useMemo(() => {
        if (useCurrentVersion && detectedVersion) {
            return detectedVersion;
        }

        return version;
    }, [useCurrentVersion, detectedVersion, version]);

    const filteredResults = useMemo(() => {
        if (!useCurrentVersion) {
            return results;
        }

        return results.filter((item) => item.compatible);
    }, [results, useCurrentVersion]);

    const toggleSource = (source: PluginSource) => {
        setSources((current) => {
            if (current.includes(source)) {
                return current.filter((item) => item !== source);
            }

            return [...current, source];
        });
    };

    const onSearch = async (pageOverride?: number) => {
        clearFlashes('plugins');
        setErrors({});

        if (!query.trim()) {
            addFlash({ key: 'plugins', type: 'error', message: 'Please enter a plugin name or keyword.' });
            return;
        }

        if (sources.length === 0) {
            addFlash({ key: 'plugins', type: 'error', message: 'Select at least one source to search.' });
            return;
        }

        if (useCurrentVersion && !detectedVersion) {
            addFlash({
                key: 'plugins',
                type: 'warning',
                message: 'Unable to detect the current server version. Using the selected version instead.',
            });
        }

        const targetPage = pageOverride ?? 1;
        setLoading(true);
        setPage(targetPage);
        try {
            const response = await searchPlugins({
                uuid,
                query,
                sources,
                version: effectiveVersion,
                limit: 12,
                offset: (targetPage - 1) * 12,
            });

            setResults(response.data || []);
            setErrors(response.errors || {});
            setHasMore((response.data || []).length >= 12);
        } catch (error) {
            clearAndAddHttpError({ error, key: 'plugins' });
        } finally {
            setLoading(false);
        }
    };

    const onInstall = async (plugin: PluginSearchResult, ignoreCompatibility = false) => {
        setInstalling(plugin.source + ':' + plugin.id);
        clearFlashes('plugins');
        try {
            await installPlugin({
                uuid,
                source: plugin.source,
                pluginId: plugin.id,
                version: effectiveVersion,
                ignoreCompatibility,
            });

            addFlash({
                key: 'plugins',
                type: 'success',
                message: `Queued ${plugin.name} for download. Check the /plugins directory in Files.`,
            });
        } catch (error) {
            clearAndAddHttpError({ error, key: 'plugins' });
        } finally {
            setInstalling(null);
        }
    };

    return (
        <ServerContentBlock title={'Plugins'}>
            <FlashMessageRender byKey={'plugins'} css={tw`mb-4`} />
            <div css={tw`bg-neutral-800/60 border border-neutral-700 rounded-xl p-5 mb-6`}>
                <div css={tw`grid gap-4 lg:grid-cols-3`}>
                    <div css={tw`lg:col-span-2`}>
                        <label css={tw`text-xs uppercase text-neutral-400 tracking-wide`}>Search plugins</label>
                        <Input
                            value={query}
                            onChange={(event) => setQuery(event.target.value)}
                            placeholder={'LuckPerms, Essentials, Geyser...'}
                            css={tw`mt-2 w-full`}
                        />
                    </div>
                    <div>
                        <label css={tw`text-xs uppercase text-neutral-400 tracking-wide`}>Minecraft version</label>
                        <Select
                            value={effectiveVersion}
                            onChange={(event) => setVersion(event.target.value)}
                            disabled={useCurrentVersion && !!detectedVersion}
                            css={tw`mt-2 w-full`}
                        >
                            {versionOptions.map((item) => (
                                <option key={item} value={item}>
                                    {item}
                                </option>
                            ))}
                        </Select>
                        {detectedVersion && (
                            <p css={tw`text-xs text-neutral-400 mt-2`}>Detected server version: {detectedVersion}</p>
                        )}
                    </div>
                </div>
                <div css={tw`mt-4 flex flex-col lg:flex-row lg:items-center lg:justify-between gap-4`}>
                    <div css={tw`flex flex-col gap-3`}>
                        <label css={tw`flex items-center gap-2 text-sm text-neutral-300`}>
                            <Checkbox
                                name={'currentVersion'}
                                checked={useCurrentVersion}
                                onChange={() => setUseCurrentVersion((value) => !value)}
                            />
                            Filter plugins for current server version only
                        </label>
                        <div css={tw`flex flex-wrap gap-2`}>
                            {(Object.keys(sourceLabels) as PluginSource[]).map((source) => {
                                const active = sources.includes(source);
                                return (
                                    <button
                                        key={source}
                                        type={'button'}
                                        onClick={() => toggleSource(source)}
                                        css={[
                                            tw`px-3 py-1 rounded-full text-xs font-semibold border transition`,
                                            active
                                                ? tw`bg-primary-500/20 border-primary-500 text-primary-200`
                                                : tw`bg-neutral-800 border-neutral-700 text-neutral-300`,
                                        ]}
                                    >
                                        {sourceLabels[source]}
                                    </button>
                                );
                            })}
                        </div>
                    </div>
                    <Button isLoading={loading} onClick={onSearch} disabled={loading}>
                        Search
                    </Button>
                </div>
            </div>

            {Object.keys(errors).length > 0 && (
                <div css={tw`mb-4 text-sm text-yellow-200 bg-yellow-500/10 border border-yellow-500/30 rounded p-3`}>
                    Some sources returned errors:{' '}
                    {Object.entries(errors)
                        .map(([key, value]) => `${key}: ${value}`)
                        .join(' | ')}
                </div>
            )}

            {loading ? (
                <Spinner size={'large'} centered />
            ) : filteredResults.length === 0 ? (
                <p css={tw`text-center text-sm text-neutral-300`}>
                    No plugins found yet. Try searching for a different keyword.
                </p>
            ) : (
                <>
                    <div css={tw`grid gap-4 lg:grid-cols-2`}>
                        {filteredResults.map((plugin) => {
                            const key = `${plugin.source}:${plugin.id}`;
                            const isInstalling = installing === key;
                            const isCompatible = plugin.compatible;
                            const canInstall = plugin.downloadable !== false;

                            return (
                                <div key={key} css={tw`bg-neutral-800/60 border border-neutral-700 rounded-xl p-4`}>
                                    <div css={tw`flex items-start gap-4`}>
                                        {plugin.icon ? (
                                            <img
                                                src={plugin.icon}
                                                alt={plugin.name}
                                                css={tw`w-12 h-12 rounded-lg object-cover`}
                                            />
                                        ) : (
                                            <div css={tw`w-12 h-12 rounded-lg bg-neutral-700`} />
                                        )}
                                        <div css={tw`flex-1`}>
                                            <div css={tw`flex items-center justify-between gap-3`}>
                                                <div>
                                                    <h3 css={tw`text-lg text-neutral-100`}>{plugin.name}</h3>
                                                    <p css={tw`text-xs text-neutral-400`}>
                                                        {sourceLabels[plugin.source]}{' '}
                                                        {plugin.author ? `• ${plugin.author}` : ''}
                                                    </p>
                                                </div>
                                                <div css={tw`text-xs text-neutral-400`}>
                                                    {plugin.downloads ? `${plugin.downloads.toLocaleString()} downloads` : ''}
                                                </div>
                                            </div>
                                            <p css={tw`text-sm text-neutral-300 mt-2`}>{plugin.summary}</p>
                                            <div css={tw`text-xs text-neutral-400 mt-2`}>
                                                Latest: {plugin.latest_version || 'Unknown'}{' '}
                                                {plugin.supported_versions?.length
                                                    ? `• Supports ${plugin.supported_versions.slice(0, 3).join(', ')}`
                                                    : ''}
                                            </div>
                                        </div>
                                    </div>
                                    {!canInstall && (
                                        <div css={tw`text-xs text-red-200 bg-red-500/10 border border-red-500/30 rounded p-2 mt-3`}>
                                            This plugin cannot be downloaded directly from the source (premium or external).
                                        </div>
                                    )}
                                    {!isCompatible && (
                                        <div css={tw`text-xs text-yellow-200 bg-yellow-500/10 border border-yellow-500/30 rounded p-2 mt-3`}>
                                            {plugin.compatibility_reason ||
                                                'This plugin may not support the selected version.'}
                                        </div>
                                    )}
                                    <div css={tw`mt-4 flex flex-wrap gap-3 justify-end`}>
                                        <Button
                                            size={'small'}
                                            color={'primary'}
                                            onClick={() => onInstall(plugin)}
                                            disabled={!canInstall || isInstalling || !isCompatible}
                                            isLoading={isInstalling}
                                        >
                                            Install
                                        </Button>
                                        {!isCompatible && (
                                            <Button
                                                size={'small'}
                                                color={'red'}
                                                isSecondary
                                                onClick={() => onInstall(plugin, true)}
                                                disabled={!canInstall || isInstalling}
                                                isLoading={isInstalling}
                                            >
                                                Install anyway
                                            </Button>
                                        )}
                                    </div>
                                </div>
                            );
                        })}
                    </div>
                    <div css={tw`mt-6 flex items-center justify-between`}>
                        <Button
                            size={'small'}
                            isSecondary
                            disabled={loading || page <= 1}
                            onClick={() => onSearch(page - 1)}
                        >
                            Previous
                        </Button>
                        <div css={tw`text-xs text-neutral-400`}>Page {page}</div>
                        <Button
                            size={'small'}
                            isSecondary
                            disabled={loading || !hasMore}
                            onClick={() => onSearch(page + 1)}
                        >
                            Next
                        </Button>
                    </div>
                </>
            )}
        </ServerContentBlock>
    );
};

export default PluginsContainer;
