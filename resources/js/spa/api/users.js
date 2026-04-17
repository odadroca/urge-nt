import client from './client.js';

export async function listUsers() {
    const { data } = await client.get('/users');
    return data;
}

export async function createUser({ name, email, password, role }) {
    const { data } = await client.post('/users', { name, email, password, role });
    return data;
}

export async function updateUser(id, { role }) {
    const { data } = await client.patch(`/users/${id}`, { role });
    return data;
}

export async function deleteUser(id) {
    const { data } = await client.delete(`/users/${id}`);
    return data;
}
