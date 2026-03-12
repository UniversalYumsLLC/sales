import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head, Link, router } from '@inertiajs/react';
import { useState, useEffect } from 'react';

interface Contact {
    name: string;
    email: string;
}

interface BuyerContact {
    name: string;
    email: string;
    last_emailed_at: string | null;
    last_received_at: string | null;
}

interface APContact {
    name: string;
    value: string;
}

interface Customer {
    id: number;
    name: string;
    code: string | null;
    buyers: Contact[];
    accounts_payable: APContact[];
    logistics: Contact[];
    payment_terms: string | null;
    shipping_terms: string | null;
    discount_percent: number | null;
    shelf_life_requirement: string | null;
    vendor_guide: string | null;
    receivable: number | null;
    receivable_today: number | null;
    company_urls: string[];
}

interface PriceList {
    id: number;
    name: string;
    discount_percent: number;
}

interface PaymentTerm {
    id: number;
    name: string;
    days: number;
}

interface ShippingTerm {
    id: number;
    name: string;
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
    buyerContacts: BuyerContact[];
    monthlyRevenue: MonthlyRevenue[];
    topProducts: TopProduct[];
    upcomingOrders: UpcomingOrder[];
    outstandingInvoices: OutstandingInvoice[];
    lastUpdated: string;
    priceLists: PriceList[];
    paymentTerms: PaymentTerm[];
    shippingTerms: ShippingTerm[];
}

interface CustomerDetailsForm {
    name: string;
    sale_price_list: string;
    customer_payment_term: string;
    shipping_terms_category_id: string;
    shelf_life_requirement: string;
    vendor_guide: string;
}

interface ContactsForm {
    buyers: Contact[];
    accounts_payable: APContact[];
    logistics: Contact[];
}

