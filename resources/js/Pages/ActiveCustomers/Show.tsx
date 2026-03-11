import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head, Link, router } from '@inertiajs/react';

interface Contact {
    name: string;
    email: string | null;
}

interface Customer {
    id: number;
    name: string;
    code: string | null;
    buyers: Contact[];
    accounts_payable: Contact[];
    logistics: Contact[];
    payment_terms: string | null;
    shipping_terms: string | null;
    discount_percent: number | null;
    receivable: number | null;
    receivable_today: number | null;
}

interface MonthlyRevenue {
    month: string;
    month_name: string;
    revenue: number;
    prior_year_month: string;
    prior_year_revenue: number;
}

interface TopProduct {
    sku: string;
    name: string;
    units_sold: number;
    revenue: number;
}

interface UpcomingOrder {
    id: number;
    reference: string | null;
    sale_date: string | null;
    shipping_end_date: string | null;
    total_amount: number;
}

interface OutstandingInvoice {
    id: number;
    number: string | null;
    total_amount: number;
    balance: number;
    due_date: string | null;
    days_overdue: number;
}

interface Props {
    customer: Customer;
    monthlyRevenue: MonthlyRevenue[];
    topProducts: TopProduct[];
    upcomingOrders: UpcomingOrder[];
    outstandingInvoices: OutstandingInvoice[];
    lastUpdated: string;
}

