import client from './client.js';

export async function updatePrompt(username, slug, data) {
    const { data: response } = await client.patch(`/prompts/${username}/${slug}`, data);
    return response;
}
