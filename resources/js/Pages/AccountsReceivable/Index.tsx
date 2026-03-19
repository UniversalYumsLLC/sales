import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head, router } from '@inertiajs/react';
import { useState, Fragment } from 'react';
import { Menu, Transition } from '@headlessui/react';

interface Invoice {
    id: number;
    number: string | null;
    total_amount: number;
    balance: number;
    due_date: string | null;
    days_overdue: number; // positive = overdue, negative = days until due
}

interface APContact {
    name: string;
    value: string;
    type: 'portal' | 'inbox';
}

interface Customer {
    id: number;
    name: string;
    ap_contacts: APContact[];
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
    fulfilSubdomain: string;
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

function getInvoiceUrl(subdomain: string, invoiceId: number): string {
    return `https://${subdomain}.fulfil.io/v2/erp/model/account.invoice/${invoiceId}`;
}

// Email types for resend functionality
const EMAIL_TYPES = [
    { key: 'initial_invoice', label: 'Initial Invoice' },
    { key: 'initial_invoice_ap_portal', label: 'Initial Invoice (AP Portal)' },
    { key: 'invoice_modified', label: 'Invoice Modified' },
    { key: 'invoice_modified_ap_portal', label: 'Invoice Modified (AP Portal)' },
    { key: 'due_reminder', label: 'Due Reminder' },
    { key: 'overdue_notification', label: 'Overdue Notification' },
    { key: 'overdue_followup', label: 'Overdue Followup' },
] as const;

export default function Index({ customers, totals, search, lastUpdated, fulfilSubdomain }: Props) {
    const [searchTerm, setSearchTerm] = useState(search);
    const [expandedCustomer, setExpandedCustomer] = useState<number | null>(null);
    const [sendingEmail, setSendingEmail] = useState<string | null>(null); // invoiceId_emailType
    const [regeneratingPdf, setRegeneratingPdf] = useState<number | null>(null);
    const [message, setMessage] = useState<{ type: 'success' | 'error'; text: string } | null>(null);

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

    const handleRegeneratePdf = async (invoiceId: number, e: React.MouseEvent) => {
        e.stopPropagation();
        setRegeneratingPdf(invoiceId);
        setMessage(null);

        try {
            const response = await fetch(route('invoices.pdf.regenerate', { id: invoiceId }), {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '',
                },
            });

            const data = await response.json();

            if (response.ok) {
                setMessage({ type: 'success', text: 'PDF regenerated successfully' });
                // Open the newly generated PDF
                window.open(route('invoices.pdf.download', { id: invoiceId }), '_blank');
            } else {
                setMessage({ type: 'error', text: data.message || 'Failed to regenerate PDF' });
            }
        } catch (error) {
            setMessage({ type: 'error', text: 'Failed to regenerate PDF' });
        } finally {
            setRegeneratingPdf(null);
            setTimeout(() => setMessage(null), 5000);
        }
    };

    const handleResendEmail = async (invoiceId: number, emailType: string, e: React.MouseEvent) => {
        e.stopPropagation();
        const key = `${invoiceId}_${emailType}`;
        setSendingEmail(key);
        setMessage(null);

        try {
            const response = await fetch(route('invoices.resend-email', { id: invoiceId }), {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '',
                },
                body: JSON.stringify({ email_type: emailType }),
            });

            const data = await response.json();

            if (response.ok) {
                setMessage({ type: 'success', text: `Email sent successfully` });
            } else {
                setMessage({ type: 'error', text: data.message || 'Failed to send email' });
            }
        } catch (error) {
            setMessage({ type: 'error', text: 'Failed to send email' });
        } finally {
            setSendingEmail(null);
            setTimeout(() => setMessage(null), 5000);
        }
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

