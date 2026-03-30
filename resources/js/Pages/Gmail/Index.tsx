import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head, router, usePage } from '@inertiajs/react';
import { useState } from 'react';

interface SyncHistoryItem {
    id: number;
    sync_type: 'full' | 'domain';
    entity_type: 'prospect' | 'customer' | null;
    entity_id: number | null;
    domains: string[] | null;
    sync_started_at: string;
    sync_completed_at: string | null;
    emails_from: string;
    emails_to: string;
    emails_fetched: number;
    emails_matched: number;
    status: 'running' | 'completed' | 'failed';
    error_message: string | null;
    user?: {
        id: number;
        name: string;
    };
}

interface Salesperson {
    id: number;
    name: string;
    email: string;
    is_connected: boolean;
    gmail_email: string | null;
    connected_at: string | null;
}

interface Props {
    isAdmin: boolean;
    isConnected: boolean;
    gmailEmail: string | null;
    connectedAt: string | null;
    lastSync: SyncHistoryItem | null;
    syncHistory: SyncHistoryItem[];
    salespersons: Salesperson[];
}

interface Flash {
    success?: string;
    error?: string;
}

export default function Index({ isAdmin, isConnected, gmailEmail, connectedAt, lastSync, syncHistory, salespersons }: Props) {
    const pageProps = usePage().props as unknown as { flash?: Flash };
    const flash = pageProps.flash;
    const [fullSyncing, setFullSyncing] = useState(false);
    const [fullSyncingAll, setFullSyncingAll] = useState(false);
    const [backfillingDomains, setBackfillingDomains] = useState(false);
    const [disconnecting, setDisconnecting] = useState(false);

    const handleFullSync = () => {
        if (!confirm('This will resync all emails from the past 365 days. This may take several minutes. Continue?')) {
            return;
        }
        setFullSyncing(true);
        router.post(route('gmail.full-sync'), {}, {
            onFinish: () => setFullSyncing(false),
        });
    };

    const handleFullSyncAll = () => {
        if (!confirm('This will resync all emails from the past 365 days for ALL salespersons. This may take a long time. Continue?')) {
            return;
        }
        setFullSyncingAll(true);
        router.post(route('gmail.full-sync-all'), {}, {
            onFinish: () => setFullSyncingAll(false),
        });
    };

    const handleBackfillDomains = () => {
        if (!confirm('This will extract email domains from all customer contacts and populate missing company domains. Continue?')) {
            return;
        }
        setBackfillingDomains(true);
        router.post(route('gmail.backfill-domains'), {}, {
            onFinish: () => setBackfillingDomains(false),
        });
    };

    const handleDisconnect = () => {
        if (!confirm('Are you sure you want to disconnect Gmail? This will stop email syncing.')) {
            return;
        }
        setDisconnecting(true);
        router.post(route('gmail.disconnect'), {}, {
            onFinish: () => setDisconnecting(false),
        });
    };

    const formatDate = (dateStr: string) => {
        return new Date(dateStr).toLocaleString();
    };

    const formatRelativeTime = (dateStr: string) => {
        const date = new Date(dateStr);
        const now = new Date();
        const diffMs = now.getTime() - date.getTime();
        const diffMins = Math.floor(diffMs / 60000);
        const diffHours = Math.floor(diffMins / 60);
        const diffDays = Math.floor(diffHours / 24);

        if (diffMins < 1) return 'Just now';
        if (diffMins < 60) return `${diffMins} minute${diffMins !== 1 ? 's' : ''} ago`;
        if (diffHours < 24) return `${diffHours} hour${diffHours !== 1 ? 's' : ''} ago`;
        return `${diffDays} day${diffDays !== 1 ? 's' : ''} ago`;
    };

    const getStatusBadge = (status: string) => {
        switch (status) {
            case 'completed':
                return <span className="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-green-100 text-green-800">Completed</span>;
            case 'running':
                return <span className="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-blue-100 text-blue-800">Running</span>;
            case 'failed':
                return <span className="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-red-100 text-red-800">Failed</span>;
            default:
                return <span className="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-gray-100 text-gray-800">{status}</span>;
        }
    };

    const getSyncTypeBadge = (sync: SyncHistoryItem) => {
        if (sync.sync_type === 'full') {
            return <span className="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-purple-100 text-purple-800">Full</span>;
        }
        const label = sync.entity_type === 'prospect' ? 'Prospect' : 'Customer';
        return <span className="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-indigo-100 text-indigo-800">{label}</span>;
    };

    const getSyncDescription = (sync: SyncHistoryItem) => {
        if (sync.sync_type === 'full') {
            return 'Full sync';
        }
        const domains = sync.domains?.join(', ') || 'unknown';
        return domains;
    };

    // Admin View
    if (isAdmin) {
        return (
            <AuthenticatedLayout
                header={
                    <h2 className="text-xl font-semibold leading-tight text-gray-800">
                        Gmail Integration - Admin View
                    </h2>
                }
            >
                <Head title="Gmail Integration - Admin" />

                <div className="py-12">
                    <div className="mx-auto max-w-6xl space-y-6 sm:px-6 lg:px-8">
                        {/* Flash Messages */}
                        {flash?.success && (
                            <div className="rounded-md bg-green-50 p-4">
                                <div className="flex">
                                    <div className="shrink-0">
                                        <svg className="h-5 w-5 text-green-400" viewBox="0 0 20 20" fill="currentColor">
                                            <path fillRule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clipRule="evenodd" />
                                        </svg>
                                    </div>
                                    <div className="ml-3">
                                        <p className="text-sm font-medium text-green-800">{flash.success}</p>
                                    </div>
                                </div>
                            </div>
                        )}

                        {flash?.error && (
                            <div className="rounded-md bg-red-50 p-4">
                                <div className="flex">
                                    <div className="shrink-0">
                                        <svg className="h-5 w-5 text-red-400" viewBox="0 0 20 20" fill="currentColor">
                                            <path fillRule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zM8.707 7.293a1 1 0 00-1.414 1.414L8.586 10l-1.293 1.293a1 1 0 101.414 1.414L10 11.414l1.293 1.293a1 1 0 001.414-1.414L11.414 10l1.293-1.293a1 1 0 00-1.414-1.414L10 8.586 8.707 7.293z" clipRule="evenodd" />
                                        </svg>
                                    </div>
                                    <div className="ml-3">
                                        <p className="text-sm font-medium text-red-800">{flash.error}</p>
                                    </div>
                                </div>
                            </div>
                        )}

                        {/* Admin Actions */}
                        <div className="overflow-hidden bg-white shadow-xs sm:rounded-lg">
                            <div className="p-6">
                                <h3 className="text-lg font-medium text-gray-900 mb-4">Admin Actions</h3>
                                <p className="text-sm text-gray-600 mb-4">
                                    Use these actions to maintain email sync data across all customers and salespersons.
                                </p>
                                <div className="flex flex-wrap gap-4">
                                    <div className="flex-1 min-w-[280px] p-4 border border-gray-200 rounded-lg">
                                        <h4 className="text-sm font-medium text-gray-900 mb-2">Full Email Resync</h4>
                                        <p className="text-xs text-gray-500 mb-3">
                                            Resync all emails from the past 365 days for all salespersons. Useful for recovering from sync errors.
                                        </p>
                                        <button
                                            onClick={handleFullSyncAll}
                                            disabled={fullSyncingAll}
                                            className="inline-flex items-center px-4 py-2 bg-orange-600 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-orange-700 focus:bg-orange-700 active:bg-orange-900 focus:outline-none focus:ring-2 focus:ring-orange-500 focus:ring-offset-2 disabled:opacity-50 transition ease-in-out duration-150"
                                        >
                                            {fullSyncingAll ? 'Starting...' : 'Full Resync All (365 days)'}
                                        </button>
                                    </div>
                                    <div className="flex-1 min-w-[280px] p-4 border border-gray-200 rounded-lg">
                                        <h4 className="text-sm font-medium text-gray-900 mb-2">Backfill Company Domains</h4>
                                        <p className="text-xs text-gray-500 mb-3">
                                            Extract email domains from customer contacts and populate missing company domains for email matching.
                                        </p>
                                        <button
                                            onClick={handleBackfillDomains}
                                            disabled={backfillingDomains}
                                            className="inline-flex items-center px-4 py-2 bg-indigo-600 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-indigo-700 focus:bg-indigo-700 active:bg-indigo-900 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2 disabled:opacity-50 transition ease-in-out duration-150"
                                        >
                                            {backfillingDomains ? 'Processing...' : 'Backfill Domains'}
                                        </button>
                                    </div>
                                </div>
                            </div>
                        </div>

                        {/* Salesperson Connection Status */}
                        <div className="overflow-hidden bg-white shadow-xs sm:rounded-lg">
                            <div className="p-6">
                                <h3 className="text-lg font-medium text-gray-900 mb-4">Salesperson Gmail Connections</h3>

                                {salespersons.length === 0 ? (
                                    <p className="text-sm text-gray-500">No salespersons found.</p>
                                ) : (
                                    <div className="overflow-x-auto">
                                        <table className="min-w-full divide-y divide-gray-200">
                                            <thead className="bg-gray-50">
                                                <tr>
                                                    <th className="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                                        Name
                                                    </th>
                                                    <th className="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                                        Email
                                                    </th>
                                                    <th className="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                                        Status
                                                    </th>
                                                    <th className="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                                        Gmail Account
                                                    </th>
                                                    <th className="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                                        Connected Since
                                                    </th>
                                                </tr>
                                            </thead>
                                            <tbody className="bg-white divide-y divide-gray-200">
                                                {salespersons.map((sp) => (
                                                    <tr key={sp.id}>
                                                        <td className="px-4 py-3 whitespace-nowrap text-sm font-medium text-gray-900">
                                                            {sp.name}
                                                        </td>
                                                        <td className="px-4 py-3 whitespace-nowrap text-sm text-gray-500">
                                                            {sp.email}
                                                        </td>
                                                        <td className="px-4 py-3 whitespace-nowrap">
                                                            {sp.is_connected ? (
                                                                <span className="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-green-100 text-green-800">
                                                                    Connected
                                                                </span>
                                                            ) : (
                                                                <span className="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-gray-100 text-gray-800">
                                                                    Not Connected
                                                                </span>
                                                            )}
                                                        </td>
                                                        <td className="px-4 py-3 whitespace-nowrap text-sm text-gray-500">
                                                            {sp.gmail_email || '-'}
                                                        </td>
                                                        <td className="px-4 py-3 whitespace-nowrap text-sm text-gray-500">
                                                            {sp.connected_at ? formatDate(sp.connected_at) : '-'}
                                                        </td>
                                                    </tr>
                                                ))}
                                            </tbody>
                                        </table>
                                    </div>
                                )}
                            </div>
                        </div>

                        {/* All Sync History */}
                        <div className="overflow-hidden bg-white shadow-xs sm:rounded-lg">
                            <div className="p-6">
                                <h3 className="text-lg font-medium text-gray-900 mb-4">Sync History (All Salespersons)</h3>

                                {syncHistory.length === 0 ? (
                                    <p className="text-sm text-gray-500">No sync history found.</p>
                                ) : (
                                    <div className="overflow-x-auto">
                                        <table className="min-w-full divide-y divide-gray-200">
                                            <thead className="bg-gray-50">
                                                <tr>
                                                    <th className="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                                        Date
                                                    </th>
                                                    <th className="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                                        Salesperson
                                                    </th>
                                                    <th className="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                                        Type
                                                    </th>
                                                    <th className="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                                        Domains
                                                    </th>
                                                    <th className="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                                        Status
                                                    </th>
                                                    <th className="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                                        Fetched
                                                    </th>
                                                    <th className="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                                        Matched
                                                    </th>
                                                    <th className="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                                        Date Range
                                                    </th>
                                                </tr>
                                            </thead>
                                            <tbody className="bg-white divide-y divide-gray-200">
                                                {syncHistory.map((sync) => (
                                                    <tr key={sync.id} className={sync.status === 'failed' ? 'bg-red-50' : ''}>
                                                        <td className="px-4 py-3 whitespace-nowrap text-sm text-gray-900">
                                                            {formatDate(sync.sync_started_at)}
                                                        </td>
                                                        <td className="px-4 py-3 whitespace-nowrap text-sm text-gray-900">
                                                            {sync.user?.name || '-'}
                                                        </td>
                                                        <td className="px-4 py-3 whitespace-nowrap">
                                                            {getSyncTypeBadge(sync)}
                                                        </td>
                                                        <td className="px-4 py-3 text-sm text-gray-500 max-w-xs truncate" title={getSyncDescription(sync)}>
                                                            {getSyncDescription(sync)}
                                                        </td>
                                                        <td className="px-4 py-3 whitespace-nowrap">
                                                            {getStatusBadge(sync.status)}
                                                        </td>
                                                        <td className="px-4 py-3 whitespace-nowrap text-sm text-gray-500">
                                                            {sync.emails_fetched ?? '-'}
                                                        </td>
                                                        <td className="px-4 py-3 whitespace-nowrap text-sm text-gray-500">
                                                            {sync.emails_matched ?? '-'}
                                                        </td>
                                                        <td className="px-4 py-3 whitespace-nowrap text-sm text-gray-500">
                                                            {new Date(sync.emails_from).toLocaleDateString()} - {new Date(sync.emails_to).toLocaleDateString()}
                                                        </td>
                                                    </tr>
                                                ))}
                                            </tbody>
                                        </table>
                                    </div>
                                )}
                            </div>
                        </div>
                    </div>
                </div>
            </AuthenticatedLayout>
        );
    }

    // Salesperson View
    return (
        <AuthenticatedLayout
            header={
                <h2 className="text-xl font-semibold leading-tight text-gray-800">
                    Gmail Integration
                </h2>
            }
        >
            <Head title="Gmail Integration" />

            <div className="py-12">
                <div className="mx-auto max-w-4xl space-y-6 sm:px-6 lg:px-8">
                    {/* Flash Messages */}
                    {flash?.success && (
                        <div className="rounded-md bg-green-50 p-4">
                            <div className="flex">
                                <div className="shrink-0">
                                    <svg className="h-5 w-5 text-green-400" viewBox="0 0 20 20" fill="currentColor">
                                        <path fillRule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clipRule="evenodd" />
                                    </svg>
                                </div>
                                <div className="ml-3">
                                    <p className="text-sm font-medium text-green-800">{flash.success}</p>
                                </div>
                            </div>
                        </div>
                    )}

                    {flash?.error && (
                        <div className="rounded-md bg-red-50 p-4">
                            <div className="flex">
                                <div className="shrink-0">
                                    <svg className="h-5 w-5 text-red-400" viewBox="0 0 20 20" fill="currentColor">
                                        <path fillRule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zM8.707 7.293a1 1 0 00-1.414 1.414L8.586 10l-1.293 1.293a1 1 0 101.414 1.414L10 11.414l1.293 1.293a1 1 0 001.414-1.414L11.414 10l1.293-1.293a1 1 0 00-1.414-1.414L10 8.586 8.707 7.293z" clipRule="evenodd" />
                                    </svg>
                                </div>
                                <div className="ml-3">
                                    <p className="text-sm font-medium text-red-800">{flash.error}</p>
                                </div>
                            </div>
                        </div>
                    )}

                    {/* Connection Status Card */}
                    <div className="overflow-hidden bg-white shadow-xs sm:rounded-lg">
                        <div className="p-6">
                            <h3 className="text-lg font-medium text-gray-900 mb-4">Connection Status</h3>

                            {isConnected ? (
                                <div className="space-y-4">
                                    <div className="flex items-center">
                                        <div className="shrink-0">
                                            <div className="h-10 w-10 rounded-full bg-green-100 flex items-center justify-center">
                                                <svg className="h-6 w-6 text-green-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                    <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M5 13l4 4L19 7" />
                                                </svg>
                                            </div>
                                        </div>
                                        <div className="ml-4">
                                            <p className="text-sm font-medium text-gray-900">Connected</p>
                                            <p className="text-sm text-gray-500">{gmailEmail}</p>
                                        </div>
                                    </div>

                                    <div className="border-t border-gray-200 pt-4">
                                        <dl className="grid grid-cols-2 gap-4">
                                            <div>
                                                <dt className="text-sm font-medium text-gray-500">Connected Since</dt>
                                                <dd className="text-sm text-gray-900">{connectedAt ? formatDate(connectedAt) : '-'}</dd>
                                            </div>
                                            <div>
                                                <dt className="text-sm font-medium text-gray-500">Last Sync</dt>
                                                <dd className="text-sm text-gray-900">
                                                    {lastSync ? formatRelativeTime(lastSync.sync_started_at) : 'Never'}
                                                </dd>
                                            </div>
                                        </dl>
                                    </div>

                                    <div className="flex flex-wrap gap-3 pt-2">
                                        <button
                                            onClick={handleFullSync}
                                            disabled={fullSyncing}
                                            className="inline-flex items-center px-4 py-2 bg-indigo-600 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-indigo-700 focus:bg-indigo-700 active:bg-indigo-900 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2 disabled:opacity-50 transition ease-in-out duration-150"
                                        >
                                            {fullSyncing ? 'Syncing...' : 'Full Resync (365 days)'}
                                        </button>
                                        <button
                                            onClick={handleDisconnect}
                                            disabled={disconnecting}
                                            className="inline-flex items-center px-4 py-2 bg-white border border-red-300 rounded-md font-semibold text-xs text-red-700 uppercase tracking-widest hover:bg-red-50 focus:outline-none focus:ring-2 focus:ring-red-500 focus:ring-offset-2 disabled:opacity-50 transition ease-in-out duration-150"
                                        >
                                            {disconnecting ? 'Disconnecting...' : 'Disconnect'}
                                        </button>
                                    </div>
                                </div>
                            ) : (
                                <div className="space-y-4">
                                    <div className="flex items-center">
                                        <div className="shrink-0">
                                            <div className="h-10 w-10 rounded-full bg-gray-100 flex items-center justify-center">
                                                <svg className="h-6 w-6 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                    <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z" />
                                                </svg>
                                            </div>
                                        </div>
                                        <div className="ml-4">
                                            <p className="text-sm font-medium text-gray-900">Not Connected</p>
                                            <p className="text-sm text-gray-500">Connect your Gmail to automatically track prospect emails</p>
                                        </div>
                                    </div>

                                    <div className="bg-gray-50 rounded-md p-4">
                                        <h4 className="text-sm font-medium text-gray-900 mb-2">What happens when you connect?</h4>
                                        <ul className="text-sm text-gray-600 space-y-1">
                                            <li>- Emails to/from prospects are automatically tracked</li>
                                            <li>- "Last Emailed" and "Last Received" dates are updated for buyer contacts</li>
                                            <li>- Email content is stored for future reference</li>
                                            <li>- Sync runs every 15 minutes automatically</li>
                                        </ul>
                                    </div>

                                    <a
                                        href={route('gmail.connect')}
                                        className="inline-flex items-center px-4 py-2 bg-indigo-600 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-indigo-700 focus:bg-indigo-700 active:bg-indigo-900 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2 transition ease-in-out duration-150"
                                    >
                                        <svg className="w-5 h-5 mr-2" viewBox="0 0 24 24" fill="currentColor">
                                            <path d="M20.283 10.356h-8.327v3.451h4.792c-.446 2.193-2.313 3.453-4.792 3.453a5.27 5.27 0 0 1-5.279-5.28 5.27 5.27 0 0 1 5.279-5.279c1.259 0 2.397.447 3.29 1.178l2.6-2.599c-1.584-1.381-3.615-2.233-5.89-2.233a8.908 8.908 0 0 0-8.934 8.934 8.907 8.907 0 0 0 8.934 8.934c4.467 0 8.529-3.249 8.529-8.934 0-.528-.081-1.097-.202-1.625z"/>
                                        </svg>
                                        Connect Gmail
                                    </a>
                                </div>
                            )}
                        </div>
                    </div>

                    {/* Sync History */}
                    {syncHistory.length > 0 && (
                        <div className="overflow-hidden bg-white shadow-xs sm:rounded-lg">
                            <div className="p-6">
                                <h3 className="text-lg font-medium text-gray-900 mb-4">Sync History</h3>

                                <div className="overflow-x-auto">
                                    <table className="min-w-full divide-y divide-gray-200">
                                        <thead className="bg-gray-50">
                                            <tr>
                                                <th className="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                                    Date
                                                </th>
                                                <th className="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                                    Type
                                                </th>
                                                <th className="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                                    Status
                                                </th>
                                                <th className="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                                    Fetched
                                                </th>
                                                <th className="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                                    Matched
                                                </th>
                                                <th className="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                                    Date Range
                                                </th>
                                            </tr>
                                        </thead>
                                        <tbody className="bg-white divide-y divide-gray-200">
                                            {syncHistory.map((sync) => (
                                                <tr key={sync.id} className={sync.status === 'failed' ? 'bg-red-50' : ''}>
                                                    <td className="px-4 py-3 whitespace-nowrap text-sm text-gray-900">
                                                        {formatDate(sync.sync_started_at)}
                                                    </td>
                                                    <td className="px-4 py-3 whitespace-nowrap">
                                                        {getSyncTypeBadge(sync)}
                                                        {sync.sync_type === 'domain' && sync.domains && (
                                                            <span className="ml-2 text-xs text-gray-500" title={sync.domains.join(', ')}>
                                                                {sync.domains.length > 1 ? `${sync.domains[0]} +${sync.domains.length - 1}` : sync.domains[0]}
                                                            </span>
                                                        )}
                                                    </td>
                                                    <td className="px-4 py-3 whitespace-nowrap">
                                                        {getStatusBadge(sync.status)}
                                                        {sync.status === 'failed' && sync.error_message && (
                                                            <span className="ml-2 text-xs text-red-600" title={sync.error_message}>
                                                                (hover for details)
                                                            </span>
                                                        )}
                                                    </td>
                                                    <td className="px-4 py-3 whitespace-nowrap text-sm text-gray-500">
                                                        {sync.emails_fetched ?? '-'}
                                                    </td>
                                                    <td className="px-4 py-3 whitespace-nowrap text-sm text-gray-500">
                                                        {sync.emails_matched ?? '-'}
                                                    </td>
                                                    <td className="px-4 py-3 whitespace-nowrap text-sm text-gray-500">
                                                        {new Date(sync.emails_from).toLocaleDateString()} - {new Date(sync.emails_to).toLocaleDateString()}
                                                    </td>
                                                </tr>
                                            ))}
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        </div>
                    )}

                    {/* Help Text */}
                    <div className="bg-blue-50 rounded-lg p-4">
                        <div className="flex">
                            <div className="shrink-0">
                                <svg className="h-5 w-5 text-blue-400" viewBox="0 0 20 20" fill="currentColor">
                                    <path fillRule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7-4a1 1 0 11-2 0 1 1 0 012 0zM9 9a1 1 0 000 2v3a1 1 0 001 1h1a1 1 0 100-2v-3a1 1 0 00-1-1H9z" clipRule="evenodd" />
                                </svg>
                            </div>
                            <div className="ml-3">
                                <h3 className="text-sm font-medium text-blue-800">How email matching works</h3>
                                <div className="mt-2 text-sm text-blue-700">
                                    <p>Emails are matched to prospects based on their Company URLs. Make sure to add all email domains used by a prospect (e.g., "company.com", "company-sales.com") to their Company URLs field.</p>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </AuthenticatedLayout>
    );
}
