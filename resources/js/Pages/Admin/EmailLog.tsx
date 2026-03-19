import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head, router } from '@inertiajs/react';
import { useState } from 'react';

interface EmailRecord {
    id: number;
    sent_at: string;
    email_type: string;
    email_type_label: string;
    fulfil_party_id: number;
    customer_name: string;
    invoice_id: number | null;
    invoice_fulfil_id: number | null;
    invoice_number: string | null;
    has_pdf: boolean;
}

interface PaginatedData {
    data: EmailRecord[];
    current_page: number;
    last_page: number;
    per_page: number;
    total: number;
    from: number | null;
    to: number | null;
}

interface EmailTypeOption {
    value: string;
    label: string;
}

interface Props {
    emailRecords: PaginatedData;
    filters: {
        type: string;
    };
    emailTypes: EmailTypeOption[];
    fulfilSubdomain: string;
}

function formatDateTime(isoString: string): string {
    const date = new Date(isoString);
    return date.toLocaleString('en-US', {
        month: 'short',
        day: 'numeric',
        year: 'numeric',
        hour: 'numeric',
        minute: '2-digit',
        hour12: true,
    });
}

function getEmailTypeBadgeColor(type: string): string {
    switch (type) {
        case 'initial_invoice':
        case 'initial_invoice_ap_portal':
            return 'bg-blue-100 text-blue-800';
        case 'invoice_modified':
        case 'invoice_modified_ap_portal':
            return 'bg-purple-100 text-purple-800';
        case 'due_reminder':
            return 'bg-yellow-100 text-yellow-800';
        case 'overdue_notification':
            return 'bg-orange-100 text-orange-800';
        case 'overdue_followup':
            return 'bg-red-100 text-red-800';
        case 'sku_mapping_error':
            return 'bg-gray-100 text-gray-800';
        default:
            return 'bg-gray-100 text-gray-800';
    }
}

function getCustomerUrl(subdomain: string, partyId: number): string {
    return `https://${subdomain}.fulfil.io/v2/erp/model/party.party/${partyId}`;
}


