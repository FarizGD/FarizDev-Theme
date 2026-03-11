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
    const params: Record<string, unknown> = {
        query,
        sources,
        limit,
        offset,
    };

    if (version) {
        params.version = version;
    }

    const { data } = await http.get<PluginSearchResponse>(`/api/client/servers/${uuid}/plugins/search`, {
        params,
    });

    return data;
};
