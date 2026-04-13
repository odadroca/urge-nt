import client from './client.js';

export async function listResults(username, slug, params = {}) {
    const { data } = await client.get(`/prompts/${username}/${slug}/results`, { params });
    return data;
}

export async function createResult(username, slug, resultData) {
    const { data } = await client.post(`/prompts/${username}/${slug}/results`, resultData);
    return data;
}

export async function updateResult(resultId, updates) {
    const { data } = await client.patch(`/results/${resultId}`, updates);
    return data;
}

export async function deleteResult(resultId) {
    const { data } = await client.delete(`/results/${resultId}`);
    return data;
}

export async function listStarredResults(params = {}) {
    const { data } = await client.get('/results/starred', { params });
    return data;
}