export default function EmailLog({ emailRecords, filters, emailTypes, fulfilSubdomain }: Props) {
    const [typeFilter, setTypeFilter] = useState(filters.type);

    const handleFilterChange = (newType: string) => {
        setTypeFilter(newType);
        router.get(route('admin.email-log'), { type: newType || undefined }, { preserveState: true });
    };

    const handlePageChange = (page: number) => {
        router.get(route('admin.email-log'), {
            type: typeFilter || undefined,
            page,
        }, { preserveState: true });
    };

    return (
        <AuthenticatedLayout
            header={
                <h2 className="text-xl font-semibold leading-tight text-gray-800">
                    Email Activity Log
                </h2>
            }
        >
            <Head title="Email Activity Log" />

            <div className="py-12">
                <div className="mx-auto max-w-7xl sm:px-6 lg:px-8">
                    <div className="overflow-hidden bg-white shadow-sm sm:rounded-lg">
                        <div className="p-6">
                            {/* Filters */}
                            <div className="mb-6 flex items-center gap-4">
                                <label className="text-sm font-medium text-gray-700">
                                    Filter by type:
                                </label>
                                <select
                                    value={typeFilter}
                                    onChange={(e) => handleFilterChange(e.target.value)}
                                    className="rounded-md border-gray-300 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                                >
                                    {emailTypes.map((option) => (
                                        <option key={option.value} value={option.value}>
                                            {option.label}
                                        </option>
                                    ))}
                                </select>
                                <span className="text-sm text-gray-500">
                                    {emailRecords.total} record{emailRecords.total !== 1 ? 's' : ''}
                                </span>
                            </div>

                            {/* Table */}
                            {emailRecords.data.length > 0 ? (
                                <div className="overflow-x-auto">
                                    <table className="min-w-full divide-y divide-gray-200">
                                        <thead className="bg-gray-50">
                                            <tr>
                                                <th className="px-4 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500">
                                                    Sent At
                                                </th>
                                                <th className="px-4 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500">
                                                    Customer
                                                </th>
                                                <th className="px-4 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500">
                                                    Invoice
                                                </th>
                                                <th className="px-4 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500">
                                                    Email Type
                                                </th>
                                                <th className="px-4 py-3 text-center text-xs font-medium uppercase tracking-wider text-gray-500">
                                                    PDF
                                                </th>
                                            </tr>
                                        </thead>
                                        <tbody className="divide-y divide-gray-200 bg-white">
                                            {emailRecords.data.map((record) => (
                                                <tr key={record.id} className="hover:bg-gray-50">
                                                    <td className="whitespace-nowrap px-4 py-3 text-sm text-gray-900">
                                                        {formatDateTime(record.sent_at)}
                                                    </td>
                                                    <td className="px-4 py-3 text-sm">
                                                        <a
                                                            href={getCustomerUrl(fulfilSubdomain, record.fulfil_party_id)}
                                                            target="_blank"
                                                            rel="noopener noreferrer"
                                                            className="text-indigo-600 hover:text-indigo-800 hover:underline"
                                                        >
                                                            {record.customer_name}
                                                        </a>
                                                    </td>
                                                    <td className="px-4 py-3 text-sm">
                                                        {record.invoice_number && record.invoice_fulfil_id ? (
                                                            <div className="flex items-center gap-2">
                                                                <span className="text-gray-900">{record.invoice_number}</span>
                                                                {record.has_pdf && (
                                                                    <a
                                                                        href={route('invoices.pdf.download', { id: record.invoice_fulfil_id })}
                                                                        className="text-indigo-600 hover:text-indigo-800"
                                                                        title="Download PDF"
                                                                    >
                                                                        <svg className="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                                                            <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M12 10v6m0 0l-3-3m3 3l3-3m2 8H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                                                                        </svg>
                                                                    </a>
                                                                )}
                                                            </div>
                                                        ) : (
                                                            <span className="text-gray-400">-</span>
                                                        )}
                                                    </td>
                                                    <td className="px-4 py-3 text-sm">
                                                        <span className={`inline-flex rounded-full px-2 py-1 text-xs font-medium ${getEmailTypeBadgeColor(record.email_type)}`}>
                                                            {record.email_type_label}
                                                        </span>
                                                    </td>
                                                    <td className="px-4 py-3 text-center text-sm">
                                                        {record.has_pdf ? (
                                                            <svg className="mx-auto h-5 w-5 text-green-500" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                                                <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
                                                            </svg>
                                                        ) : (
                                                            <svg className="mx-auto h-5 w-5 text-gray-300" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                                                <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M20 12H4" />
                                                            </svg>
                                                        )}
                                                    </td>
                                                </tr>
                                            ))}
                                        </tbody>
                                    </table>
                                </div>
                            ) : (
                                <div className="py-12 text-center text-gray-500">
                                    No email records found
                                </div>
                            )}

                            {/* Pagination */}
                            {emailRecords.last_page > 1 && (
                                <div className="mt-6 flex items-center justify-between border-t border-gray-200 pt-4">
                                    <div className="text-sm text-gray-500">
                                        Showing {emailRecords.from} to {emailRecords.to} of {emailRecords.total} results
                                    </div>
                                    <div className="flex gap-2">
                                        <button
                                            onClick={() => handlePageChange(emailRecords.current_page - 1)}
                                            disabled={emailRecords.current_page === 1}
                                            className="rounded-md border border-gray-300 bg-white px-3 py-1 text-sm font-medium text-gray-700 hover:bg-gray-50 disabled:cursor-not-allowed disabled:opacity-50"
                                        >
                                            Previous
                                        </button>
                                        <span className="px-3 py-1 text-sm text-gray-700">
                                            Page {emailRecords.current_page} of {emailRecords.last_page}
                                        </span>
                                        <button
                                            onClick={() => handlePageChange(emailRecords.current_page + 1)}
                                            disabled={emailRecords.current_page === emailRecords.last_page}
                                            className="rounded-md border border-gray-300 bg-white px-3 py-1 text-sm font-medium text-gray-700 hover:bg-gray-50 disabled:cursor-not-allowed disabled:opacity-50"
                                        >
                                            Next
                                        </button>
                                    </div>
                                </div>
                            )}
                        </div>
                    </div>
                </div>
            </div>
        </AuthenticatedLayout>
    );
}
