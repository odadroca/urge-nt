import Sidebar from './Sidebar.jsx';

export default function Layout({ children }) {
    return (
        <div className="h-screen w-screen flex bg-gray-900 text-gray-100">
            <Sidebar />
            <main className="flex-1 overflow-hidden pb-14 md:pb-0">{children}</main>
        </div>
    );
}
