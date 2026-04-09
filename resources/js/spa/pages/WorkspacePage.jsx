import { useParams, Link } from 'react-router-dom';
export default function WorkspacePage() {
    const { username, slug } = useParams();
    return (
        <div className="h-full flex items-center justify-center text-gray-400">
            <div className="text-center">
                <h2 className="text-xl mb-2">Workspace: {username}/{slug}</h2>
                <p className="mb-4">Coming in Phase 4</p>
                <a href={`/prompts/${username}/${slug}`} className="text-indigo-400 hover:underline">Open in Classic UI →</a>
                <br /><Link to="/canvas" className="text-gray-500 hover:text-gray-300 text-sm mt-2 inline-block">← Back to Canvas</Link>
            </div>
        </div>
    );
}
