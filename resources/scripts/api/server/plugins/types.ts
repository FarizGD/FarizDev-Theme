export type PluginSource = 'spigot' | 'modrinth' | 'hangar';

export interface PluginSearchResult {
    source: PluginSource;
    id: string;
    slug?: string | null;
    name: string;
    summary: string;
    author?: string | null;
    icon?: string | null;
    downloads?: number | null;
    latest_version?: string | null;
    supported_versions?: string[];
    compatible: boolean;
    downloadable?: boolean;
    compatibility_reason?: string | null;
}

export interface PluginSearchResponse {
    data: PluginSearchResult[];
    errors?: Record<string, string>;
}
