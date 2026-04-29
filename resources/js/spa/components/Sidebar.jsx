import { Link, useLocation } from 'react-router-dom';
import useAuth from '../hooks/useAuth.js';

const navItems = [
    {
        key: 'browse',
        label: 'Browse',
        to: '/browse',
        spa: true,
        icon: (
            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" strokeWidth={1.5} stroke="currentColor" className="w-6 h-6">
                <path strokeLinecap="round" strokeLinejoin="round" d="M3.75 6A2.25 2.25 0 0 1 6 3.75h2.25A2.25 2.25 0 0 1 10.5 6v2.25a2.25 2.25 0 0 1-2.25 2.25H6a2.25 2.25 0 0 1-2.25-2.25V6ZM3.75 15.75A2.25 2.25 0 0 1 6 13.5h2.25a2.25 2.25 0 0 1 2.25 2.25V18a2.25 2.25 0 0 1-2.25 2.25H6A2.25 2.25 0 0 1 3.75 18v-2.25ZM13.5 6a2.25 2.25 0 0 1 2.25-2.25H18A2.25 2.25 0 0 1 20.25 6v2.25A2.25 2.25 0 0 1 18 10.5h-2.25a2.25 2.25 0 0 1-2.25-2.25V6ZM13.5 15.75a2.25 2.25 0 0 1 2.25-2.25H18a2.25 2.25 0 0 1 2.25 2.25V18A2.25 2.25 0 0 1 18 20.25h-2.25a2.25 2.25 0 0 1-2.25-2.25v-2.25Z" />
            </svg>
        ),
    },
    {
        key: 'pipelines',
        label: 'Pipelines',
        to: '/pipelines',
        spa: true,
        icon: (
            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" strokeWidth={1.5} stroke="currentColor" className="w-6 h-6">
                <path strokeLinecap="round" strokeLinejoin="round" d="M3.75 6.75h16.5M3.75 12h16.5M12 17.25h8.25" />
            </svg>
        ),
    },
    {
        key: 'teams',
        label: 'Teams',
        to: '/teams',
        spa: true,
        icon: (
            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" strokeWidth={1.5} stroke="currentColor" className="w-6 h-6">
                <path strokeLinecap="round" strokeLinejoin="round" d="M15 19.128a9.38 9.38 0 0 0 2.625.372 9.337 9.337 0 0 0 4.121-.952 4.125 4.125 0 0 0-7.533-2.493M15 19.128v-.003c0-1.113-.285-2.16-.786-3.07M15 19.128v.106A12.318 12.318 0 0 1 8.624 21c-2.331 0-4.512-.645-6.374-1.766l-.001-.109a6.375 6.375 0 0 1 11.964-3.07M12 6.375a3.375 3.375 0 1 1-6.75 0 3.375 3.375 0 0 1 6.75 0Zm8.25 2.25a2.625 2.625 0 1 1-5.25 0 2.625 2.625 0 0 1 5.25 0Z" />
            </svg>
        ),
    },
    {
        key: 'canvas',
        label: 'Canvas',
        to: '/canvas',
        spa: true,
        icon: (
            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" strokeWidth={1.5} stroke="currentColor" className="w-6 h-6">
                <path strokeLinecap="round" strokeLinejoin="round" d="M7.5 21 3 16.5m0 0L7.5 12M3 16.5h13.5m0-13.5L21 7.5m0 0L16.5 12M21 7.5H7.5" />
            </svg>
        ),
    },
];

const bottomItems = [
    {
        key: 'settings',
        label: 'Settings',
        to: '/settings',
        spa: true,
        icon: (
            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" strokeWidth={1.5} stroke="currentColor" className="w-6 h-6">
                <path strokeLinecap="round" strokeLinejoin="round" d="M9.594 3.94c.09-.542.56-.94 1.11-.94h2.593c.55 0 1.02.398 1.11.94l.213 1.281c.063.374.313.686.645.87.074.04.147.083.22.127.325.196.72.257 1.075.124l1.217-.456a1.125 1.125 0 0 1 1.37.49l1.296 2.247a1.125 1.125 0 0 1-.26 1.431l-1.003.827c-.293.241-.438.613-.43.992a7.723 7.723 0 0 1 0 .255c-.008.378.137.75.43.991l1.004.827c.424.35.534.955.26 1.43l-1.298 2.247a1.125 1.125 0 0 1-1.369.491l-1.217-.456c-.355-.133-.75-.072-1.076.124a6.47 6.47 0 0 1-.22.128c-.331.183-.581.495-.644.869l-.213 1.281c-.09.543-.56.94-1.11.94h-2.594c-.55 0-1.019-.398-1.11-.94l-.213-1.281c-.062-.374-.312-.686-.644-.87a6.52 6.52 0 0 1-.22-.127c-.325-.196-.72-.257-1.076-.124l-1.217.456a1.125 1.125 0 0 1-1.369-.49l-1.297-2.247a1.125 1.125 0 0 1 .26-1.431l1.004-.827c.292-.24.437-.613.43-.991a6.932 6.932 0 0 1 0-.255c.007-.38-.138-.751-.43-.992l-1.004-.827a1.125 1.125 0 0 1-.26-1.43l1.297-2.247a1.125 1.125 0 0 1 1.37-.491l1.216.456c.356.133.751.072 1.076-.124.072-.044.146-.086.22-.128.332-.183.582-.495.644-.869l.214-1.28Z" />
                <path strokeLinecap="round" strokeLinejoin="round" d="M15 12a3 3 0 1 1-6 0 3 3 0 0 1 6 0Z" />
            </svg>
        ),
    },
    {
        key: 'docs',
        label: 'API Docs',
        to: '/docs',
        spa: false,
        icon: (
            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" strokeWidth={1.5} stroke="currentColor" className="w-6 h-6">
                <path strokeLinecap="round" strokeLinejoin="round" d="M12 6.042A8.967 8.967 0 0 0 6 3.75c-1.052 0-2.062.18-3 .512v14.25A8.987 8.987 0 0 1 6 18c2.305 0 4.408.867 6 2.292m0-14.25a8.966 8.966 0 0 1 6-2.292c1.052 0 2.062.18 3 .512v14.25A8.987 8.987 0 0 0 18 18a8.967 8.967 0 0 0-6 2.292m0-14.25v14.25" />
            </svg>
        ),
    },
];