interface ValidationErrors {
    [key: string]: string;
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

// Pencil icon component
function PencilIcon({ className = "h-4 w-4" }: { className?: string }) {
    return (
        <svg className={className} fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M15.232 5.232l3.536 3.536m-2.036-5.036a2.5 2.5 0 113.536 3.536L6.5 21.036H3v-3.572L16.732 3.732z" />
        </svg>
    );
}

// X icon component
function XIcon({ className = "h-4 w-4" }: { className?: string }) {
    return (
        <svg className={className} fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M6 18L18 6M6 6l12 12" />
        </svg>
    );
}

// Plus icon component
function PlusIcon({ className = "h-4 w-4" }: { className?: string }) {
    return (
        <svg className={className} fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M12 4v16m8-8H4" />
        </svg>
    );
}

export default function Show({
    customer,
    buyerContacts,
    monthlyRevenue,
    topProducts,
    upcomingOrders,
    outstandingInvoices,
    lastUpdated,
    priceLists,
    paymentTerms,
    shippingTerms,
}: Props) {
    // Edit mode states
    const [editingDetails, setEditingDetails] = useState(false);
    const [editingContacts, setEditingContacts] = useState(false);
    const [editingCompanyUrls, setEditingCompanyUrls] = useState(false);
    const [saving, setSaving] = useState(false);

    // Company URLs form state
    const [companyUrlsForm, setCompanyUrlsForm] = useState<string[]>(
        customer.company_urls?.length > 0 ? [...customer.company_urls] : ['']
    );

    // Form states
    const [detailsForm, setDetailsForm] = useState<CustomerDetailsForm>({
        name: customer.name,
        sale_price_list: priceLists.find(pl => pl.discount_percent === customer.discount_percent)?.id.toString() || '',
        customer_payment_term: paymentTerms.find(pt => pt.name === customer.payment_terms)?.id.toString() || '',
        shipping_terms_category_id: shippingTerms.find(st => st.name === customer.shipping_terms)?.id.toString() || '',
        shelf_life_requirement: customer.shelf_life_requirement || '',
        vendor_guide: customer.vendor_guide || '',
    });

    const [contactsForm, setContactsForm] = useState<ContactsForm>({
        buyers: customer.buyers?.length > 0 ? [...customer.buyers] : [{ name: '', email: '' }],
        accounts_payable: customer.accounts_payable?.length > 0 ? [...customer.accounts_payable] : [],
        logistics: customer.logistics?.length > 0 ? [...customer.logistics] : [],
    });

    // Validation errors
    const [detailsErrors, setDetailsErrors] = useState<ValidationErrors>({});
    const [contactsErrors, setContactsErrors] = useState<ValidationErrors>({});

    // Validate details form
    useEffect(() => {
        if (!editingDetails) return;

        const errors: ValidationErrors = {};

        if (!detailsForm.name || detailsForm.name.length < 2) {
            errors.name = 'Company name must be at least 2 characters';
        }
        if (!detailsForm.sale_price_list) {
            errors.sale_price_list = 'Please select a discount level';
        }
        if (!detailsForm.customer_payment_term) {
            errors.customer_payment_term = 'Please select payment terms';
        }
        if (!detailsForm.shipping_terms_category_id) {
            errors.shipping_terms_category_id = 'Please select shipping terms';
        }
        if (!detailsForm.shelf_life_requirement) {
            errors.shelf_life_requirement = 'Shelf life requirement is required';
        } else {
            const days = parseInt(detailsForm.shelf_life_requirement);
            if (isNaN(days) || days < 30) {
                errors.shelf_life_requirement = 'Must be at least 30 days';
            } else if (days > 365) {
                errors.shelf_life_requirement = 'Cannot exceed 365 days';
            }
        }
        if (detailsForm.vendor_guide && !isValidUrl(detailsForm.vendor_guide)) {
            errors.vendor_guide = 'Must be a valid URL';
        }

        setDetailsErrors(errors);
    }, [detailsForm, editingDetails]);

    // Validate contacts form
    useEffect(() => {
        if (!editingContacts) return;

        const errors: ValidationErrors = {};

        // Validate buyers (at least one required)
        if (contactsForm.buyers.length === 0) {
            errors.buyers = 'At least one buyer contact is required';
        } else {
            contactsForm.buyers.forEach((buyer, idx) => {
                if (!buyer.name || buyer.name.length < 2) {
                    errors[`buyers.${idx}.name`] = 'Name is required';
                }
                if (!buyer.email || !isValidEmail(buyer.email)) {
                    errors[`buyers.${idx}.email`] = 'Valid email is required';
                }
            });
        }

        // Validate AP (optional, but if provided must be valid)
        contactsForm.accounts_payable.forEach((ap, idx) => {
            if (!ap.name || ap.name.length < 2) {
                errors[`accounts_payable.${idx}.name`] = 'Name is required';
            }
            if (!ap.value || (!isValidEmail(ap.value) && !isValidUrl(ap.value))) {
                errors[`accounts_payable.${idx}.value`] = 'Valid email or URL is required';
            }
        });

        // Validate logistics (optional, but if provided must be valid)
        contactsForm.logistics.forEach((logistics, idx) => {
            if (!logistics.name || logistics.name.length < 2) {
                errors[`logistics.${idx}.name`] = 'Name is required';
            }
            if (!logistics.email || !isValidEmail(logistics.email)) {
                errors[`logistics.${idx}.email`] = 'Valid email is required';
            }
        });

        setContactsErrors(errors);
    }, [contactsForm, editingContacts]);

    function isValidEmail(email: string): boolean {
        return /^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(email);
    }

    function isValidUrl(url: string): boolean {
        try {
            new URL(url);
            return url.startsWith('http://') || url.startsWith('https://');
        } catch {
            return false;
        }
    }

    const handleRefresh = () => {
        router.get(route('customers.show', customer.id), { refresh: true }, { preserveState: true });
    };

    const cancelDetailsEdit = () => {
        setDetailsForm({
            name: customer.name,
            sale_price_list: priceLists.find(pl => pl.discount_percent === customer.discount_percent)?.id.toString() || '',
            customer_payment_term: paymentTerms.find(pt => pt.name === customer.payment_terms)?.id.toString() || '',
            shipping_terms_category_id: shippingTerms.find(st => st.name === customer.shipping_terms)?.id.toString() || '',
            shelf_life_requirement: customer.shelf_life_requirement || '',
            vendor_guide: customer.vendor_guide || '',
        });
        setEditingDetails(false);
        setDetailsErrors({});
    };

    const cancelContactsEdit = () => {
        setContactsForm({
            buyers: customer.buyers?.length > 0 ? [...customer.buyers] : [{ name: '', email: '' }],
            accounts_payable: customer.accounts_payable?.length > 0 ? [...customer.accounts_payable] : [],
            logistics: customer.logistics?.length > 0 ? [...customer.logistics] : [],
        });
        setEditingContacts(false);
        setContactsErrors({});
    };

    const saveDetails = async () => {
        if (Object.keys(detailsErrors).length > 0) return;

        setSaving(true);
        try {
            const response = await fetch(route('customers.update', customer.id), {
                method: 'PUT',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '',
                },
                body: JSON.stringify({
                    name: detailsForm.name,
                    sale_price_list: parseInt(detailsForm.sale_price_list),
                    customer_payment_term: parseInt(detailsForm.customer_payment_term),
                    shipping_terms_category_id: parseInt(detailsForm.shipping_terms_category_id),
                    shelf_life_requirement: parseInt(detailsForm.shelf_life_requirement),
                    vendor_guide: detailsForm.vendor_guide || null,
                }),
            });

            if (response.ok) {
                setEditingDetails(false);
                router.reload({ only: ['customer'] });
            } else {
                const data = await response.json();
                alert(data.message || 'Failed to save changes');
            }
        } catch (error) {
            alert('Failed to save changes');
        } finally {
            setSaving(false);
        }
    };

