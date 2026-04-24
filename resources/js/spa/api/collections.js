import client from './client.js';

export async function listCollections(params = {}) {
    const { data } = await client.get('/collections', { params });
    return data;
}

export async function getCollection(slug) {
    const { data } = await client.get(`/collections/${slug}`);
    return data;
}

export async function createCollection({ title, description }) {
    const { data } = await client.post('/collections', { title, description });
    return data;
}

export async function deleteCollection(slug) {
    const { data } = await client.delete(`/collections/${slug}`);
    return data;
}

export async function addItem(slug, { item_type, item_id }) {
    const { data } = await client.post(`/collections/${slug}/items`, { item_type, item_id });
    return data;
}

export async function removeItem(slug, itemId) {
    const { data } = await client.delete(`/collections/${slug}/items/${itemId}`);
    return data;
}
