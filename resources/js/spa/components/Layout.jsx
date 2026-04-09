export default function Layout({ children }) {
    return (
        <div className="h-screen w-screen flex flex-col bg-gray-900 text-gray-100">
            <nav className="h-12 flex items-center justify-between px-4 bg-gray-800 border-b border-gray-700 shrink-0 z-50">
                <a href="/app/canvas" className="text-lg font-bold text-indigo-400">URGE</a>
                <div className="flex items-center gap-4 text-sm">
                    <a href="/app/canvas" className="text-gray-300 hover:text-white">Canvas</a>
                    <a href="/browse" className="text-gray-400 hover:text-white">Browse</a>
                    <a href="/settings" className="text-gray-400 hover:text-white">Settings</a>
                </div>
            </nav>
            <main className="flex-1 overflow-hidden">{children}</main>
        </div>
    );
}
