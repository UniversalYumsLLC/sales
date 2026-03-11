import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head, router } from '@inertiajs/react';
import { useState } from 'react';

interface Invoice {
    id: number;
    number: string | null;
    total_amount: number;
    balance: number;
    due_date: string | null;
    days_overdue: number; // positive = overdue, negative = days until due
}

interface Customer {
    id: number;
    name: string;
    ap_contact_names: string[];
    invoices: Invoice[];
    total_due: number;
    total_overdue: number;
    total_severely_overdue: number;
}

interface Totals {
    total_due: number;
    total_overdue: number;
    total_severely_overdue: number;
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

function formatDate(dateStr: string | null): string {
    if (!dateStr) return '-';
    return new Date(dateStr).toLocaleDateString();
}

function getInvoiceStatus(daysOverdue: number): { text: string; className: string } {
    if (daysOverdue > 30) {
        return { text: `Overdue by ${daysOverdue} days`, className: 'text-red-600' };
    } else if (daysOverdue > 0) {
        return { text: `Overdue by ${daysOverdue} days`, className: 'text-orange-600' };
    } else if (daysOverdue < 0) {
        return { text: `Due in ${Math.abs(daysOverdue)} days`, className: 'text-green-600' };
    } else {
        return { text: 'Due today', className: 'text-orange-600' };
    }
}

export default function Index({ customers, totals, search, lastUpdated }: Props) {
    const [searchTerm, setSearchTerm] = useState(search);
    const [expandedCustomer, setExpandedCustomer] = useState<number | null>(null);

    const handleSearch = (e: React.FormEvent) => {
        e.preventDefault();
        router.get(route('ar.index'), { search: searchTerm }, { preserveState: true });
    };

    const handleRefresh = () => {
        router.get(route('ar.index'), { refresh: true, search: searchTerm }, { preserveState: true });
    };

    const toggleExpanded = (customerId: number) => {
        setExpandedCustomer(expandedCustomer === customerId ? null : customerId);
    };

    return (
        <AuthenticatedLayout
            header={
                <div className="flex items-center justify-between">
                    <h2 className="text-xl font-semibold leading-tight text-gray-800">
                        Accounts Receivable
                    </h2>
                    <button
                        onClick={handleRefresh}
                        className="text-sm text-gray-500 hover:text-gray-700"
                    >
                        Refresh Data
                    </button>
                </div>
            }
        >
            <Head title="Accounts Receivable" />

            <div className="py-12">
                <div className="mx-auto max-w-7xl sm:px-6 lg:px-8">
                    {/* Summary Cards */}
                    <div className="mb-6 grid grid-cols-1 gap-4 sm:grid-cols-4">
                        <div className="rounded-lg bg-white p-6 shadow-sm">
                            <div className="text-sm font-medium text-gray-500">Customers</div>
                            <div className="mt-1 text-2xl font-semibold text-gray-900">
                                {customers.length}
                            </div>
                        </div>
                        <div className="rounded-lg bg-white p-6 shadow-sm">
                            <div className="text-sm font-medium text-gray-500">Total Outstanding</div>
                            <div className="mt-1 text-2xl font-semibold text-gray-900">
                                {formatCurrency(totals.total_due)}
                            </div>
                        </div>
                        <div className="rounded-lg bg-white p-6 shadow-sm">
                            <div className="text-sm font-medium text-gray-500">Total Overdue</div>
                            <div className="mt-1 text-2xl font-semibold text-orange-600">
                                {formatCurrency(totals.total_overdue)}
                            </div>
                        </div>
                        <div className="rounded-lg bg-white p-6 shadow-sm">
                            <div className="text-sm font-medium text-gray-500">Severely Overdue (30+ days)</div>
                            <div className="mt-1 text-2xl font-semibold text-red-600">
                                {formatCurrency(totals.total_severely_overdue)}
                            </div>
                        </div>
                    </div>

                    <div className="overflow-hidden bg-white shadow-sm sm:rounded-lg">
                        <div className="p-6">
                            {/* Search */}
                            <form onSubmit={handleSearch} className="mb-6 flex gap-2">
                                <input
                                    type="text"
                                    value={searchTerm}
                                    onChange={(e) => setSearchTerm(e.target.value)}
                                    placeholder="Search customers..."
                                    className="rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                                />
                                <button
                                    type="submit"
                                    className="rounded-md bg-indigo-600 px-4 py-2 text-white hover:bg-indigo-700"
                                >
                                    Search
                                </button>
                            </form>

                            {/* Customer List */}
                            <div className="space-y-4">
                                {customers.map((customer) => (
                                    <div
                                        key={customer.id}
                                        className="rounded-lg border border-gray-200 bg-white"
                                    >
                                        <div
                                            className="flex cursor-pointer items-center justify-between p-4 hover:bg-gray-50"
                                            onClick={() => toggleExpanded(customer.id)}
                                        >
                                            <div>
                                                <div className="font-medium text-gray-900">
                                                    {customer.name}
                                                </div>
                                                {customer.ap_contact_names.length > 0 && (
                                                    <div className="text-sm text-gray-500">
                                                        AP: {customer.ap_contact_names.join(', ')}
                                                    </div>
                                                )}
                                            </div>
                                            <div className="flex items-center gap-6">
                                                <div className="text-right">
                                                    <div className="text-sm text-gray-500">Outstanding</div>
                                                    <div className="font-medium text-gray-900">
                                                        {formatCurrency(customer.total_due)}
                                                    </div>
                                                </div>
                                                {customer.total_overdue > 0 && (
                                                    <div className="text-right">
                                                        <div className="text-sm text-gray-500">Overdue</div>
                                                        <div className="font-medium text-orange-600">
                                                            {formatCurrency(customer.total_overdue)}
                                                        </div>
                                                    </div>
                                                )}
                                                {customer.total_severely_overdue > 0 && (
                                                    <div className="text-right">
                                                        <div className="text-sm text-gray-500">30+ Days</div>
                                                        <div className="font-medium text-red-600">
                                                            {formatCurrency(customer.total_severely_overdue)}
                                                        </div>
                                                    </div>
                                                )}
                                                <svg
                                                    className={`h-5 w-5 text-gray-400 transition-transform ${
                                                        expandedCustomer === customer.id ? 'rotate-180' : ''
                                                    }`}
                                                    fill="none"
                                                    viewBox="0 0 24 24"
                                                    stroke="currentColor"
                                                >
                                                    <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M19 9l-7 7-7-7" />
                                                </svg>
                                            </div>
                                        </div>

                                        {/* Expanded Invoice List */}
                                        {expandedCustomer === customer.id && (
                                            <div className="border-t border-gray-200 bg-gray-50 p-4">
                                                <table className="min-w-full">
                                                    <thead>
                                                        <tr>
                                                            <th className="pb-2 text-left text-xs font-medium uppercase text-gray-500">
                                                                Invoice #
                                                            </th>
                                                            <th className="pb-2 text-left text-xs font-medium uppercase text-gray-500">
                                                                Due Date
                                                            </th>
                                                            <th className="pb-2 text-right text-xs font-medium uppercase text-gray-500">
                                                                Total
                                                            </th>
                                                            <th className="pb-2 text-right text-xs font-medium uppercase text-gray-500">
                                                                Balance
                                                            </th>
                                                            <th className="pb-2 text-right text-xs font-medium uppercase text-gray-500">
                                                                Status
                                                            </th>
                                                        </tr>
                                                    </thead>
                                                    <tbody className="divide-y divide-gray-200">
                                                        {customer.invoices.map((invoice) => {
                                                            const status = getInvoiceStatus(invoice.days_overdue);
                                                            return (
                                                                <tr key={invoice.id}>
                                                                    <td className="py-2 text-sm text-gray-900">
                                                                        {invoice.number || '-'}
                                                                    </td>
                                                                    <td className="py-2 text-sm text-gray-500">
                                                                        {formatDate(invoice.due_date)}
                                                                    </td>
                                                                    <td className="py-2 text-right text-sm text-gray-500">
                                                                        {formatCurrency(invoice.total_amount)}
                                                                    </td>
                                                                    <td className="py-2 text-right text-sm text-gray-900">
                                                                        {formatCurrency(invoice.balance)}
                                                                    </td>
                                                                    <td className={`py-2 text-right text-sm ${status.className}`}>
                                                                        {status.text}
                                                                    </td>
                                                                </tr>
                                                            );
                                                        })}
                                                    </tbody>
                                                </table>
                                            </div>
                                        )}
                                    </div>
                                ))}
                            </div>

                            {customers.length === 0 && (
                                <div className="py-8 text-center text-gray-500">
                                    No customers with outstanding invoices
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