    const saveContacts = async () => {
        if (Object.keys(contactsErrors).length > 0) return;

        setSaving(true);
        try {
            const response = await fetch(route('customers.update', customer.id), {
                method: 'PUT',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '',
                },
                body: JSON.stringify({
                    buyers: contactsForm.buyers,
                    accounts_payable: contactsForm.accounts_payable,
                    logistics: contactsForm.logistics,
                }),
            });

            if (response.ok) {
                setEditingContacts(false);
                router.reload({ only: ['customer'] });
            } else {
                const data = await response.json();
                alert(data.message || 'Failed to save changes');
            }
        } catch (error) {
            alert('Failed to save changes');
        } finally {
            setSaving(false);
        }
    };

    // Contact management functions
    const addBuyer = () => {
        setContactsForm(prev => ({
            ...prev,
            buyers: [...prev.buyers, { name: '', email: '' }],
        }));
    };

    const removeBuyer = (index: number) => {
        if (contactsForm.buyers.length > 1) {
            setContactsForm(prev => ({
                ...prev,
                buyers: prev.buyers.filter((_, i) => i !== index),
            }));
        }
    };

    const updateBuyer = (index: number, field: 'name' | 'email', value: string) => {
        setContactsForm(prev => ({
            ...prev,
            buyers: prev.buyers.map((b, i) => i === index ? { ...b, [field]: value } : b),
        }));
    };

    const addAP = () => {
        setContactsForm(prev => ({
            ...prev,
            accounts_payable: [...prev.accounts_payable, { name: '', value: '' }],
        }));
    };

    const removeAP = (index: number) => {
        setContactsForm(prev => ({
            ...prev,
            accounts_payable: prev.accounts_payable.filter((_, i) => i !== index),
        }));
    };

    const updateAP = (index: number, field: 'name' | 'value', value: string) => {
        setContactsForm(prev => ({
            ...prev,
            accounts_payable: prev.accounts_payable.map((ap, i) => i === index ? { ...ap, [field]: value } : ap),
        }));
    };

    const addLogistics = () => {
        setContactsForm(prev => ({
            ...prev,
            logistics: [...prev.logistics, { name: '', email: '' }],
        }));
    };

    const removeLogistics = (index: number) => {
        setContactsForm(prev => ({
            ...prev,
            logistics: prev.logistics.filter((_, i) => i !== index),
        }));
    };

    const updateLogistics = (index: number, field: 'name' | 'email', value: string) => {
        setContactsForm(prev => ({
            ...prev,
            logistics: prev.logistics.map((l, i) => i === index ? { ...l, [field]: value } : l),
        }));
    };

    // Company URL management
    const addCompanyUrl = () => {
        setCompanyUrlsForm(prev => [...prev, '']);
    };

    const removeCompanyUrl = (index: number) => {
        if (companyUrlsForm.length > 1) {
            setCompanyUrlsForm(prev => prev.filter((_, i) => i !== index));
        } else {
            setCompanyUrlsForm(['']);
        }
    };

    const updateCompanyUrl = (index: number, value: string) => {
        setCompanyUrlsForm(prev => prev.map((url, i) => i === index ? value : url));
    };

    const cancelCompanyUrlsEdit = () => {
        setCompanyUrlsForm(customer.company_urls?.length > 0 ? [...customer.company_urls] : ['']);
        setEditingCompanyUrls(false);
    };

    const saveCompanyUrls = async () => {
        setSaving(true);
        try {
            const response = await fetch(route('customers.update-company-urls', customer.id), {
                method: 'PUT',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '',
                },
                body: JSON.stringify({
                    company_urls: companyUrlsForm.filter(url => url.trim() !== ''),
                }),
            });

            if (response.ok) {
                setEditingCompanyUrls(false);
                router.reload({ only: ['customer'] });
            } else {
                const data = await response.json();
                alert(data.message || 'Failed to save changes');
            }
        } catch (error) {
            alert('Failed to save changes');
        } finally {
            setSaving(false);
        }
    };

    // Format relative date for email tracking
    const formatRelativeDate = (dateStr: string | null): string => {
        if (!dateStr) return 'Never';
        const date = new Date(dateStr);
        const now = new Date();
        const diffMs = now.getTime() - date.getTime();
        const diffDays = Math.floor(diffMs / (1000 * 60 * 60 * 24));

        if (diffDays === 0) return 'Today';
        if (diffDays === 1) return 'Yesterday';
        if (diffDays < 7) return `${diffDays} days ago`;
        if (diffDays < 30) return `${Math.floor(diffDays / 7)} weeks ago`;
        if (diffDays < 365) return `${Math.floor(diffDays / 30)} months ago`;
        return `${Math.floor(diffDays / 365)} years ago`;
    };

    const t12mTotal = monthlyRevenue.reduce((sum, m) => sum + m.revenue, 0);
    const priorYearTotal = monthlyRevenue.reduce((sum, m) => sum + m.prior_year_revenue, 0);
    const revenueChange = t12mTotal - priorYearTotal;

    const detailsValid = Object.keys(detailsErrors).length === 0;
    const contactsValid = Object.keys(contactsErrors).length === 0;

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
                    {/* Customer Details */}
                    <div className="overflow-hidden bg-white shadow-sm sm:rounded-lg">
                        <div className="p-6">
                            <div className="mb-4 flex items-center justify-between">
                                <h3 className="text-lg font-medium text-gray-900">Customer Details</h3>
                                {!editingDetails ? (
                                    <button
                                        onClick={() => setEditingDetails(true)}
                                        className="text-gray-400 hover:text-gray-600"
                                        title="Edit"
                                    >
                                        <PencilIcon />
                                    </button>
                                ) : (
                                    <div className="flex gap-2">
                                        <button
                                            onClick={cancelDetailsEdit}
                                            className="rounded border border-gray-300 px-3 py-1 text-sm text-gray-600 hover:bg-gray-50"
                                            disabled={saving}
                                        >
                                            Cancel
                                        </button>
                                        <button
                                            onClick={saveDetails}
                                            disabled={!detailsValid || saving}
                                            className="rounded bg-indigo-600 px-3 py-1 text-sm text-white hover:bg-indigo-700 disabled:opacity-50"
                                        >
                                            {saving ? 'Saving...' : 'Save'}
                                        </button>
                                    </div>
                                )}
                            </div>

                            {!editingDetails ? (
                                <dl className="grid grid-cols-2 gap-4 sm:grid-cols-4">
                                    <div>
                                        <dt className="text-sm font-medium text-gray-500">Company Name</dt>
                                        <dd className="text-sm text-gray-900">{customer.name}</dd>
                                    </div>
                                    <div>
                                        <dt className="text-sm font-medium text-gray-500">Discount</dt>
                                        <dd className="text-sm text-gray-900">
                                            {customer.discount_percent !== null ? `${customer.discount_percent}%` : '-'}
                                        </dd>
                                    </div>
                                    <div>
                                        <dt className="text-sm font-medium text-gray-500">Payment Terms</dt>
                                        <dd className="text-sm text-gray-900">{customer.payment_terms || '-'}</dd>
                                    </div>
                                    <div>
                                        <dt className="text-sm font-medium text-gray-500">Shipping Terms</dt>
                                        <dd className="text-sm text-gray-900">{customer.shipping_terms || '-'}</dd>
                                    </div>
                                    <div>
                                        <dt className="text-sm font-medium text-gray-500">Shelf Life Requirement</dt>
                                        <dd className="text-sm text-gray-900">
                                            {customer.shelf_life_requirement ? `${customer.shelf_life_requirement} days` : '-'}
                                        </dd>
                                    </div>
                                    <div className="sm:col-span-2">
                                        <dt className="text-sm font-medium text-gray-500">Vendor Guide</dt>
                                        <dd className="text-sm text-gray-900">
                                            {customer.vendor_guide ? (
                                                <a href={customer.vendor_guide} target="_blank" rel="noopener noreferrer" className="text-indigo-600 hover:text-indigo-800">
                                                    {customer.vendor_guide}
                                                </a>
                                            ) : '-'}
                                        </dd>
                                    </div>
                                </dl>
                            ) : (
                                <div className="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-3">
                                    <div>
                                        <label className="block text-sm font-medium text-gray-700">Company Name</label>
                                        <input
                                            type="text"
                                            value={detailsForm.name}
                                            onChange={(e) => setDetailsForm(prev => ({ ...prev, name: e.target.value }))}
                                            className={`mt-1 block w-full rounded-md shadow-sm focus:ring-indigo-500 ${detailsErrors.name ? 'border-red-300 focus:border-red-500' : 'border-gray-300 focus:border-indigo-500'}`}
                                        />
                                        {detailsErrors.name && <p className="mt-1 text-xs text-red-600">{detailsErrors.name}</p>}
                                    </div>

                                    <div>
                                        <label className="block text-sm font-medium text-gray-700">Discount Level</label>
                                        <select
                                            value={detailsForm.sale_price_list}
                                            onChange={(e) => setDetailsForm(prev => ({ ...prev, sale_price_list: e.target.value }))}
                                            className={`mt-1 block w-full rounded-md shadow-sm focus:ring-indigo-500 ${detailsErrors.sale_price_list ? 'border-red-300 focus:border-red-500' : 'border-gray-300 focus:border-indigo-500'}`}
                                        >
                                            <option value="">Select...</option>
                                            {priceLists.map((pl) => (
                                                <option key={pl.id} value={pl.id}>{pl.discount_percent}% Discount</option>
                                            ))}
                                        </select>
                                        {detailsErrors.sale_price_list && <p className="mt-1 text-xs text-red-600">{detailsErrors.sale_price_list}</p>}
                                    </div>

                                    <div>
                                        <label className="block text-sm font-medium text-gray-700">Payment Terms</label>
                                        <select
                                            value={detailsForm.customer_payment_term}
                                            onChange={(e) => setDetailsForm(prev => ({ ...prev, customer_payment_term: e.target.value }))}
                                            className={`mt-1 block w-full rounded-md shadow-sm focus:ring-indigo-500 ${detailsErrors.customer_payment_term ? 'border-red-300 focus:border-red-500' : 'border-gray-300 focus:border-indigo-500'}`}
                                        >
                                            <option value="">Select...</option>
                                            {paymentTerms.map((pt) => (
                                                <option key={pt.id} value={pt.id}>{pt.name}</option>
                                            ))}
                                        </select>
                                        {detailsErrors.customer_payment_term && <p className="mt-1 text-xs text-red-600">{detailsErrors.customer_payment_term}</p>}
                                    </div>

                                    <div>
                                        <label className="block text-sm font-medium text-gray-700">Shipping Terms</label>
                                        <select
                                            value={detailsForm.shipping_terms_category_id}
                                            onChange={(e) => setDetailsForm(prev => ({ ...prev, shipping_terms_category_id: e.target.value }))}
                                            className={`mt-1 block w-full rounded-md shadow-sm focus:ring-indigo-500 ${detailsErrors.shipping_terms_category_id ? 'border-red-300 focus:border-red-500' : 'border-gray-300 focus:border-indigo-500'}`}
                                        >
                                            <option value="">Select...</option>
                                            {shippingTerms.map((st) => (
                                                <option key={st.id} value={st.id}>{st.name}</option>
                                            ))}
                                        </select>
                                        {detailsErrors.shipping_terms_category_id && <p className="mt-1 text-xs text-red-600">{detailsErrors.shipping_terms_category_id}</p>}
                                    </div>

                                    <div>
                                        <label className="block text-sm font-medium text-gray-700">Shelf Life Requirement (days)</label>
                                        <input
                                            type="number"
                                            value={detailsForm.shelf_life_requirement}
                                            onChange={(e) => setDetailsForm(prev => ({ ...prev, shelf_life_requirement: e.target.value }))}
                                            min="30"
                                            max="365"
                                            className={`mt-1 block w-full rounded-md shadow-sm focus:ring-indigo-500 ${detailsErrors.shelf_life_requirement ? 'border-red-300 focus:border-red-500' : 'border-gray-300 focus:border-indigo-500'}`}
                                        />
                                        {detailsErrors.shelf_life_requirement && <p className="mt-1 text-xs text-red-600">{detailsErrors.shelf_life_requirement}</p>}
                                    </div>

                                    <div className="sm:col-span-2 lg:col-span-1">
                                        <label className="block text-sm font-medium text-gray-700">Vendor Guide URL</label>
                                        <input
                                            type="url"
                                            value={detailsForm.vendor_guide}
                                            onChange={(e) => setDetailsForm(prev => ({ ...prev, vendor_guide: e.target.value }))}
                                            placeholder="https://..."
                                            className={`mt-1 block w-full rounded-md shadow-sm focus:ring-indigo-500 ${detailsErrors.vendor_guide ? 'border-red-300 focus:border-red-500' : 'border-gray-300 focus:border-indigo-500'}`}
                                        />
                                        {detailsErrors.vendor_guide && <p className="mt-1 text-xs text-red-600">{detailsErrors.vendor_guide}</p>}
                                    </div>
                                </div>
                            )}
                        </div>
                    </div>

                    {/* Contacts */}
                    <div className="overflow-hidden bg-white shadow-sm sm:rounded-lg">
                        <div className="p-6">
                            <div className="mb-4 flex items-center justify-between">
                                <h3 className="text-lg font-medium text-gray-900">Contacts</h3>
                                {!editingContacts ? (
                                    <button
                                        onClick={() => setEditingContacts(true)}
                                        className="text-gray-400 hover:text-gray-600"
                                        title="Edit"
                                    >
                                        <PencilIcon />
                                    </button>
                                ) : (
                                    <div className="flex gap-2">
                                        <button
                                            onClick={cancelContactsEdit}
                                            className="rounded border border-gray-300 px-3 py-1 text-sm text-gray-600 hover:bg-gray-50"
                                            disabled={saving}
                                        >
                                            Cancel
                                        </button>
                                        <button
                                            onClick={saveContacts}
                                            disabled={!contactsValid || saving}
                                            className="rounded bg-indigo-600 px-3 py-1 text-sm text-white hover:bg-indigo-700 disabled:opacity-50"
                                        >
                                            {saving ? 'Saving...' : 'Save'}
                                        </button>
                                    </div>
                                )}
                            </div>

                            {!editingContacts ? (
                                <div className="grid gap-6 sm:grid-cols-3">
                                    {/* Buyers */}
                                    <div>
                                        <h4 className="mb-2 text-sm font-medium text-gray-700">Buyers</h4>
                                        {buyerContacts && buyerContacts.length > 0 ? (
                                            <ul className="space-y-3">
                                                {buyerContacts.map((contact, idx) => (
                                                    <li key={idx} className="text-sm">
                                                        <div className="text-gray-900">{contact.name}</div>
                                                        {contact.email && (
                                                            <div className="text-gray-500">{contact.email}</div>
                                                        )}
                                                        <div className="mt-1 flex gap-3 text-xs text-gray-400">
                                                            <span title={contact.last_emailed_at ? new Date(contact.last_emailed_at).toLocaleString() : undefined}>
                                                                Sent: {formatRelativeDate(contact.last_emailed_at)}
                                                            </span>
                                                            <span title={contact.last_received_at ? new Date(contact.last_received_at).toLocaleString() : undefined}>
                                                                Received: {formatRelativeDate(contact.last_received_at)}
                                                            </span>
                                                        </div>
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
                                            <ul className="space-y-2">
                                                {customer.accounts_payable.map((contact, idx) => (
                                                    <li key={idx} className="text-sm">
                                                        <div className="text-gray-900">{contact.name}</div>
                                                        {contact.value && (
                                                            <div className="text-gray-500 break-all">
                                                                {contact.value.startsWith('http') ? (
                                                                    <a href={contact.value} target="_blank" rel="noopener noreferrer" className="text-indigo-600 hover:text-indigo-800">
                                                                        {contact.value}
                                                                    </a>
                                                                ) : contact.value}
                                                            </div>
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
                                            <ul className="space-y-2">
                                                {customer.logistics.map((contact, idx) => (
                                                    <li key={idx} className="text-sm">
                                                        <div className="text-gray-900">{contact.name}</div>
                                                        {contact.email && (
                                                            <div className="text-gray-500">{contact.email}</div>
                                                        )}
                                                    </li>
                                                ))}
                                            </ul>
                                        ) : (
                                            <p className="text-sm text-gray-400">-</p>
                                        )}
                                    </div>
                                </div>
                            ) : (
                                <div className="space-y-6">
                                    {/* Buyers Edit */}
                                    <div>
                                        <div className="mb-2 flex items-center justify-between">
                                            <h4 className="text-sm font-medium text-gray-700">Buyers</h4>
                                            <button
                                                type="button"
                                                onClick={addBuyer}
                                                className="flex items-center gap-1 text-sm text-indigo-600 hover:text-indigo-800"
                                            >
                                                <PlusIcon className="h-3 w-3" /> Add
                                            </button>
                                        </div>
                                        {contactsErrors.buyers && <p className="mb-2 text-xs text-red-600">{contactsErrors.buyers}</p>}
                                        <div className="space-y-2">
                                            {contactsForm.buyers.map((buyer, idx) => (
                                                <div key={idx} className="flex gap-2">
                                                    <div className="flex-1">
                                                        <input
                                                            type="text"
                                                            value={buyer.name}
                                                            onChange={(e) => updateBuyer(idx, 'name', e.target.value)}
                                                            placeholder="Name"
                                                            className={`block w-full rounded-md text-sm shadow-sm focus:ring-indigo-500 ${contactsErrors[`buyers.${idx}.name`] ? 'border-red-300' : 'border-gray-300'}`}
                                                        />
                                                    </div>
                                                    <div className="flex-1">
                                                        <input
                                                            type="email"
                                                            value={buyer.email}
                                                            onChange={(e) => updateBuyer(idx, 'email', e.target.value)}
                                                            placeholder="Email"
                                                            className={`block w-full rounded-md text-sm shadow-sm focus:ring-indigo-500 ${contactsErrors[`buyers.${idx}.email`] ? 'border-red-300' : 'border-gray-300'}`}
                                                        />
                                                    </div>
                                                    {contactsForm.buyers.length > 1 && (
                                                        <button
                                                            type="button"
                                                            onClick={() => removeBuyer(idx)}
                                                            className="text-red-400 hover:text-red-600"
                                                        >
                                                            <XIcon />
                                                        </button>
                                                    )}
                                                </div>
                                            ))}
                                        </div>
                                    </div>

                                    {/* AP Edit */}
                                    <div>
                                        <div className="mb-2 flex items-center justify-between">
                                            <h4 className="text-sm font-medium text-gray-700">Accounts Payable</h4>
                                            <button
                                                type="button"
                                                onClick={addAP}
                                                className="flex items-center gap-1 text-sm text-indigo-600 hover:text-indigo-800"
                                            >
                                                <PlusIcon className="h-3 w-3" /> Add
                                            </button>
                                        </div>
                                        {contactsForm.accounts_payable.length === 0 ? (
                                            <p className="text-sm text-gray-400">No AP contacts</p>
                                        ) : (
                                            <div className="space-y-2">
                                                {contactsForm.accounts_payable.map((ap, idx) => (
                                                    <div key={idx} className="flex gap-2">
                                                        <div className="flex-1">
                                                            <input
                                                                type="text"
                                                                value={ap.name}
                                                                onChange={(e) => updateAP(idx, 'name', e.target.value)}
                                                                placeholder="Name"
                                                                className={`block w-full rounded-md text-sm shadow-sm focus:ring-indigo-500 ${contactsErrors[`accounts_payable.${idx}.name`] ? 'border-red-300' : 'border-gray-300'}`}
                                                            />
                                                        </div>
                                                        <div className="flex-1">
                                                            <input
                                                                type="text"
                                                                value={ap.value}
                                                                onChange={(e) => updateAP(idx, 'value', e.target.value)}
                                                                placeholder="Email or URL"
                                                                className={`block w-full rounded-md text-sm shadow-sm focus:ring-indigo-500 ${contactsErrors[`accounts_payable.${idx}.value`] ? 'border-red-300' : 'border-gray-300'}`}
                                                            />
                                                        </div>
                                                        <button
                                                            type="button"
                                                            onClick={() => removeAP(idx)}
                                                            className="text-red-400 hover:text-red-600"
                                                        >
                                                            <XIcon />
                                                        </button>
                                                    </div>
                                                ))}
                                            </div>
                                        )}
                                    </div>

                                    {/* Logistics Edit */}
                                    <div>
                                        <div className="mb-2 flex items-center justify-between">
                                            <h4 className="text-sm font-medium text-gray-700">Logistics</h4>
                                            <button
                                                type="button"
                                                onClick={addLogistics}
                                                className="flex items-center gap-1 text-sm text-indigo-600 hover:text-indigo-800"
                                            >
                                                <PlusIcon className="h-3 w-3" /> Add
                                            </button>
                                        </div>
                                        {contactsForm.logistics.length === 0 ? (
                                            <p className="text-sm text-gray-400">No logistics contacts</p>
                                        ) : (
                                            <div className="space-y-2">
                                                {contactsForm.logistics.map((logistics, idx) => (
                                                    <div key={idx} className="flex gap-2">
                                                        <div className="flex-1">
                                                            <input
                                                                type="text"
                                                                value={logistics.name}
                                                                onChange={(e) => updateLogistics(idx, 'name', e.target.value)}
                                                                placeholder="Name"
                                                                className={`block w-full rounded-md text-sm shadow-sm focus:ring-indigo-500 ${contactsErrors[`logistics.${idx}.name`] ? 'border-red-300' : 'border-gray-300'}`}
                                                            />
                                                        </div>
                                                        <div className="flex-1">
                                                            <input
                                                                type="email"
                                                                value={logistics.email}
                                                                onChange={(e) => updateLogistics(idx, 'email', e.target.value)}
                                                                placeholder="Email"
                                                                className={`block w-full rounded-md text-sm shadow-sm focus:ring-indigo-500 ${contactsErrors[`logistics.${idx}.email`] ? 'border-red-300' : 'border-gray-300'}`}
                                                            />
                                                        </div>
                                                        <button
                                                            type="button"
                                                            onClick={() => removeLogistics(idx)}
                                                            className="text-red-400 hover:text-red-600"
                                                        >
                                                            <XIcon />
                                                        </button>
                                                    </div>
                                                ))}
                                            </div>
                                        )}
                                    </div>
                                </div>
                            )}
                        </div>
                    </div>

                    {/* Company URLs (for Gmail matching) */}
                    <div className="overflow-hidden bg-white shadow-sm sm:rounded-lg">
                        <div className="p-6">
                            <div className="mb-4 flex items-center justify-between">
                                <div>
                                    <h3 className="text-lg font-medium text-gray-900">Company Domains</h3>
                                    <p className="text-sm text-gray-500">Email domains used for Gmail sync matching</p>
                                </div>
                                {!editingCompanyUrls ? (
                                    <button
                                        onClick={() => setEditingCompanyUrls(true)}
                                        className="text-gray-400 hover:text-gray-600"
                                        title="Edit"
                                    >
                                        <PencilIcon />
                                    </button>
                                ) : (
                                    <div className="flex gap-2">
                                        <button
                                            onClick={cancelCompanyUrlsEdit}
                                            className="rounded border border-gray-300 px-3 py-1 text-sm text-gray-600 hover:bg-gray-50"
                                            disabled={saving}
                                        >
                                            Cancel
                                        </button>
                                        <button
                                            onClick={saveCompanyUrls}
                                            disabled={saving}
                                            className="rounded bg-indigo-600 px-3 py-1 text-sm text-white hover:bg-indigo-700 disabled:opacity-50"
                                        >
                                            {saving ? 'Saving...' : 'Save'}
                                        </button>
                                    </div>
                                )}
                            </div>

                            {!editingCompanyUrls ? (
                                <div>
                                    {customer.company_urls && customer.company_urls.length > 0 ? (
                                        <div className="flex flex-wrap gap-2">
                                            {customer.company_urls.map((url, idx) => (
                                                <span
                                                    key={idx}
                                                    className="inline-flex items-center rounded-full bg-gray-100 px-3 py-1 text-sm text-gray-700"
                                                >
                                                    {url}
                                                </span>
                                            ))}
                                        </div>
                                    ) : (
                                        <p className="text-sm text-gray-400">No domains configured. Add domains to enable Gmail email tracking.</p>
                                    )}
                                </div>
                            ) : (
                                <div className="space-y-2">
                                    {companyUrlsForm.map((url, idx) => (
                                        <div key={idx} className="flex gap-2">
                                            <input
                                                type="text"
                                                value={url}
                                                onChange={(e) => updateCompanyUrl(idx, e.target.value)}
                                                placeholder="e.g., example.com"
                                                className="block flex-1 rounded-md border-gray-300 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                                            />
                                            <button
                                                type="button"
                                                onClick={() => removeCompanyUrl(idx)}
                                                className="text-red-400 hover:text-red-600"
                                            >
                                                <XIcon />
                                            </button>
                                        </div>
                                    ))}
                                    <button
                                        type="button"
                                        onClick={addCompanyUrl}
                                        className="flex items-center gap-1 text-sm text-indigo-600 hover:text-indigo-800"
                                    >
                                        <PlusIcon className="h-3 w-3" /> Add Domain
                                    </button>
                                </div>
                            )}
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
