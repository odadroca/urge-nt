import client from './client.js';

export async function listBranches(username, slug) {
    const { data } = await client.get(`/prompts/${username}/${slug}/branches`);
    return data;
}

export async function createBranch(username, slug, { name, from_version }) {
    const { data } = await client.post(`/prompts/${username}/${slug}/branches`, { name, from_version });
    return data;
}

export async function deleteBranch(username, slug, branchName) {
    const { data } = await client.delete(`/prompts/${username}/${slug}/branches/${branchName}`);
    return data;
}

export async function setDefaultBranch(username, slug, branchName) {
    const { data } = await client.patch(`/prompts/${username}/${slug}/branches/${branchName}/default`);
    return data;
}