            {/* Notification Banner */}
            {message && (
                <div className={`mx-auto max-w-7xl px-4 sm:px-6 lg:px-8 pt-6`}>
                    <div className={`rounded-md p-4 ${
                        message.type === 'success' ? 'bg-green-50 border border-green-200' : 'bg-red-50 border border-red-200'
                    }`}>
                        <div className="flex items-center justify-between">
                            <div className="flex items-center gap-3">
                                {message.type === 'success' ? (
                                    <svg className="w-5 h-5 text-green-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                        <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
                                    </svg>
                                ) : (
                                    <svg className="w-5 h-5 text-red-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                        <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                                    </svg>
                                )}
                                <span className={`text-sm font-medium ${
                                    message.type === 'success' ? 'text-green-800' : 'text-red-800'
                                }`}>{message.text}</span>
                            </div>
                            <button
                                onClick={() => setMessage(null)}
                                className={`rounded-md p-1.5 ${
                                    message.type === 'success' ? 'text-green-500 hover:bg-green-100' : 'text-red-500 hover:bg-red-100'
                                }`}
                            >
                                <span className="sr-only">Dismiss</span>
                                <svg className="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M6 18L18 6M6 6l12 12" />
                                </svg>
                            </button>
                        </div>
                    </div>
                </div>
            )}

            <div className="py-12">
                <div className="mx-auto max-w-7xl sm:px-6 lg:px-8">
                    {/* Summary Cards */}
                    <div className="mb-6 grid grid-cols-1 gap-4 sm:grid-cols-4">
                        <div className="rounded-lg bg-white p-6 shadow-sm">
                            <div className="text-sm font-medium text-gray-500">Customers with Outstanding Invoices</div>
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
                                            <div className="font-medium text-gray-900">
                                                {customer.name}
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
                                                            <th className="pb-2 text-right text-xs font-medium uppercase text-gray-500">
                                                                Actions
                                                            </th>
                                                        </tr>
                                                    </thead>
                                                    <tbody className="divide-y divide-gray-200">
                                                        {customer.invoices.map((invoice) => {
                                                            const status = getInvoiceStatus(invoice.days_overdue);
                                                            return (
                                                                <tr key={invoice.id}>
                                                                    <td className="py-2 text-sm text-gray-900">
                                                                        {invoice.number ? (
                                                                            <a
                                                                                href={getInvoiceUrl(fulfilSubdomain, invoice.id)}
                                                                                target="_blank"
                                                                                rel="noopener noreferrer"
                                                                                className="text-indigo-600 hover:text-indigo-800 hover:underline"
                                                                                onClick={(e) => e.stopPropagation()}
                                                                            >
                                                                                {invoice.number}
                                                                            </a>
                                                                        ) : '-'}
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
                                                                    <td className="py-2 text-right text-sm">
                                                                        <div className="flex items-center justify-end gap-2">
                                                                            {/* Generate PDF Button */}
                                                                            <button
                                                                                onClick={(e) => handleRegeneratePdf(invoice.id, e)}
                                                                                disabled={regeneratingPdf === invoice.id}
                                                                                className="inline-flex items-center px-2 py-1 text-xs font-medium text-gray-700 bg-white border border-gray-300 rounded hover:bg-gray-50 disabled:opacity-50"
                                                                                title="Generate PDF"
                                                                            >
                                                                                {regeneratingPdf === invoice.id ? (
                                                                                    <svg className="w-4 h-4 animate-spin" fill="none" viewBox="0 0 24 24">
                                                                                        <circle className="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" strokeWidth="4"></circle>
                                                                                        <path className="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"></path>
                                                                                    </svg>
                                                                                ) : (
                                                                                    <svg className="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                                                                        <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M12 10v6m0 0l-3-3m3 3l3-3m2 8H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                                                                                    </svg>
                                                                                )}
                                                                            </button>

                                                                            {/* Resend Email Dropdown */}
                                                                            <Menu as="div" className="relative inline-block text-left">
                                                                                <Menu.Button
                                                                                    className="inline-flex items-center px-2 py-1 text-xs font-medium text-white bg-indigo-600 border border-transparent rounded hover:bg-indigo-700 disabled:opacity-50"
                                                                                    onClick={(e: React.MouseEvent) => e.stopPropagation()}
                                                                                >
                                                                                    <svg className="w-4 h-4 mr-1" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                                                                        <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z" />
                                                                                    </svg>
                                                                                    Resend
                                                                                    <svg className="w-3 h-3 ml-1" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                                                                        <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M19 9l-7 7-7-7" />
                                                                                    </svg>
                                                                                </Menu.Button>
                                                                                <Transition
                                                                                    as={Fragment}
                                                                                    enter="transition ease-out duration-100"
                                                                                    enterFrom="transform opacity-0 scale-95"
                                                                                    enterTo="transform opacity-100 scale-100"
                                                                                    leave="transition ease-in duration-75"
                                                                                    leaveFrom="transform opacity-100 scale-100"
                                                                                    leaveTo="transform opacity-0 scale-95"
                                                                                >
                                                                                    <Menu.Items className="absolute right-0 z-10 mt-1 w-56 origin-top-right rounded-md bg-white shadow-lg ring-1 ring-black ring-opacity-5 focus:outline-none">
                                                                                        <div className="py-1">
                                                                                            {EMAIL_TYPES.map((emailType) => (
                                                                                                <Menu.Item key={emailType.key}>
                                                                                                    {({ active }) => (
                                                                                                        <button
                                                                                                            onClick={(e) => handleResendEmail(invoice.id, emailType.key, e)}
                                                                                                            disabled={sendingEmail === `${invoice.id}_${emailType.key}`}
                                                                                                            className={`${
                                                                                                                active ? 'bg-gray-100 text-gray-900' : 'text-gray-700'
                                                                                                            } block w-full px-4 py-2 text-left text-sm disabled:opacity-50`}
                                                                                                        >
                                                                                                            {sendingEmail === `${invoice.id}_${emailType.key}` ? 'Sending...' : emailType.label}
                                                                                                        </button>
                                                                                                    )}
                                                                                                </Menu.Item>
                                                                                            ))}
                                                                                        </div>
                                                                                    </Menu.Items>
                                                                                </Transition>
                                                                            </Menu>
                                                                        </div>
                                                                    </td>
                                                                </tr>
                                                            );
                                                        })}
                                                    </tbody>
                                                </table>

