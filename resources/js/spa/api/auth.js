import client from './client.js';

export async function login(email, password) {
    await client.get('/sanctum/csrf-cookie', { baseURL: '/' });
    const { data } = await client.post('/auth/login', { email, password });
    return data;
}

export async function logout() {
    const { data } = await client.post('/auth/logout');
    return data;
}

export async function getUser() {
    const { data } = await client.get('/auth/user');
    return data;
}
