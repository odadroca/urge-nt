import client from './client.js';

export async function listProviders() {
    const { data } = await client.get('/providers');
    return data;
}

export async function createProvider(data) {
    const { data: response } = await client.post('/providers', data);
    return response;
}

export async function updateProvider(id, data) {
    const { data: response } = await client.patch(`/providers/${id}`, data);
    return response;
}

export async function deleteProvider(id) {
    const { data: response } = await client.delete(`/providers/${id}`);
    return response;
}

export async function testProvider(id) {
    const { data: response } = await client.post(`/providers/${id}/test`);
    return response;
}
