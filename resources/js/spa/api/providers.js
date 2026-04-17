import client from './client.js';

export async function listProviders() {
    const { data } = await client.get('/providers');
    return data;
}
