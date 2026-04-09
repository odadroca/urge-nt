import { useCallback } from 'react';

function sanitize(str) {
    return str.replace(/"/g, '#quot;').replace(/[[\]]/g, '');
}

export default function useMermaidExport(nodes, edges) {
    const getMermaidString = useCallback(() => {
        const lines = ['flowchart LR'];
        nodes.forEach((node) => {
            const name = sanitize(node.data.name || node.data.title || node.id);
            if (node.type === 'prompt') lines.push(`    ${node.id}["${name}"]`);
            else if (node.type === 'fragment') lines.push(`    ${node.id}("${name}")`);
            else if (node.type === 'collection') lines.push(`    ${node.id}{{"${name}"}}`);
        });
        edges.forEach((edge) => {
            const label = edge.data?.label || 'includes';
            lines.push(`    ${edge.source} -->|${label}| ${edge.target}`);
        });
        return lines.join('\n');
    }, [nodes, edges]);

    const copyToClipboard = useCallback(async () => {
        const text = getMermaidString();
        await navigator.clipboard.writeText(text);
        return text;
    }, [getMermaidString]);

    return { getMermaidString, copyToClipboard };
}
