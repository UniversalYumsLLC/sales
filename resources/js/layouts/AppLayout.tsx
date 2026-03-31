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
        <div className="min-h-screen bg-gray-50">
            {/* Header */}
            <header className="border-b border-gray-200 bg-white">
                <div className="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
                    <div className="flex h-16 items-center justify-between">
                        <div className="flex items-center space-x-8">
                            <h1 className="text-xl font-semibold text-gray-900">Sales Tools</h1>
                            <nav className="flex space-x-1">
                                {tabs.map((tab) => {
                                    const isActive = tab.pattern.test(url);
                                    return (
                                        <Link
                                            key={tab.name}
                                            href={tab.href}
                                            className={`rounded-md px-4 py-2 text-sm font-medium transition-colors ${
                                                isActive ? 'bg-gray-100 text-gray-900' : 'text-gray-600 hover:bg-gray-50 hover:text-gray-900'
                                            }`}
                                        >
                                            {tab.name}
                                        </Link>
                                    );
                                })}
                            </nav>
                        </div>
                        <div className="flex items-center space-x-4">
                            {lastUpdated && <span className="text-sm text-gray-500">Data as of {formatLastUpdated(lastUpdated)}</span>}
                            <button
                                onClick={handleRefresh}
                                disabled={isRefreshing}
                                className="inline-flex items-center rounded-md border border-gray-300 bg-white px-3 py-1.5 text-sm font-medium text-gray-700 hover:bg-gray-50 focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2 focus:outline-none disabled:opacity-50"
                            >
                                <RefreshCw className={`mr-1.5 h-4 w-4 ${isRefreshing ? 'animate-spin' : ''}`} />
                                Refresh Data
                            </button>
                        </div>
                    </div>
                </div>
            </header>

            {/* Main content */}
            <main className="mx-auto max-w-7xl px-4 py-8 sm:px-6 lg:px-8">{children}</main>
        </div>
    );
}
