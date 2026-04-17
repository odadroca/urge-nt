import client from './client.js';

export async function getEvaluationSettings() {
    const { data } = await client.get('/evaluation-settings');
    return data;
}

export async function saveEvaluationSettings(settings) {
    const { data } = await client.patch('/evaluation-settings', settings);
    return data;
}
