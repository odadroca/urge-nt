import client from './client.js';

export async function listVersions(username, slug, params = {}) {
    const { data } = await client.get(`/prompts/${username}/${slug}/versions`, { params });
    return data;
}

export async function createVersion(username, slug, { content, commit_message, variable_metadata }) {
    const { data } = await client.post(`/prompts/${username}/${slug}/versions`, {
        content, commit_message, variable_metadata,
    });
    return data;
}

export async function getVersion(username, slug, versionNumber) {
    const { data } = await client.get(`/prompts/${username}/${slug}/versions/${versionNumber}`);
    return data;
}
