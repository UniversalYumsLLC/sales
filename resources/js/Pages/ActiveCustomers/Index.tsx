import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head, Link, router, usePage } from '@inertiajs/react';
import { useState, useEffect } from 'react';

interface Customer {
    id: number;
    name: string;
    code: string | null;
    open_po_count: number;
    open_po_total: number;
    t12m_revenue: number;
    prior_year_revenue: number;
    revenue_change: number;
    overdue_count: number;
    overdue_total: number;
    late_shipments_count: number;
}

interface Totals {
    total_customers: number;
    open_po_revenue: number;
    t12m_revenue: number;
}

interface Props {
    customers: Customer[];
    totals: Totals;
    search: string;
    lastUpdated: string;
}

function formatCurrency(amount: number): string {
    return new Intl.NumberFormat('en-US', {
        style: 'currency',
        currency: 'USD',
        minimumFractionDigits: 0,
        maximumFractionDigits: 0,
    }).format(amount);
}

export default function Index({ customers, totals, search, lastUpdated }: Props) {
    const { props } = usePage();
    const flash = (props as { flash?: { success?: string } }).flash;
    const [searchTerm, setSearchTerm] = useState(search);
    const [showFlash, setShowFlash] = useState(!!flash?.success);

    // Auto-hide flash message after 5 seconds
    useEffect(() => {
        if (flash?.success) {
            setShowFlash(true);
            const timer = setTimeout(() => setShowFlash(false), 5000);
            return () => clearTimeout(timer);
        }
    }, [flash?.success]);

    const handleSearch = (e: React.FormEvent) => {
        e.preventDefault();
        router.get(route('customers.index'), { search: searchTerm }, { preserveState: true });
    };

    const handleRefresh = () => {
        router.get(route('customers.index'), { refresh: true, search: searchTerm }, { preserveState: true });
    };

    return (
        <AuthenticatedLayout
            header={
                <div className="flex items-center justify-between">
                    <h2 className="text-xl font-semibold leading-tight text-gray-800">
                        Active Customers
                    </h2>
                    <div className="flex items-center gap-4">
                        <button
                            onClick={handleRefresh}
                            className="text-sm text-gray-500 hover:text-gray-700"
                        >
                            Refresh Data
                        </button>
                        <Link
                            href={route('customers.create')}
                            className="rounded-md bg-indigo-600 px-4 py-2 text-sm text-white hover:bg-indigo-700"
                        >
                            Add New Customer
                        </Link>
                    </div>
                </div>
            }
        >
            <Head title="Active Customers" />

            <div className="py-12">
                <div className="mx-auto max-w-7xl sm:px-6 lg:px-8">
                    {/* Success Flash Message */}
                    {showFlash && flash?.success && (
                        <div className="mb-6 rounded-md bg-green-50 p-4">
                            <div className="flex">
                                <div className="flex-shrink-0">
                                    <svg className="h-5 w-5 text-green-400" viewBox="0 0 20 20" fill="currentColor">
                                        <path fillRule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.857-9.809a.75.75 0 00-1.214-.882l-3.483 4.79-1.88-1.88a.75.75 0 10-1.06 1.061l2.5 2.5a.75.75 0 001.137-.089l4-5.5z" clipRule="evenodd" />
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

                    {/* Summary Cards */}
                    <div className="mb-6 grid grid-cols-1 gap-4 sm:grid-cols-3">
                        <div className="rounded-lg bg-white p-6 shadow-sm">
                            <div className="text-sm font-medium text-gray-500">Active Customers</div>
                            <div className="mt-1 text-2xl font-semibold text-gray-900">
                                {totals.total_customers}
                            </div>
                        </div>
                        <div className="rounded-lg bg-white p-6 shadow-sm">
                            <div className="text-sm font-medium text-gray-500">Open POs Revenue</div>
                            <div className="mt-1 text-2xl font-semibold text-gray-900">
                                {formatCurrency(totals.open_po_revenue)}
                            </div>
                        </div>
                        <div className="rounded-lg bg-white p-6 shadow-sm">
                            <div className="text-sm font-medium text-gray-500">T12M Revenue</div>
                            <div className="mt-1 text-2xl font-semibold text-gray-900">
                                {formatCurrency(totals.t12m_revenue)}
                            </div>
                        </div>
                    </div>

                    <div className="overflow-hidden bg-white shadow-sm sm:rounded-lg">
                        <div className="p-6">
                            {/* Search */}
                            <form onSubmit={handleSearch} className="mb-6">
                                <div className="flex gap-2">
                                    <input
                                        type="text"
                                        value={searchTerm}
                                        onChange={(e) => setSearchTerm(e.target.value)}
                                        placeholder="Search customers..."
                                        className="flex-1 rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                                    />
                                    <button
                                        type="submit"
                                        className="rounded-md bg-indigo-600 px-4 py-2 text-white hover:bg-indigo-700"
                                    >
                                        Search
                                    </button>
                                </div>
                            </form>

                            {/* Table */}
                            <div className="overflow-x-auto">
                                <table className="min-w-full divide-y divide-gray-200">
                                    <thead className="bg-gray-50">
                                        <tr>
                                            <th className="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500">
                                                Customer
                                            </th>
                                            <th className="px-6 py-3 text-right text-xs font-medium uppercase tracking-wider text-gray-500">
                                                Overdue Invoices
                                            </th>
                                            <th className="px-6 py-3 text-right text-xs font-medium uppercase tracking-wider text-gray-500">
                                                Late Shipments
                                            </th>
                                            <th className="px-6 py-3 text-right text-xs font-medium uppercase tracking-wider text-gray-500">
                                                Open POs
                                            </th>
                                            <th className="px-6 py-3 text-right text-xs font-medium uppercase tracking-wider text-gray-500">
                                                T12M Revenue
                                            </th>
                                        </tr>
                                    </thead>
                                    <tbody className="divide-y divide-gray-200 bg-white">
                                        {customers.map((customer) => (
                                            <tr
                                                key={customer.id}
                                                className="cursor-pointer hover:bg-gray-50"
                                                onClick={() => router.get(route('customers.show', customer.id))}
                                            >
                                                <td className="whitespace-nowrap px-6 py-4">
                                                    <div className="font-medium text-gray-900">
                                                        {customer.name}
                                                    </div>
                                                </td>
                                                <td className="whitespace-nowrap px-6 py-4 text-right">
                                                    {customer.overdue_total > 0 ? (
                                                        <div className="text-sm text-orange-600">
                                                            {formatCurrency(customer.overdue_total)}
                                                        </div>
                                                    ) : (
                                                        <div className="text-sm text-gray-400">-</div>
                                                    )}
                                                </td>
                                                <td className="whitespace-nowrap px-6 py-4 text-right">
                                                    {customer.late_shipments_count > 0 ? (
                                                        <div className="text-xl font-bold text-red-600">
                                                            {customer.late_shipments_count}
                                                        </div>
                                                    ) : (
                                                        <div className="text-sm text-gray-400">-</div>
                                                    )}
                                                </td>
                                                <td className="whitespace-nowrap px-6 py-4 text-right">
                                                    <div className="text-sm text-gray-900">
                                                        {customer.open_po_count} order{customer.open_po_count !== 1 ? 's' : ''}
                                                    </div>
                                                    <div className="text-sm text-gray-500">
                                                        {formatCurrency(customer.open_po_total)}
                                                    </div>
                                                </td>
                                                <td className="whitespace-nowrap px-6 py-4 text-right">
                                                    <div className="text-sm font-medium text-gray-900">
                                                        {formatCurrency(customer.t12m_revenue)}
                                                    </div>
                                                    {customer.revenue_change !== 0 && (
                                                        <div className={`text-sm ${customer.revenue_change > 0 ? 'text-green-600' : 'text-red-600'}`}>
                                                            {customer.revenue_change > 0 ? '+ ' : '- '}
                                                            {formatCurrency(Math.abs(customer.revenue_change))}
                                                        </div>
                                                    )}
                                                </td>
                                            </tr>
                                        ))}
                                    </tbody>
                                </table>
                            </div>

                            {customers.length === 0 && (
                                <div className="py-8 text-center text-gray-500">
                                    No customers found
                                </div>
                            )}

                            {/* Last updated */}
                            <div className="mt-4 text-right text-xs text-gray-400">
                                Last updated: {new Date(lastUpdated).toLocaleString()}
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </AuthenticatedLayout>
    );
}
