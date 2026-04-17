import client from './client.js';

export async function listPipelines() {
    const { data } = await client.get('/pipelines');
    return data;
}

export async function createPipeline({ name, description }) {
    const { data } = await client.post('/pipelines', { name, description });
    return data;
}

export async function getPipeline(slug) {
    const { data } = await client.get(`/pipelines/${slug}`);
    return data;
}

export async function updatePipeline(slug, updates) {
    const { data } = await client.patch(`/pipelines/${slug}`, updates);
    return data;
}

export async function deletePipeline(slug) {
    const { data } = await client.delete(`/pipelines/${slug}`);
    return data;
}

export async function addChannel(pipelineSlug, channelData) {
    const { data } = await client.post(`/pipelines/${pipelineSlug}/channels`, channelData);
    return data;
}

export async function updateChannel(pipelineSlug, channelId, updates) {
    const { data } = await client.patch(`/pipelines/${pipelineSlug}/channels/${channelId}`, updates);
    return data;
}

export async function removeChannel(pipelineSlug, channelId) {
    const { data } = await client.delete(`/pipelines/${pipelineSlug}/channels/${channelId}`);
    return data;
}
