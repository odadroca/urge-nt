import client from './client.js';

export async function listPrompts(params = {}) {
    const { data } = await client.get('/prompts', { params });
    return data;
}

export async function getPrompt(username, slug) {
    const { data } = await client.get(`/prompts/${username}/${slug}`);
    return data;
}

export async function updatePrompt(username, slug, data) {
    const { data: response } = await client.patch(`/prompts/${username}/${slug}`, data);
    return response;
}

export async function createPrompt({ name, type }) {
    const { data } = await client.post('/prompts', { name, type });
    return data;
}

export async function runPrompt(username, slug, { version_number, provider_ids, variables }) {
    const { data } = await client.post(`/prompts/${username}/${slug}/run`, {
        version_number,
        provider_ids,
        variables,
    });
    return data;
}
