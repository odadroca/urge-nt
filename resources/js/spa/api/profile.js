import client from './client.js';

export async function updateProfile({ name, email }) {
    const { data } = await client.patch('/profile', { name, email });
    return data;
}

export async function updatePassword({ current_password, password, password_confirmation }) {
    await client.put('/profile/password', {
        current_password,
        password,
        password_confirmation,
    });
}

export async function deleteAccount({ password }) {
    await client.delete('/profile', { data: { password } });
}
