import client from './client.js';

export async function getNodes(layers) {
    const params = layers ? { layers: layers.join(',') } : {};
    const { data } = await client.get('/graph/nodes', { params });
    return data;
}

export async function getEdges(layers) {
    const params = layers ? { layers: layers.join(',') } : {};
    const { data } = await client.get('/graph/edges', { params });
    return data;
}

export async function savePositions(positions) {
    const { data } = await client.post('/graph/positions', { positions });
    return data;
}

export async function appendInclude(username, slug, fragmentSlug) {
    const { data } = await client.post(`/prompts/${username}/${slug}/append-include`, {
        fragment_slug: fragmentSlug,
    });
    return data;
}

export async function removeInclude(username, slug, fragmentSlug) {
    const { data } = await client.delete(`/prompts/${username}/${slug}/remove-include`, {
        data: { fragment_slug: fragmentSlug },
    });
    return data;
}
