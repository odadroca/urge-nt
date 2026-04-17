import client from './client.js';

export async function listCategories() {
    const { data } = await client.get('/categories');
    return data;
}

export async function updateCategory(id, data) {
    const { data: response } = await client.patch(`/categories/${id}`, data);
    return response;
}

export async function createCategory({ name, color }) {
    const { data } = await client.post('/categories', { name, color });
    return data;
}

export async function deleteCategory(id) {
    const { data: response } = await client.delete(`/categories/${id}`);
    return response;
}
