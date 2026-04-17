import client from './client.js';

export async function listApiKeys() {
    const { data } = await client.get('/api-keys');
    return data;
}

export async function createApiKey({ name, prompt_ids }) {
    const { data } = await client.post('/api-keys', { name, prompt_ids });
    return data;
}

export async function updateApiKey(id, { is_active }) {
    const { data } = await client.patch(`/api-keys/${id}`, { is_active });
    return data;
}

export async function deleteApiKey(id) {
    const { data } = await client.delete(`/api-keys/${id}`);
    return data;
}