function NavLink({ item, isActive }) {
    const activeClass = 'bg-indigo-600 text-white';
    const inactiveClass = 'text-gray-500 hover:text-gray-300 hover:bg-gray-800';
    const classes = `flex flex-col items-center justify-center w-10 h-10 rounded-lg transition-colors ${isActive ? activeClass : inactiveClass}`;

    if (item.spa) {
        return (
            <Link to={item.to} className={classes} title={item.label}>
                {item.icon}
            </Link>
        );
    }
    return (
        <a href={item.to} className={classes} title={item.label}>
            {item.icon}
        </a>
    );
}

function MobileNavLink({ item, isActive }) {
    const activeClass = 'text-white';
    const inactiveClass = 'text-gray-500';
    const classes = `flex flex-col items-center justify-center gap-0.5 flex-1 py-1 ${isActive ? activeClass : inactiveClass}`;

    if (item.spa) {
        return (
            <Link to={item.to} className={classes}>
                {item.icon}
                <span className="text-[10px]">{item.label}</span>
            </Link>
        );
    }
    return (
        <a href={item.to} className={classes}>
            {item.icon}
            <span className="text-[10px]">{item.label}</span>
        </a>
    );
}

export default function Sidebar() {
    const location = useLocation();
    const { user, logout } = useAuth();

    const isActive = (path) => location.pathname === path || location.pathname.startsWith(path + '/');

    const userInitial = user?.name?.charAt(0)?.toUpperCase() || '?';

    return (
        <>
            {/* Desktop sidebar */}
            <aside className="hidden md:flex flex-col items-center w-14 bg-gray-800 border-r border-gray-700 py-3 gap-2 shrink-0">
                {/* Brand mark */}
                <Link
                    to="/browse"
                    className="flex items-center justify-center w-8 h-8 rounded-md bg-indigo-600 text-white font-bold text-sm mb-2"
                >
                    U
                </Link>

                {/* Main nav */}
                {navItems.map((item) => (
                    <NavLink key={item.key} item={item} isActive={isActive(item.to)} />
                ))}

                {/* Spacer */}
                <div className="flex-1" />

                {/* Bottom nav */}
                {bottomItems.map((item) => (
                    <NavLink key={item.key} item={item} isActive={false} />
                ))}

                {/* User avatar + logout */}
                {user && (
                    <div className="flex flex-col items-center gap-1 mt-2 pt-2 border-t border-gray-700">
                        <div
                            className="flex items-center justify-center w-8 h-8 rounded-full bg-gray-600 text-white text-xs font-semibold"
                            title={user.name}
                        >
                            {userInitial}
                        </div>
                        <button
                            onClick={logout}
                            className="flex items-center justify-center w-10 h-10 rounded-lg text-gray-500 hover:text-red-400 hover:bg-gray-800 transition-colors"
                            title="Logout"
                        >
                            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" strokeWidth={1.5} stroke="currentColor" className="w-5 h-5">
                                <path strokeLinecap="round" strokeLinejoin="round" d="M15.75 9V5.25A2.25 2.25 0 0 0 13.5 3h-6a2.25 2.25 0 0 0-2.25 2.25v13.5A2.25 2.25 0 0 0 7.5 21h6a2.25 2.25 0 0 0 2.25-2.25V15m3 0 3-3m0 0-3-3m3 3H9" />
                            </svg>
                        </button>
                    </div>
                )}
            </aside>

            {/* Mobile bottom tab bar */}
            <nav className="md:hidden fixed bottom-0 left-0 right-0 h-14 bg-gray-800 border-t border-gray-700 flex items-center z-50">
                {navItems.map((item) => (
                    <MobileNavLink key={item.key} item={item} isActive={isActive(item.to)} />
                ))}
                {bottomItems.map((item) => (
                    <MobileNavLink key={item.key} item={item} isActive={false} />
                ))}
                {user && (
                    <button
                        onClick={logout}
                        className="flex flex-col items-center justify-center gap-0.5 flex-1 py-1 text-gray-500"
                    >
                        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" strokeWidth={1.5} stroke="currentColor" className="w-6 h-6">
                            <path strokeLinecap="round" strokeLinejoin="round" d="M15.75 9V5.25A2.25 2.25 0 0 0 13.5 3h-6a2.25 2.25 0 0 0-2.25 2.25v13.5A2.25 2.25 0 0 0 7.5 21h6a2.25 2.25 0 0 0 2.25-2.25V15m3 0 3-3m0 0-3-3m3 3H9" />
                        </svg>
                        <span className="text-[10px]">Logout</span>
                    </button>
                )}
            </nav>
        </>
    );
}