function formatCurrency(amount: number | null): string {
    if (amount === null) return '-';
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

function getShipmentStatus(shipDateStr: string | null): { text: string; className: string } {
    if (!shipDateStr) return { text: '-', className: 'text-gray-400' };

    const today = new Date();
    today.setHours(0, 0, 0, 0);
    const shipDate = new Date(shipDateStr);
    shipDate.setHours(0, 0, 0, 0);

    const diffTime = shipDate.getTime() - today.getTime();
    const diffDays = Math.round(diffTime / (1000 * 60 * 60 * 24));

    if (diffDays > 0) {
        return { text: `Ships in ${diffDays} day${diffDays !== 1 ? 's' : ''}`, className: 'text-green-600' };
    } else if (diffDays < 0) {
        const lateDays = Math.abs(diffDays);
        return { text: `Late by ${lateDays} day${lateDays !== 1 ? 's' : ''}`, className: 'text-red-600' };
    } else {
        return { text: 'Ships today', className: 'text-green-600' };
    }
}

function formatCompactCurrency(amount: number): string {
    if (amount >= 1000000) {
        return `$${(amount / 1000000).toFixed(1)}M`;
    } else if (amount >= 1000) {
        return `$${(amount / 1000).toFixed(0)}k`;
    }
    return `$${amount}`;
}

export default function Show({
    customer,
    monthlyRevenue,
    topProducts,
    upcomingOrders,
    outstandingInvoices,
    lastUpdated,
}: Props) {
    const handleRefresh = () => {
        router.get(route('customers.show', customer.id), { refresh: true }, { preserveState: true });
    };

    const t12mTotal = monthlyRevenue.reduce((sum, m) => sum + m.revenue, 0);
    const priorYearTotal = monthlyRevenue.reduce((sum, m) => sum + m.prior_year_revenue, 0);
    const revenueChange = t12mTotal - priorYearTotal;

    return (
        <AuthenticatedLayout
            header={
                <div className="flex items-center justify-between">
                    <div className="flex items-center gap-4">
                        <Link
                            href={route('customers.index')}
                            className="text-gray-500 hover:text-gray-700"
                        >
                            &larr; Back
                        </Link>
                        <h2 className="text-xl font-semibold leading-tight text-gray-800">
                            {customer.name}
                        </h2>
                    </div>
                    <button
                        onClick={handleRefresh}
                        className="text-sm text-gray-500 hover:text-gray-700"
                    >
                        Refresh Data
                    </button>
                </div>
            }
        >
            <Head title={customer.name} />

            <div className="py-12">
                <div className="mx-auto max-w-7xl space-y-6 sm:px-6 lg:px-8">
                    {/* Customer Info */}
                    <div className="overflow-hidden bg-white shadow-sm sm:rounded-lg">
                        <div className="p-6">
                            <h3 className="mb-4 text-lg font-medium text-gray-900">Customer Details</h3>
                            <dl className="grid grid-cols-2 gap-4 sm:grid-cols-4">
                                {customer.code && (
                                    <div>
                                        <dt className="text-sm font-medium text-gray-500">Code</dt>
                                        <dd className="text-sm text-gray-900">{customer.code}</dd>
                                    </div>
                                )}
                                <div>
                                    <dt className="text-sm font-medium text-gray-500">Payment Terms</dt>
                                    <dd className="text-sm text-gray-900">{customer.payment_terms || '-'}</dd>
                                </div>
                                <div>
                                    <dt className="text-sm font-medium text-gray-500">Shipping Terms</dt>
                                    <dd className="text-sm text-gray-900">{customer.shipping_terms || '-'}</dd>
                                </div>
                                {customer.discount_percent && (
                                    <div>
                                        <dt className="text-sm font-medium text-gray-500">Discount</dt>
                                        <dd className="text-sm text-gray-900">{customer.discount_percent}%</dd>
                                    </div>
                                )}
                            </dl>
                        </div>
                    </div>

                    {/* Contacts */}
                    <div className="overflow-hidden bg-white shadow-sm sm:rounded-lg">
                        <div className="p-6">
                            <h3 className="mb-4 text-lg font-medium text-gray-900">Contacts</h3>
                            <div className="grid gap-6 sm:grid-cols-3">
                                {/* Buyers */}
                                <div>
                                    <h4 className="mb-2 text-sm font-medium text-gray-700">Buyers</h4>
                                    {customer.buyers && customer.buyers.length > 0 ? (
                                        <ul className="space-y-1">
                                            {customer.buyers.map((contact, idx) => (
                                                <li key={idx} className="text-sm text-gray-600">
                                                    {contact.name}
                                                    {contact.email && (
                                                        <span className="text-gray-400"> ({contact.email})</span>
                                                    )}
                                                </li>
                                            ))}
                                        </ul>
                                    ) : (
                                        <p className="text-sm text-gray-400">-</p>
                                    )}
                                </div>

                                {/* Accounts Payable */}
                                <div>
                                    <h4 className="mb-2 text-sm font-medium text-gray-700">Accounts Payable</h4>
                                    {customer.accounts_payable && customer.accounts_payable.length > 0 ? (
                                        <ul className="space-y-1">
                                            {customer.accounts_payable.map((contact, idx) => (
                                                <li key={idx} className="text-sm text-gray-600">
                                                    {contact.name}
                                                    {contact.email && (
                                                        <span className="text-gray-400"> ({contact.email})</span>
                                                    )}
                                                </li>
                                            ))}
                                        </ul>
                                    ) : (
                                        <p className="text-sm text-gray-400">-</p>
                                    )}
                                </div>

                                {/* Logistics */}
                                <div>
                                    <h4 className="mb-2 text-sm font-medium text-gray-700">Logistics</h4>
                                    {customer.logistics && customer.logistics.length > 0 ? (
                                        <ul className="space-y-1">
                                            {customer.logistics.map((contact, idx) => (
                                                <li key={idx} className="text-sm text-gray-600">
                                                    {contact.name}
                                                    {contact.email && (
                                                        <span className="text-gray-400"> ({contact.email})</span>
                                                    )}
                                                </li>
                                            ))}
                                        </ul>
                                    ) : (
                                        <p className="text-sm text-gray-400">-</p>
                                    )}
                                </div>
                            </div>
                        </div>
                    </div>

                    {/* Revenue Chart */}
                    <div className="overflow-hidden bg-white shadow-sm sm:rounded-lg">
                        <div className="p-6">
                            <div className="mb-4 flex items-center justify-between">
                                <h3 className="text-lg font-medium text-gray-900">Revenue</h3>
                                <div className="flex gap-6 text-right">
                                    <div>
                                        <span className="text-sm text-gray-500">T12M: </span>
                                        <span className="font-semibold text-gray-900">{formatCurrency(t12mTotal)}</span>
                                    </div>
                                    <div>
                                        <span className="text-sm text-gray-500">Prior Year: </span>
                                        <span className="font-semibold text-gray-500">{formatCurrency(priorYearTotal)}</span>
                                    </div>
                                    <div>
                                        <span className="text-sm text-gray-500">YoY: </span>
                                        <span className={`font-semibold ${revenueChange >= 0 ? 'text-green-600' : 'text-red-600'}`}>
                                            {revenueChange >= 0 ? '+ ' : '- '}{formatCurrency(Math.abs(revenueChange))}
                                        </span>
                                    </div>
                                </div>
                            </div>
                            {(() => {
                                const maxRevenue = Math.max(
                                    ...monthlyRevenue.map(m => Math.max(m.revenue, m.prior_year_revenue)),
                                    10000
                                );
                                const yAxisSteps = 4;
                                const yAxisValues = Array.from({ length: yAxisSteps + 1 }, (_, i) =>
                                    Math.round((maxRevenue / yAxisSteps) * (yAxisSteps - i))
                                );

                                return (
                                    <>
                                        {/* Legend */}
                                        <div className="mb-2 flex justify-end gap-4">
                                            <div className="flex items-center gap-1">
                                                <div className="h-3 w-3 rounded bg-indigo-500"></div>
                                                <span className="text-xs text-gray-500">Current Year</span>
                                            </div>
                                            <div className="flex items-center gap-1">
                                                <div className="h-3 w-3 rounded bg-gray-300"></div>
                                                <span className="text-xs text-gray-500">Prior Year</span>
                                            </div>
                                        </div>
                                        <div className="flex" style={{ height: '220px' }}>
                                            {/* Y-Axis */}
                                            <div className="flex flex-col justify-between pr-2 text-right" style={{ width: '60px' }}>
                                                {yAxisValues.map((value, idx) => (
                                                    <span key={idx} className="text-xs text-gray-500">
                                                        {formatCompactCurrency(value)}
                                                    </span>
                                                ))}
                                            </div>

                                            {/* Chart Area */}
                                            <div className="flex flex-1 flex-col">
                                                <div className="relative flex flex-1 items-end gap-2 border-b border-l border-gray-200">
                                                    {/* Horizontal grid lines */}
                                                    {yAxisValues.slice(1, -1).map((_, idx) => (
                                                        <div
                                                            key={idx}
                                                            className="absolute left-0 right-0 border-t border-gray-100"
                                                            style={{ bottom: `${((idx + 1) / yAxisSteps) * 100}%` }}
                                                        />
                                                    ))}

                                                    {/* Bars - grouped by month */}
                                                    {monthlyRevenue.map((month, idx) => {
                                                        const currentHeightPercent = (month.revenue / maxRevenue) * 100;
                                                        const priorHeightPercent = (month.prior_year_revenue / maxRevenue) * 100;
                                                        return (
                                                            <div key={idx} className="relative z-10 flex flex-1 items-end justify-center gap-0.5 h-full">
                                                                {/* Prior Year Bar */}
                                                                <div
                                                                    className="w-2/5 bg-gray-300 rounded-t"
                                                                    style={{ height: `${priorHeightPercent}%`, minHeight: month.prior_year_revenue > 0 ? '4px' : '0' }}
                                                                    title={`${month.prior_year_month}: ${formatCurrency(month.prior_year_revenue)}`}
                                                                />
                                                                {/* Current Year Bar */}
                                                                <div
                                                                    className="w-2/5 bg-indigo-500 rounded-t"
                                                                    style={{ height: `${currentHeightPercent}%`, minHeight: month.revenue > 0 ? '4px' : '0' }}
                                                                    title={`${month.month}: ${formatCurrency(month.revenue)}`}
                                                                />
                                                            </div>
                                                        );
                                                    })}
                                                </div>

                                                {/* X-Axis Labels */}
                                                <div className="flex gap-2 pt-1">
                                                    {monthlyRevenue.map((month, idx) => (
                                                        <div key={idx} className="flex-1 text-center">
                                                            <span className="text-xs text-gray-500">{month.month_name}</span>
                                                        </div>
                                                    ))}
                                                </div>
                                            </div>
                                        </div>
                                    </>
                                );
                            })()}
                        </div>
                    </div>

                    <div className="grid gap-6 lg:grid-cols-2">
                        {/* Top Products */}
                        <div className="overflow-hidden bg-white shadow-sm sm:rounded-lg">
                            <div className="p-6">
                                <h3 className="mb-4 text-lg font-medium text-gray-900">Top Products (T12M)</h3>
                                {topProducts.length > 0 ? (
                                    <table className="min-w-full divide-y divide-gray-200">
                                        <thead>
                                            <tr>
                                                <th className="pb-2 text-left text-xs font-medium uppercase text-gray-500">SKU</th>
                                                <th className="pb-2 text-right text-xs font-medium uppercase text-gray-500">Units</th>
                                                <th className="pb-2 text-right text-xs font-medium uppercase text-gray-500">Revenue</th>
                                            </tr>
                                        </thead>
                                        <tbody className="divide-y divide-gray-200">
                                            {topProducts.map((product, idx) => (
                                                <tr key={idx}>
                                                    <td className="py-2 text-sm text-gray-900">{product.sku}</td>
                                                    <td className="py-2 text-right text-sm text-gray-500">{product.units_sold}</td>
                                                    <td className="py-2 text-right text-sm text-gray-900">{formatCurrency(product.revenue)}</td>
                                                </tr>
                                            ))}
                                        </tbody>
                                    </table>
                                ) : (
                                    <p className="text-sm text-gray-500">No product data available</p>
                                )}
                            </div>
                        </div>

                        {/* Upcoming Orders */}
                        <div className="overflow-hidden bg-white shadow-sm sm:rounded-lg">
                            <div className="p-6">
                                <div className="mb-4 flex items-center justify-between">
                                    <h3 className="text-lg font-medium text-gray-900">Upcoming Orders</h3>
                                    {upcomingOrders.length > 0 && (
                                        <div className="text-right">
                                            <span className="text-sm text-gray-500">Total: </span>
                                            <span className="font-semibold text-gray-900">
                                                {formatCurrency(upcomingOrders.reduce((sum, o) => sum + o.total_amount, 0))}
                                            </span>
                                        </div>
                                    )}
                                </div>
                                {upcomingOrders.length > 0 ? (
                                    <table className="min-w-full divide-y divide-gray-200">
                                        <thead>
                                            <tr>
                                                <th className="pb-2 text-left text-xs font-medium uppercase text-gray-500">PO #</th>
                                                <th className="pb-2 text-left text-xs font-medium uppercase text-gray-500">Ship Date</th>
                                                <th className="pb-2 text-right text-xs font-medium uppercase text-gray-500">Amount</th>
                                                <th className="pb-2 text-right text-xs font-medium uppercase text-gray-500">Status</th>
                                            </tr>
                                        </thead>
                                        <tbody className="divide-y divide-gray-200">
                                            {upcomingOrders.map((order) => {
                                                const shipStatus = getShipmentStatus(order.shipping_end_date);
                                                return (
                                                    <tr key={order.id}>
                                                        <td className="py-2 text-sm text-gray-900">{order.reference || '-'}</td>
                                                        <td className="py-2 text-sm text-gray-500">{formatDate(order.shipping_end_date)}</td>
                                                        <td className="py-2 text-right text-sm text-gray-900">{formatCurrency(order.total_amount)}</td>
                                                        <td className={`py-2 text-right text-sm ${shipStatus.className}`}>
                                                            {shipStatus.text}
                                                        </td>
                                                    </tr>
                                                );
                                            })}
                                        </tbody>
                                    </table>
                                ) : (
                                    <p className="text-sm text-gray-500">No upcoming orders</p>
                                )}
                            </div>
                        </div>
                    </div>

                    {/* Outstanding Invoices */}
                    <div className="overflow-hidden bg-white shadow-sm sm:rounded-lg">
                        <div className="p-6">
                            <div className="mb-4 flex items-center justify-between">
                                <h3 className="text-lg font-medium text-gray-900">Outstanding Invoices</h3>
                                {outstandingInvoices.length > 0 && (
                                    <div className="flex gap-6 text-right">
                                        <div>
                                            <span className="text-sm text-gray-500">Total Outstanding: </span>
                                            <span className="font-semibold text-gray-900">
                                                {formatCurrency(outstandingInvoices.reduce((sum, i) => sum + i.balance, 0))}
                                            </span>
                                        </div>
                                        <div>
                                            <span className="text-sm text-gray-500">Overdue: </span>
                                            <span className="font-semibold text-orange-600">
                                                {formatCurrency(outstandingInvoices.filter(i => i.days_overdue > 0).reduce((sum, i) => sum + i.balance, 0))}
                                            </span>
                                        </div>
                                        <div>
                                            <span className="text-sm text-gray-500">30+ Days: </span>
                                            <span className="font-semibold text-red-600">
                                                {formatCurrency(outstandingInvoices.filter(i => i.days_overdue > 30).reduce((sum, i) => sum + i.balance, 0))}
                                            </span>
                                        </div>
                                    </div>
                                )}
                            </div>
                            {outstandingInvoices.length > 0 ? (
                                <table className="min-w-full divide-y divide-gray-200">
                                    <thead>
                                        <tr>
                                            <th className="pb-2 text-left text-xs font-medium uppercase text-gray-500">Invoice #</th>
                                            <th className="pb-2 text-left text-xs font-medium uppercase text-gray-500">Due Date</th>
                                            <th className="pb-2 text-right text-xs font-medium uppercase text-gray-500">Total</th>
                                            <th className="pb-2 text-right text-xs font-medium uppercase text-gray-500">Balance</th>
                                            <th className="pb-2 text-right text-xs font-medium uppercase text-gray-500">Status</th>
                                        </tr>
                                    </thead>
                                    <tbody className="divide-y divide-gray-200">
                                        {outstandingInvoices.map((invoice) => {
                                            const status = getInvoiceStatus(invoice.days_overdue);
                                            return (
                                                <tr key={invoice.id}>
                                                    <td className="py-2 text-sm text-gray-900">{invoice.number || '-'}</td>
                                                    <td className="py-2 text-sm text-gray-500">{formatDate(invoice.due_date)}</td>
                                                    <td className="py-2 text-right text-sm text-gray-500">{formatCurrency(invoice.total_amount)}</td>
                                                    <td className="py-2 text-right text-sm text-gray-900">{formatCurrency(invoice.balance)}</td>
                                                    <td className={`py-2 text-right text-sm ${status.className}`}>
                                                        {status.text}
                                                    </td>
                                                </tr>
                                            );
                                        })}
                                    </tbody>
                                </table>
                            ) : (
                                <p className="text-sm text-gray-500">No outstanding invoices</p>
                            )}
                        </div>
                    </div>

                    {/* Last updated */}
                    <div className="text-right text-xs text-gray-400">
                        Last updated: {new Date(lastUpdated).toLocaleString()}
                    </div>
                </div>
            </div>
        </AuthenticatedLayout>
    );
}
