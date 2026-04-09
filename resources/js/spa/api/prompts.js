import client from './client';

export async function updatePrompt(username, slug, data) {
    const { data: response } = await client.patch(`/prompts/${username}/${slug}`, data);
    return response;
}
