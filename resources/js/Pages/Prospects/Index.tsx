import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head, Link, router, usePage } from '@inertiajs/react';
import axios from 'axios';
import { useEffect, useState } from 'react';

interface Prospect {
    id: number;
    company_name: string;
    status: string;
    created_at: string;
}

interface StatusInfo {
    label: string;
    description: string;
}

interface Props {
    prospects: Prospect[];
    statuses: Record<string, StatusInfo>;
}

export default function Index({ prospects: initialProspects, statuses }: Props) {
    const { props } = usePage();
    const flash = (props as { flash?: { success?: string } }).flash;
    const [showFlash, setShowFlash] = useState(!!flash?.success);
    const [prospects, setProspects] = useState(initialProspects);
    const [updatingId, setUpdatingId] = useState<number | null>(null);

    // Auto-hide flash message after 5 seconds
    useEffect(() => {
        if (flash?.success) {
            setShowFlash(true);
            const timer = setTimeout(() => setShowFlash(false), 5000);
            return () => clearTimeout(timer);
        }
    }, [flash?.success]);

    const handleStatusChange = async (prospectId: number, newStatus: string) => {
        setUpdatingId(prospectId);
        try {
            await axios.patch(route('prospects.update-status', prospectId), {
                status: newStatus,
            });

            // Update local state
            setProspects((prev) => prev.map((p) => (p.id === prospectId ? { ...p, status: newStatus } : p)));
        } catch (error) {
            console.error('Failed to update status:', error);
        } finally {
            setUpdatingId(null);
        }
    };

    return (
        <AuthenticatedLayout
            header={
                <div className="flex items-center justify-between">
                    <h2 className="text-xl leading-tight font-semibold text-gray-800">Prospects</h2>
                    <Link href={route('prospects.create')} className="rounded-md bg-indigo-600 px-4 py-2 text-sm text-white hover:bg-indigo-700">
                        New Prospect
                    </Link>
                </div>
            }
        >
            <Head title="Prospects" />

            <div className="py-12">
                <div className="mx-auto max-w-7xl sm:px-6 lg:px-8">
                    {/* Success Flash Message */}
                    {showFlash && flash?.success && (
                        <div className="mb-6 rounded-md bg-green-50 p-4">
                            <div className="flex">
                                <div className="shrink-0">
                                    <svg className="h-5 w-5 text-green-400" viewBox="0 0 20 20" fill="currentColor">
                                        <path
                                            fillRule="evenodd"
                                            d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.857-9.809a.75.75 0 00-1.214-.882l-3.483 4.79-1.88-1.88a.75.75 0 10-1.06 1.061l2.5 2.5a.75.75 0 001.137-.089l4-5.5z"
                                            clipRule="evenodd"
                                        />
                                    </svg>
                                </div>
                                <div className="ml-3">
                                    <p className="text-sm font-medium text-green-800">{flash.success}</p>
                                </div>
                                <div className="ml-auto pl-3">
                                    <button
                                        onClick={() => setShowFlash(false)}
                                        className="inline-flex rounded-md bg-green-50 p-1.5 text-green-500 hover:bg-green-100"
                                    >
                                        <svg className="h-5 w-5" viewBox="0 0 20 20" fill="currentColor">
                                            <path d="M6.28 5.22a.75.75 0 00-1.06 1.06L8.94 10l-3.72 3.72a.75.75 0 101.06 1.06L10 11.06l3.72 3.72a.75.75 0 101.06-1.06L11.06 10l3.72-3.72a.75.75 0 00-1.06-1.06L10 8.94 6.28 5.22z" />
                                        </svg>
                                    </button>
                                </div>
                            </div>
                        </div>
                    )}

                    <div className="overflow-hidden bg-white shadow-xs sm:rounded-lg">
                        <div className="p-6">
                            {/* Table */}
                            <div className="overflow-x-auto">
                                <table className="min-w-full divide-y divide-gray-200">
                                    <thead className="bg-gray-50">
                                        <tr>
                                            <th className="px-6 py-3 text-left text-xs font-medium tracking-wider text-gray-500 uppercase">
                                                Company Name
                                            </th>
                                            <th className="px-6 py-3 text-left text-xs font-medium tracking-wider text-gray-500 uppercase">Status</th>
                                        </tr>
                                    </thead>
                                    <tbody className="divide-y divide-gray-200 bg-white">
                                        {prospects.map((prospect) => (
                                            <tr key={prospect.id} className="hover:bg-gray-50">
                                                <td
                                                    className="cursor-pointer px-6 py-4 whitespace-nowrap"
                                                    onClick={() => router.get(route('prospects.show', prospect.id))}
                                                >
                                                    <div className="font-medium text-gray-900 hover:text-indigo-600">{prospect.company_name}</div>
                                                </td>
                                                <td className="px-6 py-4 whitespace-nowrap">
                                                    <div className="group relative">
                                                        <select
                                                            value={prospect.status}
                                                            onChange={(e) => handleStatusChange(prospect.id, e.target.value)}
                                                            disabled={updatingId === prospect.id}
                                                            className={`rounded-md border-gray-300 text-sm shadow-xs focus:border-indigo-500 focus:ring-indigo-500 ${updatingId === prospect.id ? 'cursor-wait opacity-50' : ''} `}
                                                        >
                                                            {Object.entries(statuses).map(([value, info]) => (
                                                                <option key={value} value={value}>
                                                                    {info.label}
                                                                </option>
                                                            ))}
                                                        </select>
                                                        {/* Tooltip */}
                                                        {statuses[prospect.status] && (
                                                            <div className="absolute bottom-full left-0 z-10 mb-2 hidden group-hover:block">
                                                                <div className="rounded bg-gray-900 px-2 py-1 text-xs whitespace-nowrap text-white">
                                                                    {statuses[prospect.status].description}
                                                                </div>
                                                                <div className="absolute -bottom-1 left-4 h-2 w-2 rotate-45 transform bg-gray-900"></div>
                                                            </div>
                                                        )}
                                                    </div>
                                                </td>
                                            </tr>
                                        ))}
                                    </tbody>
                                </table>
                            </div>

                            {prospects.length === 0 && (
                                <div className="py-8 text-center text-gray-500">No prospects found. Click "New Prospect" to add one.</div>
                            )}
                        </div>
                    </div>
                </div>
            </div>
        </AuthenticatedLayout>
    );
}
