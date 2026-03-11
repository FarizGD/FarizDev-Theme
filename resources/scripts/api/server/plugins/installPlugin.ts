import http from '@/api/http';
import { PluginSource } from '@/api/server/plugins/types';

interface Params {
    uuid: string;
    source: PluginSource;
    pluginId: string;
    version?: string | null;
    ignoreCompatibility?: boolean;
}

export default async ({ uuid, source, pluginId, version, ignoreCompatibility }: Params) => {
    const payload: Record<string, unknown> = {
        source,
        plugin_id: pluginId,
    };

    if (version) {
        payload.version = version;
    }

    if (ignoreCompatibility) {
        payload.ignore_compatibility = true;
    }

    await http.post(`/api/client/servers/${uuid}/plugins/install`, payload);
};
