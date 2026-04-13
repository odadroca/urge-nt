import client from './client.js';

export async function listCollections(params = {}) {
    const { data } = await client.get('/collections', { params });
    return data;
}

export async function getCollection(slug) {
    const { data } = await client.get(`/collections/${slug}`);
    return data;
}
