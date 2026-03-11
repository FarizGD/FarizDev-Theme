import http from '@/api/http';
import { PluginSearchResponse, PluginSource } from '@/api/server/plugins/types';

interface Params {
    uuid: string;
    query: string;
    sources: PluginSource[];
    version?: string | null;
    limit?: number;
    offset?: number;
}

export default async ({ uuid, query, sources, version, limit = 12, offset = 0 }: Params) => {
    const safeLimit = Number.isFinite(limit) ? Math.max(1, Math.floor(limit)) : 12;
    const safeOffset = Number.isFinite(offset) ? Math.max(0, Math.floor(offset)) : 0;
    const params: Record<string, unknown> = {
        query,
        sources,
        limit: safeLimit,
        offset: safeOffset,
    };

    if (version) {
        params.version = version;
    }

    const { data } = await http.get<PluginSearchResponse>(`/api/client/servers/${uuid}/plugins/search`, {
        params,
    });

    return data;
};