                                                {/* AP Contacts Section */}
                                                {customer.ap_contacts.length > 0 && (
                                                    <div className="mt-6 pt-4 border-t border-gray-200">
                                                        <h4 className="text-xs font-medium uppercase text-gray-500 mb-3">AP Contacts</h4>
                                                        {(() => {
                                                            const portalContact = customer.ap_contacts.find(c => c.type === 'portal');
                                                            const inboxContacts = customer.ap_contacts.filter(c => c.type === 'inbox');

                                                            return (
                                                                <div className="space-y-2">
                                                                    {portalContact && (
                                                                        <div className="flex items-center gap-4 text-sm">
                                                                            <span className="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-purple-100 text-purple-800">
                                                                                Portal
                                                                            </span>
                                                                            <a
                                                                                href={portalContact.value}
                                                                                target="_blank"
                                                                                rel="noopener noreferrer"
                                                                                className="text-indigo-600 hover:text-indigo-800 hover:underline"
                                                                                onClick={(e) => e.stopPropagation()}
                                                                            >
                                                                                {portalContact.value}
                                                                            </a>
                                                                        </div>
                                                                    )}
                                                                    {inboxContacts.map((contact, idx) => (
                                                                        <div key={idx} className="flex items-center gap-4 text-sm">
                                                                            <span className="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-blue-100 text-blue-800">
                                                                                Inbox
                                                                            </span>
                                                                            <span className="text-gray-900">{contact.name}</span>
                                                                            {contact.value && (
                                                                                <a
                                                                                    href={`mailto:${contact.value}`}
                                                                                    className="text-indigo-600 hover:text-indigo-800 hover:underline"
                                                                                    onClick={(e) => e.stopPropagation()}
                                                                                >
                                                                                    {contact.value}
                                                                                </a>
                                                                            )}
                                                                        </div>
                                                                    ))}
                                                                </div>
                                                            );
                                                        })()}
                                                    </div>
                                                )}
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
