import client from './client.js';

export async function listTeams() {
    const { data } = await client.get('/teams');
    return data;
}

export async function createTeam({ name }) {
    const { data } = await client.post('/teams', { name });
    return data;
}

export async function getTeam(slug) {
    const { data } = await client.get(`/teams/${slug}`);
    return data;
}

export async function updateTeam(slug, updates) {
    const { data } = await client.patch(`/teams/${slug}`, updates);
    return data;
}

export async function deleteTeam(slug) {
    const { data } = await client.delete(`/teams/${slug}`);
    return data;
}

export async function addMember(slug, { email }) {
    const { data } = await client.post(`/teams/${slug}/members`, { email });
    return data;
}

export async function removeMember(slug, userId) {
    const { data } = await client.delete(`/teams/${slug}/members/${userId}`);
    return data;
}

export async function leaveTeam(slug) {
    const { data } = await client.post(`/teams/${slug}/leave`);
    return data;
}
