import PipelinesTab from '../components/settings/PipelinesTab.jsx';

export default function PipelinesPage() {
    return (
        <div className="h-full flex flex-col overflow-hidden">
            <div className="flex-1 overflow-y-auto p-6">
                <PipelinesTab />
            </div>
        </div>
    );
}
