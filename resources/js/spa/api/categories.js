import client from './client.js';

export async function listCategories() {
    const { data } = await client.get('/categories');
    return data;
}
