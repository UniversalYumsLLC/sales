import { Link, usePage } from '@inertiajs/react';
import { RefreshCw } from 'lucide-react';
import { PropsWithChildren, useState } from 'react';

interface AppLayoutProps extends PropsWithChildren {
    lastUpdated?: string;
}

export default function AppLayout({ children, lastUpdated }: AppLayoutProps) {
    const { url } = usePage();
    const [isRefreshing, setIsRefreshing] = useState(false);

    const tabs = [
        { name: 'Active Customers', href: '/', pattern: /^\/$|^\/customers/ },
        { name: 'Accounts Receivable', href: '/accounts-receivable', pattern: /^\/accounts-receivable/ },
    ];

    const handleRefresh = () => {
        setIsRefreshing(true);
        const separator = url.includes('?') ? '&' : '?';
        window.location.href = url + separator + 'refresh=1';
    };

    const formatLastUpdated = (dateStr?: string) => {
        if (!dateStr) return null;
        const date = new Date(dateStr);
        return date.toLocaleTimeString('en-US', {
            hour: 'numeric',
            minute: '2-digit',
            hour12: true,
        });
    };

    return (
        <div className="bg-gray-50 min-h-screen">
            {/* Header */}
            <header className="bg-white border-gray-200 border-b">
                <div className="max-w-7xl px-4 sm:px-6 lg:px-8 mx-auto">
                    <div className="h-16 flex items-center justify-between">
                        <div className="space-x-8 flex items-center">
                            <h1 className="text-xl font-semibold text-gray-900">Sales Tools</h1>
                            <nav className="space-x-1 flex">
                                {tabs.map((tab) => {
                                    const isActive = tab.pattern.test(url);
                                    return (
                                        <Link
                                            key={tab.name}
                                            href={tab.href}
                                            className={`px-4 py-2 text-sm font-medium rounded-md transition-colors ${
                                                isActive ? 'bg-gray-100 text-gray-900' : 'text-gray-600 hover:text-gray-900 hover:bg-gray-50'
                                            }`}
                                        >
                                            {tab.name}
                                        </Link>
                                    );
                                })}
                            </nav>
                        </div>
                        <div className="space-x-4 flex items-center">
                            {lastUpdated && <span className="text-sm text-gray-500">Data as of {formatLastUpdated(lastUpdated)}</span>}
                            <button
                                onClick={handleRefresh}
                                disabled={isRefreshing}
                                className="px-3 py-1.5 text-sm font-medium text-gray-700 bg-white border-gray-300 rounded-md hover:bg-gray-50 focus:ring-indigo-500 inline-flex items-center border focus:ring-2 focus:ring-offset-2 focus:outline-none disabled:opacity-50"
                            >
                                <RefreshCw className={`w-4 h-4 mr-1.5 ${isRefreshing ? 'animate-spin' : ''}`} />
                                Refresh Data
                            </button>
                        </div>
                    </div>
                </div>
            </header>

            {/* Main content */}
            <main className="max-w-7xl px-4 sm:px-6 lg:px-8 py-8 mx-auto">{children}</main>
        </div>
    );
}
