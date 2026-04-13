import client from './client.js';

export async function renderPrompt(username, slug, { version, variables }) {
    const { data } = await client.post(`/prompts/${username}/${slug}/render`, { version, variables });
    return data;
}
