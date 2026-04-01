import EmailActivityPanel from '@/Components/EmailActivityPanel';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head, Link, router, usePage } from '@inertiajs/react';
import { useEffect, useState } from 'react';

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

interface LocalContact {
    id: number;
    name: string;
    email: string;
    is_local?: boolean;
    last_emailed_at?: string | null;
    last_received_at?: string | null;
}

interface UncategorizedContact {
    id: number;
    name: string;
    email: string;
    last_emailed_at: string | null;
    last_received_at: string | null;
}

interface BrokerContact {
    id: number;
    name: string;
    email: string;
    last_emailed_at: string | null;
    last_received_at: string | null;
}

interface DistributorCustomerContact {
    id: number;
    name: string;
    email: string;
    type: string;
    last_emailed_at: string | null;
    last_received_at: string | null;
}

interface DistributorCustomer {
    id: number;
    name: string;
    company_urls: string[];
    contacts: DistributorCustomerContact[];
}

interface APContact {
    name: string;
    value: string;
}

interface OtherContact {
    name: string;
    email: string;
    function?: string;
}

interface ArSettings {
    edi: boolean;
    consolidated_invoicing: boolean;
    requires_customer_skus: boolean;
    invoice_discount: number | null;
}

interface Customer {
    id: number;
    name: string;
    code: string | null;
    buyers: Contact[];
    accounts_payable: APContact[];
    other: OtherContact[];
    payment_terms: string | null;
    shipping_terms: string | null;
    discount_percent: number | null;
    shelf_life_requirement: string | null;
    vendor_guide: string | null;
    receivable: number | null;
    receivable_today: number | null;
    company_urls: string[];
    customer_type: 'retailer' | 'distributor';
    broker: boolean;
    broker_commission: number | null;
    broker_company_name: string | null;
    ar_settings?: ArSettings;
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

interface Flash {
    success?: string;
    error?: string;
}

interface Product {
    id: number;
    sku: string;
    name: string;
}

interface CustomerSku {
    id: number;
    yums_sku: string;
    customer_sku: string;
}

interface Props {
    customer: Customer;
    buyerContacts: BuyerContact[];
    localBuyers: LocalContact[];
    localAP: LocalContact[];
    localOther: LocalContact[];
    uncategorizedContacts: UncategorizedContact[];
    brokerContacts: BrokerContact[];
    distributorCustomers: DistributorCustomer[];
    monthlyRevenue: MonthlyRevenue[];
    topProducts: TopProduct[];
    upcomingOrders: UpcomingOrder[];
    outstandingInvoices: OutstandingInvoice[];
    lastUpdated: string;
    priceLists: PriceList[];
    paymentTerms: PaymentTerm[];
    shippingTerms: ShippingTerm[];
    products: Product[];
    customerSkus: CustomerSku[];
    error?: string;
}

interface CustomerDetailsForm {
    name: string;
    sale_price_list: string;
    customer_payment_term: string;
    shipping_terms_category_id: string;
    shelf_life_requirement: string;
    vendor_guide: string;
    broker: string; // "true", "false", or "" for unselected
    broker_commission: string;
    broker_company_name: string;
}

interface ArSettingsForm {
    edi: boolean;
    consolidated_invoicing: boolean;
    requires_customer_skus: boolean;
    invoice_discount: string;
}

interface BrokerContactForm {
    name: string;
    email: string;
}

interface ContactsForm {
    buyers: Contact[];
    ap_method: 'inbox' | 'portal'; // Required - no empty option
    ap_portal_url: string;
    accounts_payable: APContact[]; // At least 1 required
    other: OtherContact[];
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
    // Append T12:00:00 to avoid timezone issues - parsing "2026-01-10" as UTC
    // midnight shows as previous day in US timezones
    return new Date(dateStr + 'T12:00:00').toLocaleDateString();
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
function PencilIcon({ className = 'h-4 w-4' }: { className?: string }) {
    return (
        <svg className={className} fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path
                strokeLinecap="round"
                strokeLinejoin="round"
                strokeWidth={2}
                d="M15.232 5.232l3.536 3.536m-2.036-5.036a2.5 2.5 0 113.536 3.536L6.5 21.036H3v-3.572L16.732 3.732z"
            />
        </svg>
    );
}

// X icon component
function XIcon({ className = 'h-4 w-4' }: { className?: string }) {
    return (
        <svg className={className} fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M6 18L18 6M6 6l12 12" />
        </svg>
    );
}

// Plus icon component
function PlusIcon({ className = 'h-4 w-4' }: { className?: string }) {
    return (
        <svg className={className} fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M12 4v16m8-8H4" />
        </svg>
    );
}

export default function Show({
    customer,
    buyerContacts,
    localBuyers,
    localAP,
    localOther,
    uncategorizedContacts,
    brokerContacts,
    distributorCustomers,
    monthlyRevenue,
    topProducts,
    upcomingOrders,
    outstandingInvoices,
    lastUpdated,
    priceLists,
    paymentTerms,
    shippingTerms,
    products,
    customerSkus: initialCustomerSkus,
    error,
}: Props) {
    // Flash messages
    const { props } = usePage();
    const flash = (props as { flash?: Flash }).flash;
    const [showSuccessBanner, setShowSuccessBanner] = useState(!!flash?.success);

    // Local notification state for async operations
    const [notification, setNotification] = useState<{ type: 'success' | 'error'; message: string } | null>(null);

    // Auto-dismiss success banner after 5 seconds
    useEffect(() => {
        if (flash?.success) {
            setShowSuccessBanner(true);
            const timer = setTimeout(() => setShowSuccessBanner(false), 5000);
            return () => clearTimeout(timer);
        }
    }, [flash?.success]);

    // Auto-dismiss local notification after 5 seconds
    useEffect(() => {
        if (notification) {
            const timer = setTimeout(() => setNotification(null), 5000);
            return () => clearTimeout(timer);
        }
    }, [notification]);

    // Edit mode states
    const [editingDetails, setEditingDetails] = useState(false);
    const [editingContacts, setEditingContacts] = useState(false);
    const [editingCompanyUrls, setEditingCompanyUrls] = useState(false);
    const [saving, setSaving] = useState(false);

    // Track contacts being categorized (contact id -> selected type)
    const [categorizingContacts, setCategorizingContacts] = useState<Record<number, string>>({});

    // Company URLs form state
    const [companyUrlsForm, setCompanyUrlsForm] = useState<string[]>(customer.company_urls?.length > 0 ? [...customer.company_urls] : ['']);

    // Form states
    const [detailsForm, setDetailsForm] = useState<CustomerDetailsForm>({
        name: customer.name,
        sale_price_list: priceLists.find((pl) => pl.discount_percent === customer.discount_percent)?.id.toString() || '',
        customer_payment_term: paymentTerms.find((pt) => pt.name === customer.payment_terms)?.id.toString() || '',
        shipping_terms_category_id: shippingTerms.find((st) => st.name === customer.shipping_terms)?.id.toString() || '',
        shelf_life_requirement: customer.shelf_life_requirement || '',
        vendor_guide: customer.vendor_guide || '',
        broker: customer.broker === true ? 'true' : customer.broker === false ? 'false' : '',
        broker_commission: customer.broker_commission?.toString() || '',
        broker_company_name: customer.broker_company_name || '',
    });

    // Broker section states
    const [editingBroker, setEditingBroker] = useState(false);
    const [brokerContactsForm, setBrokerContactsForm] = useState<BrokerContactForm[]>(
        brokerContacts?.map((c) => ({ name: c.name, email: c.email })) || [],
    );

    // Customer type state
    const [customerType, setCustomerType] = useState<'retailer' | 'distributor'>(customer.customer_type || 'retailer');
    const [changingCustomerType, setChangingCustomerType] = useState(false);

    // AR settings state
    const [arSettingsForm, setArSettingsForm] = useState<ArSettingsForm>({
        edi: customer.ar_settings?.edi ?? false,
        consolidated_invoicing: customer.ar_settings?.consolidated_invoicing ?? false,
        requires_customer_skus: customer.ar_settings?.requires_customer_skus ?? false,
        invoice_discount: customer.ar_settings?.invoice_discount?.toString() ?? '',
    });

    // Customer SKU state
    const [customerSkus, setCustomerSkus] = useState<CustomerSku[]>(initialCustomerSkus || []);

    // Distributor customers state
    const [localDistributorCustomers, setLocalDistributorCustomers] = useState<DistributorCustomer[]>(distributorCustomers || []);
    const [newDistributorCustomerName, setNewDistributorCustomerName] = useState('');
    const [addingDistributorCustomer, setAddingDistributorCustomer] = useState(false);
    const [expandedDistributorCustomers, setExpandedDistributorCustomers] = useState<Set<number>>(() => new Set());
    const [deleteConfirmDC, setDeleteConfirmDC] = useState<DistributorCustomer | null>(null);
    const [deletingDC, setDeletingDC] = useState(false);
    // Distributor customer editing state
    const [editingDCId, setEditingDCId] = useState<number | null>(null);
    const [editingDCName, setEditingDCName] = useState('');
    const [editingDCUrls, setEditingDCUrls] = useState<string[]>([]);
    const [savingDC, setSavingDC] = useState(false);
    // Distributor customer contact state
    const [newDCContactEmail, setNewDCContactEmail] = useState('');
    const [addingDCContact, setAddingDCContact] = useState(false);
    const [editingDCContactId, setEditingDCContactId] = useState<number | null>(null);
    const [editingDCContactName, setEditingDCContactName] = useState('');
    const [editingDCContactType, setEditingDCContactType] = useState('');
    const [savingDCContact, setSavingDCContact] = useState(false);
    const [deletingDCContactId, setDeletingDCContactId] = useState<number | null>(null);

    // Derive AP method from existing data
    const deriveApMethod = (): { method: 'inbox' | 'portal'; portalUrl: string; contacts: APContact[] } => {
        const apContacts = customer.accounts_payable || [];
        // Check if there's an "AP Portal" entry with a URL
        const portalEntry = apContacts.find((ap) => ap.name === 'AP Portal' && ap.value?.startsWith('http'));
        // Get non-portal contacts (regular AP contacts with emails)
        const regularContacts = apContacts.filter((ap) => ap.name !== 'AP Portal' || !ap.value?.startsWith('http'));

        if (portalEntry) {
            // Has portal - return portal URL and non-portal contacts
            return {
                method: 'portal',
                portalUrl: portalEntry.value,
                contacts: regularContacts.length > 0 ? regularContacts : [{ name: '', value: '' }],
            };
        }
        // No portal - just inbox with contacts
        return {
            method: 'inbox',
            portalUrl: '',
            contacts: regularContacts.length > 0 ? regularContacts : [{ name: '', value: '' }],
        };
    };

    const initialApState = deriveApMethod();

    const [contactsForm, setContactsForm] = useState<ContactsForm>({
        buyers: customer.buyers?.length > 0 ? [...customer.buyers] : [{ name: '', email: '' }],
        ap_method: initialApState.method,
        ap_portal_url: initialApState.portalUrl,
        accounts_payable: initialApState.contacts,
        other: customer.other?.length > 0 ? [...customer.other] : [],
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

        // Validate AP - at least 1 AP contact always required
        if (contactsForm.ap_method === 'portal') {
            if (!contactsForm.ap_portal_url || !isValidUrl(contactsForm.ap_portal_url)) {
                errors.ap_portal_url = 'Valid portal URL is required (https://...)';
            }
        }

        // AP contacts are always required (at least 1) regardless of method
        if (contactsForm.accounts_payable.length === 0) {
            errors.accounts_payable = 'At least one AP contact is required';
        }
        contactsForm.accounts_payable.forEach((ap, idx) => {
            if (!ap.name || ap.name.length < 2) {
                errors[`accounts_payable.${idx}.name`] = 'Name is required';
            }
            if (!ap.value || !isValidEmail(ap.value)) {
                errors[`accounts_payable.${idx}.value`] = 'Valid email is required';
            }
        });

        // Validate other contacts (optional, but if provided must be valid)
        contactsForm.other.forEach((other, idx) => {
            if (!other.name || other.name.length < 2) {
                errors[`other.${idx}.name`] = 'Name is required';
            }
            if (!other.email || !isValidEmail(other.email)) {
                errors[`other.${idx}.email`] = 'Valid email is required';
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
            sale_price_list: priceLists.find((pl) => pl.discount_percent === customer.discount_percent)?.id.toString() || '',
            customer_payment_term: paymentTerms.find((pt) => pt.name === customer.payment_terms)?.id.toString() || '',
            shipping_terms_category_id: shippingTerms.find((st) => st.name === customer.shipping_terms)?.id.toString() || '',
            shelf_life_requirement: customer.shelf_life_requirement || '',
            vendor_guide: customer.vendor_guide || '',
            broker: customer.broker === true ? 'true' : customer.broker === false ? 'false' : '',
            broker_commission: customer.broker_commission?.toString() || '',
            broker_company_name: customer.broker_company_name || '',
        });
        setEditingDetails(false);
        setDetailsErrors({});
    };

    const cancelContactsEdit = () => {
        const apState = deriveApMethod();
        setContactsForm({
            buyers: customer.buyers?.length > 0 ? [...customer.buyers] : [{ name: '', email: '' }],
            ap_method: apState.method,
            ap_portal_url: apState.portalUrl,
            accounts_payable: apState.contacts,
            other: customer.other?.length > 0 ? [...customer.other] : [],
        });
        setEditingContacts(false);
        setContactsErrors({});
    };

    const saveDetails = async () => {
        if (Object.keys(detailsErrors).length > 0) return;

        setSaving(true);
        try {
            // Save customer details and AR settings in a single request
            // so they're written to Fulfil atomically (avoiding metafield race conditions)
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
                    // AR settings (saved atomically with party data)
                    ar_edi: arSettingsForm.edi,
                    ar_consolidated_invoicing: arSettingsForm.consolidated_invoicing,
                    ar_requires_customer_skus: arSettingsForm.requires_customer_skus,
                    ar_invoice_discount: arSettingsForm.invoice_discount ? parseFloat(arSettingsForm.invoice_discount) : null,
                    // Note: broker is updated via the broker section, not here
                }),
            });

            if (response.ok) {
                setEditingDetails(false);
                setNotification({ type: 'success', message: 'Customer details updated successfully' });
                router.reload({ only: ['customer'] });
            } else {
                const data = await response.json();
                setNotification({ type: 'error', message: data.message || 'Failed to save changes' });
            }
        } catch {
            setNotification({ type: 'error', message: 'Failed to save changes. Please try again.' });
        } finally {
            setSaving(false);
        }
    };

    const saveContacts = async () => {
        if (Object.keys(contactsErrors).length > 0) return;

        // Build accounts_payable array - always include AP contacts, optionally prepend portal
        let accountsPayable: APContact[] = [...contactsForm.accounts_payable];
        if (contactsForm.ap_method === 'portal' && contactsForm.ap_portal_url) {
            // Prepend portal URL to AP contacts
            accountsPayable = [{ name: 'AP Portal', value: contactsForm.ap_portal_url }, ...accountsPayable];
        }

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
                    accounts_payable: accountsPayable,
                    other: contactsForm.other.map((o) => ({
                        name: o.name,
                        email: o.email,
                        function: o.function || '',
                    })),
                }),
            });

            if (response.ok) {
                setEditingContacts(false);
                setNotification({ type: 'success', message: 'Contacts updated successfully' });
                router.reload({ only: ['customer', 'buyerContacts', 'localBuyers', 'localAP', 'localOther'] });
            } else {
                const data = await response.json();
                setNotification({ type: 'error', message: data.message || 'Failed to save changes' });
            }
        } catch {
            setNotification({ type: 'error', message: 'Failed to save changes. Please try again.' });
        } finally {
            setSaving(false);
        }
    };

    // Customer SKU management functions
    const [newSkuYums, setNewSkuYums] = useState('');
    const [newSkuCustomer, setNewSkuCustomer] = useState('');
    const [addingSku, setAddingSku] = useState(false);

    const addCustomerSku = async () => {
        if (!newSkuYums || !newSkuCustomer) return;

        setAddingSku(true);
        try {
            const response = await fetch(route('customers.skus.store', customer.id), {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '',
                },
                body: JSON.stringify({
                    yums_sku: newSkuYums,
                    customer_sku: newSkuCustomer,
                }),
            });

            if (response.ok) {
                const data = await response.json();
                setCustomerSkus((prev) => [...prev, data.sku]);
                setNewSkuYums('');
                setNewSkuCustomer('');
                setNotification({ type: 'success', message: 'SKU mapping added successfully' });
            } else {
                const data = await response.json();
                setNotification({ type: 'error', message: data.message || 'Failed to add SKU mapping' });
            }
        } catch {
            setNotification({ type: 'error', message: 'Failed to add SKU mapping. Please try again.' });
        } finally {
            setAddingSku(false);
        }
    };

    const deleteCustomerSku = async (skuId: number) => {
        if (!confirm('Are you sure you want to delete this SKU mapping?')) return;

        try {
            const response = await fetch(route('customers.skus.destroy', { customerId: customer.id, skuId }), {
                method: 'DELETE',
                headers: {
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '',
                },
            });

            if (response.ok) {
                setCustomerSkus((prev) => prev.filter((s) => s.id !== skuId));
                setNotification({ type: 'success', message: 'SKU mapping deleted successfully' });
            } else {
                const data = await response.json();
                setNotification({ type: 'error', message: data.message || 'Failed to delete SKU mapping' });
            }
        } catch {
            setNotification({ type: 'error', message: 'Failed to delete SKU mapping. Please try again.' });
        }
    };

    // Get available products (not yet mapped)
    const availableProducts = products.filter((p) => !customerSkus.some((s) => s.yums_sku === p.sku));

    // Invoice PDF handlers
    const handleDownloadPdf = (invoiceId: number) => {
        // Open the download URL in a new window/tab to trigger the download
        window.open(route('invoices.pdf.download', { id: invoiceId }), '_blank');
    };

    const handleRegeneratePdf = async (invoiceId: number, invoiceNumber: string | null) => {
        try {
            setNotification({ type: 'success', message: `Regenerating PDF for invoice ${invoiceNumber || invoiceId}...` });

            const response = await fetch(route('invoices.pdf.regenerate', { id: invoiceId }), {
                method: 'POST',
                headers: {
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '',
                    Accept: 'application/pdf',
                },
            });

            if (!response.ok) {
                const errorData = await response.json().catch(() => ({ message: 'Failed to regenerate PDF' }));
                if (errorData.error === 'sku_mapping_required' && errorData.unmapped_skus) {
                    setNotification({
                        type: 'error',
                        message: `Cannot generate PDF: unmapped SKUs: ${errorData.unmapped_skus.join(', ')}. Please add the missing SKU mappings.`,
                    });
                } else {
                    setNotification({ type: 'error', message: errorData.message || 'Failed to regenerate PDF' });
                }
                return;
            }

            // Handle the PDF blob response
            const blob = await response.blob();
            const url = window.URL.createObjectURL(blob);
            const a = document.createElement('a');
            a.href = url;
            a.download = `${invoiceNumber || `invoice-${invoiceId}`}.pdf`;
            document.body.appendChild(a);
            a.click();
            window.URL.revokeObjectURL(url);
            document.body.removeChild(a);

            setNotification({ type: 'success', message: `PDF regenerated for invoice ${invoiceNumber || invoiceId}` });
        } catch {
            console.error('Error regenerating PDF:', error);
            setNotification({ type: 'error', message: 'Failed to regenerate PDF. Please try again.' });
        }
    };

    // Contact management functions
    const addBuyer = () => {
        setContactsForm((prev) => ({
            ...prev,
            buyers: [...prev.buyers, { name: '', email: '' }],
        }));
    };

    const removeBuyer = (index: number) => {
        if (contactsForm.buyers.length > 1) {
            setContactsForm((prev) => ({
                ...prev,
                buyers: prev.buyers.filter((_, i) => i !== index),
            }));
        }
    };

    const updateBuyer = (index: number, field: 'name' | 'email', value: string) => {
        setContactsForm((prev) => ({
            ...prev,
            buyers: prev.buyers.map((b, i) => (i === index ? { ...b, [field]: value } : b)),
        }));
    };

    const addAP = () => {
        setContactsForm((prev) => ({
            ...prev,
            accounts_payable: [...prev.accounts_payable, { name: '', value: '' }],
        }));
    };

    const removeAP = (index: number) => {
        // Prevent removing the last AP contact (at least 1 required)
        if (contactsForm.accounts_payable.length <= 1) return;

        setContactsForm((prev) => ({
            ...prev,
            accounts_payable: prev.accounts_payable.filter((_, i) => i !== index),
        }));
    };

    const updateAP = (index: number, field: 'name' | 'value', value: string) => {
        setContactsForm((prev) => ({
            ...prev,
            accounts_payable: prev.accounts_payable.map((ap, i) => (i === index ? { ...ap, [field]: value } : ap)),
        }));
    };

    const addOther = () => {
        setContactsForm((prev) => ({
            ...prev,
            other: [...prev.other, { name: '', email: '', function: '' }],
        }));
    };

    const removeOther = (index: number) => {
        setContactsForm((prev) => ({
            ...prev,
            other: prev.other.filter((_, i) => i !== index),
        }));
    };

    const updateOther = (index: number, field: 'name' | 'email' | 'function', value: string) => {
        setContactsForm((prev) => ({
            ...prev,
            other: prev.other.map((o, i) => (i === index ? { ...o, [field]: value } : o)),
        }));
    };

    // Categorize an uncategorized local contact
    const categorizeContact = async (contactId: number, newType: string) => {
        if (!contactId || !newType) return;

        try {
            const response = await fetch(route('customers.contacts.categorize', { customerId: customer.id, contactId }), {
                method: 'PATCH',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '',
                },
                body: JSON.stringify({ type: newType }),
            });

            if (response.ok) {
                setCategorizingContacts({});
                setNotification({ type: 'success', message: 'Contact categorized successfully' });
                router.reload();
            } else {
                const data = await response.json();
                setNotification({ type: 'error', message: data.message || 'Failed to categorize contact' });
            }
        } catch {
            setNotification({ type: 'error', message: 'Failed to categorize contact. Please try again.' });
        }
    };

    // Delete a local contact
    const deleteLocalContact = async (contactId: number) => {
        if (!confirm('Are you sure you want to delete this contact?')) return;

        try {
            const response = await fetch(route('customers.contacts.delete', { customerId: customer.id, contactId }), {
                method: 'DELETE',
                headers: {
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '',
                },
            });

            if (response.ok) {
                setNotification({ type: 'success', message: 'Contact deleted successfully' });
                router.reload();
            } else {
                const data = await response.json();
                setNotification({ type: 'error', message: data.message || 'Failed to delete contact' });
            }
        } catch {
            setNotification({ type: 'error', message: 'Failed to delete contact. Please try again.' });
        }
    };

    // Broker contact management
    const cancelBrokerEdit = () => {
        setBrokerContactsForm(brokerContacts?.map((c) => ({ name: c.name, email: c.email })) || []);
        setDetailsForm((prev) => ({
            ...prev,
            broker: customer.broker === true ? 'true' : customer.broker === false ? 'false' : '',
            broker_commission: customer.broker_commission?.toString() || '',
            broker_company_name: customer.broker_company_name || '',
        }));
        setEditingBroker(false);
    };

    // Validate broker section - when broker=true, requires company name, commission, and at least 1 contact with name+email
    const isBrokerValid = (): boolean => {
        // If broker is false, no validation needed
        if (detailsForm.broker !== 'true') {
            return true;
        }

        // Broker company name required
        if (!detailsForm.broker_company_name || detailsForm.broker_company_name.trim().length === 0) {
            return false;
        }

        // Commission required and must be valid number
        if (!detailsForm.broker_commission || detailsForm.broker_commission.trim() === '') {
            return false;
        }
        const commission = parseFloat(detailsForm.broker_commission);
        if (isNaN(commission) || commission < 0 || commission > 100) {
            return false;
        }

        // At least one broker contact with both name and valid email
        const validContacts = brokerContactsForm.filter((c) => c.name && c.name.trim().length >= 2 && c.email && isValidEmail(c.email));
        if (validContacts.length === 0) {
            return false;
        }

        return true;
    };

    const addBrokerContactForm = () => {
        setBrokerContactsForm((prev) => [...prev, { name: '', email: '' }]);
    };

    const removeBrokerContactForm = (index: number) => {
        setBrokerContactsForm((prev) => prev.filter((_, i) => i !== index));
    };

    const updateBrokerContactForm = (index: number, field: 'name' | 'email', value: string) => {
        setBrokerContactsForm((prev) => prev.map((c, i) => (i === index ? { ...c, [field]: value } : c)));
    };

    const saveBroker = async () => {
        setSaving(true);
        try {
            // First update broker flag and commission
            const brokerResponse = await fetch(route('customers.broker.update', customer.id), {
                method: 'PUT',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '',
                },
                body: JSON.stringify({
                    broker: detailsForm.broker === 'true',
                    broker_commission: detailsForm.broker_commission ? parseFloat(detailsForm.broker_commission) : null,
                    broker_company_name: detailsForm.broker_company_name || null,
                    broker_contacts: brokerContactsForm.filter((c) => c.name.trim()),
                }),
            });

            if (brokerResponse.ok) {
                setEditingBroker(false);
                setNotification({ type: 'success', message: 'Broker settings updated successfully' });
                router.reload();
            } else {
                const data = await brokerResponse.json();
                setNotification({ type: 'error', message: data.message || 'Failed to save broker settings' });
            }
        } catch {
            setNotification({ type: 'error', message: 'Failed to save broker settings. Please try again.' });
        } finally {
            setSaving(false);
        }
    };

    // Customer type management
    const handleCustomerTypeChange = async (newType: 'retailer' | 'distributor') => {
        if (newType === customerType) return;

        setChangingCustomerType(true);
        try {
            const response = await fetch(route('customers.update-customer-type', customer.id), {
                method: 'PATCH',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '',
                },
                body: JSON.stringify({ customer_type: newType }),
            });

            if (response.ok) {
                setCustomerType(newType);
                setNotification({ type: 'success', message: `Customer type updated to ${newType}` });
                router.reload();
            } else {
                const data = await response.json();
                setNotification({ type: 'error', message: data.message || 'Failed to update customer type' });
            }
        } catch {
            setNotification({ type: 'error', message: 'Failed to update customer type' });
        } finally {
            setChangingCustomerType(false);
        }
    };

    // Distributor customer management
    const toggleDistributorCustomerExpanded = (id: number) => {
        setExpandedDistributorCustomers((prev) => {
            const next = new Set(prev);
            if (next.has(id)) {
                next.delete(id);
            } else {
                next.add(id);
            }
            return next;
        });
    };

    const addDistributorCustomer = async () => {
        if (!newDistributorCustomerName.trim()) return;

        setAddingDistributorCustomer(true);
        try {
            const response = await fetch(route('customers.distributor-customers.create', customer.id), {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '',
                },
                body: JSON.stringify({ name: newDistributorCustomerName.trim() }),
            });

            if (response.ok) {
                const data = await response.json();
                setLocalDistributorCustomers((prev) => [...prev, data.distributor_customer]);
                setNewDistributorCustomerName('');
                setNotification({ type: 'success', message: 'Distributor customer added' });
            } else {
                const data = await response.json();
                setNotification({ type: 'error', message: data.message || 'Failed to add distributor customer' });
            }
        } catch {
            setNotification({ type: 'error', message: 'Failed to add distributor customer' });
        } finally {
            setAddingDistributorCustomer(false);
        }
    };

    const deleteDistributorCustomer = async () => {
        if (!deleteConfirmDC) return;

        setDeletingDC(true);
        try {
            const response = await fetch(
                route('customers.distributor-customers.delete', { customerId: customer.id, distributorCustomerId: deleteConfirmDC.id }),
                {
                    method: 'DELETE',
                    headers: {
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '',
                    },
                },
            );

            if (response.ok) {
                setLocalDistributorCustomers((prev) => prev.filter((dc) => dc.id !== deleteConfirmDC.id));
                setDeleteConfirmDC(null);
                setNotification({ type: 'success', message: 'Distributor customer deleted' });
            } else {
                const data = await response.json();
                setNotification({ type: 'error', message: data.message || 'Failed to delete' });
            }
        } catch {
            setNotification({ type: 'error', message: 'Failed to delete distributor customer' });
        } finally {
            setDeletingDC(false);
        }
    };

    const startEditingDC = (dc: DistributorCustomer) => {
        setEditingDCId(dc.id);
        setEditingDCName(dc.name);
        setEditingDCUrls([...dc.company_urls, '']); // Add empty slot for new URL
        setExpandedDistributorCustomers((prev) => new Set(prev).add(dc.id));
    };

    const cancelEditingDC = () => {
        setEditingDCId(null);
        setEditingDCName('');
        setEditingDCUrls([]);
    };

    const saveDistributorCustomer = async () => {
        if (!editingDCId) return;

        setSavingDC(true);
        try {
            const response = await fetch(
                route('customers.distributor-customers.update', { customerId: customer.id, distributorCustomerId: editingDCId }),
                {
                    method: 'PUT',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '',
                    },
                    body: JSON.stringify({
                        name: editingDCName.trim(),
                        company_urls: editingDCUrls.filter((u) => u.trim()),
                    }),
                },
            );

            if (response.ok) {
                const data = await response.json();
                setLocalDistributorCustomers((prev) =>
                    prev.map((dc) =>
                        dc.id === editingDCId
                            ? { ...dc, name: data.distributor_customer.name, company_urls: data.distributor_customer.company_urls }
                            : dc,
                    ),
                );
                setNotification({ type: 'success', message: 'Distributor customer updated' });
                cancelEditingDC();
            } else {
                const data = await response.json();
                setNotification({ type: 'error', message: data.message || 'Failed to update' });
            }
        } catch {
            setNotification({ type: 'error', message: 'Failed to update distributor customer' });
        } finally {
            setSavingDC(false);
        }
    };

    const addDCUrl = () => {
        setEditingDCUrls((prev) => [...prev, '']);
    };

    const updateDCUrl = (index: number, value: string) => {
        setEditingDCUrls((prev) => prev.map((url, i) => (i === index ? value : url)));
    };

    const removeDCUrl = (index: number) => {
        setEditingDCUrls((prev) => prev.filter((_, i) => i !== index));
    };

    // Distributor customer contact management
    const addDistributorCustomerContact = async (dcId: number) => {
        if (!newDCContactEmail.trim()) return;

        setAddingDCContact(true);
        try {
            const response = await fetch(route('distributor-customers.contacts.create', { distributorCustomerId: dcId }), {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '',
                },
                body: JSON.stringify({ email: newDCContactEmail.trim() }),
            });

            if (response.ok) {
                const data = await response.json();
                setLocalDistributorCustomers((prev) =>
                    prev.map((dc) => (dc.id === dcId ? { ...dc, contacts: [...(dc.contacts || []), data.contact] } : dc)),
                );
                setNewDCContactEmail('');
                setNotification({ type: 'success', message: 'Contact added' });
            } else {
                const data = await response.json();
                setNotification({ type: 'error', message: data.message || 'Failed to add contact' });
            }
        } catch {
            setNotification({ type: 'error', message: 'Failed to add contact' });
        } finally {
            setAddingDCContact(false);
        }
    };

    const startEditingDCContact = (contact: DistributorCustomerContact) => {
        setEditingDCContactId(contact.id);
        setEditingDCContactName(contact.name || '');
        setEditingDCContactType(contact.type);
    };

    const cancelEditingDCContact = () => {
        setEditingDCContactId(null);
        setEditingDCContactName('');
        setEditingDCContactType('');
    };

    const saveDistributorCustomerContact = async (dcId: number) => {
        if (!editingDCContactId) return;

        setSavingDCContact(true);
        try {
            const response = await fetch(
                route('distributor-customers.contacts.update', { distributorCustomerId: dcId, contactId: editingDCContactId }),
                {
                    method: 'PUT',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '',
                    },
                    body: JSON.stringify({
                        name: editingDCContactName.trim(),
                        type: editingDCContactType,
                    }),
                },
            );

            if (response.ok) {
                const data = await response.json();
                setLocalDistributorCustomers((prev) =>
                    prev.map((dc) =>
                        dc.id === dcId
                            ? {
                                  ...dc,
                                  contacts: dc.contacts.map((c) => (c.id === editingDCContactId ? data.contact : c)),
                              }
                            : dc,
                    ),
                );
                setNotification({ type: 'success', message: 'Contact updated' });
                cancelEditingDCContact();
            } else {
                const data = await response.json();
                setNotification({ type: 'error', message: data.message || 'Failed to update contact' });
            }
        } catch {
            setNotification({ type: 'error', message: 'Failed to update contact' });
        } finally {
            setSavingDCContact(false);
        }
    };

    const deleteDistributorCustomerContact = async (dcId: number, contactId: number) => {
        setDeletingDCContactId(contactId);
        try {
            const response = await fetch(route('distributor-customers.contacts.delete', { distributorCustomerId: dcId, contactId }), {
                method: 'DELETE',
                headers: {
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '',
                },
            });

            if (response.ok) {
                setLocalDistributorCustomers((prev) =>
                    prev.map((dc) => (dc.id === dcId ? { ...dc, contacts: dc.contacts.filter((c) => c.id !== contactId) } : dc)),
                );
                setNotification({ type: 'success', message: 'Contact deleted' });
            } else {
                const data = await response.json();
                setNotification({ type: 'error', message: data.message || 'Failed to delete contact' });
            }
        } catch {
            setNotification({ type: 'error', message: 'Failed to delete contact' });
        } finally {
            setDeletingDCContactId(null);
        }
    };

    // Company URL management
    const addCompanyUrl = () => {
        setCompanyUrlsForm((prev) => [...prev, '']);
    };

    const removeCompanyUrl = (index: number) => {
        if (companyUrlsForm.length > 1) {
            setCompanyUrlsForm((prev) => prev.filter((_, i) => i !== index));
        } else {
            setCompanyUrlsForm(['']);
        }
    };

    const updateCompanyUrl = (index: number, value: string) => {
        setCompanyUrlsForm((prev) => prev.map((url, i) => (i === index ? value : url)));
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
                    company_urls: companyUrlsForm.filter((url) => url.trim() !== ''),
                }),
            });

            if (response.ok) {
                setEditingCompanyUrls(false);
                setNotification({ type: 'success', message: 'Company domains updated successfully' });
                router.reload({ only: ['customer'] });
            } else {
                const data = await response.json();
                setNotification({ type: 'error', message: data.message || 'Failed to save changes' });
            }
        } catch {
            setNotification({ type: 'error', message: 'Failed to save changes. Please try again.' });
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
    const maxRevenue = Math.max(...monthlyRevenue.map((m) => Math.max(m.revenue, m.prior_year_revenue)), 10000);
    const yAxisSteps = 4;
    const yAxisValues = Array.from({ length: yAxisSteps + 1 }, (_, i) => Math.round((maxRevenue / yAxisSteps) * (yAxisSteps - i)));

    const detailsValid = Object.keys(detailsErrors).length === 0;
    const contactsValid = Object.keys(contactsErrors).length === 0;

    return (
        <AuthenticatedLayout
            header={
                <div className="flex items-center justify-between">
                    <div className="gap-4 flex items-center">
                        <Link href={route('customers.index')} className="text-gray-500 hover:text-gray-700">
                            &larr; Back
                        </Link>
                        <h2 className="text-xl font-semibold leading-tight text-gray-800">{customer.name}</h2>
                        <span className="px-2.5 py-0.5 text-xs font-medium bg-green-100 text-green-800 inline-flex items-center rounded-full">
                            Active
                        </span>
                    </div>
                    <button onClick={handleRefresh} className="text-sm text-gray-500 hover:text-gray-700">
                        Refresh Data
                    </button>
                </div>
            }
        >
            <Head title={customer.name} />

            {/* Success banner */}
            {showSuccessBanner && flash?.success && (
                <div className="max-w-7xl px-4 sm:px-6 lg:px-8 pt-4 mx-auto">
                    <div className="rounded-md bg-green-50 p-4">
                        <div className="flex">
                            <div className="flex-shrink-0">
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
                            <div className="pl-3 ml-auto">
                                <button
                                    onClick={() => setShowSuccessBanner(false)}
                                    className="rounded-md bg-green-50 p-1.5 text-green-500 hover:bg-green-100 inline-flex"
                                >
                                    <svg className="h-5 w-5" viewBox="0 0 20 20" fill="currentColor">
                                        <path d="M6.28 5.22a.75.75 0 00-1.06 1.06L8.94 10l-3.72 3.72a.75.75 0 101.06 1.06L10 11.06l3.72 3.72a.75.75 0 101.06-1.06L11.06 10l3.72-3.72a.75.75 0 00-1.06-1.06L10 8.94 6.28 5.22z" />
                                    </svg>
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
            )}

            {/* Local notification banner (for async operations) */}
            {notification && (
                <div className="max-w-7xl px-4 sm:px-6 lg:px-8 pt-4 mx-auto">
                    <div className={`rounded-md p-4 ${notification.type === 'success' ? 'bg-green-50' : 'bg-red-50'}`}>
                        <div className="flex">
                            <div className="flex-shrink-0">
                                {notification.type === 'success' ? (
                                    <svg className="h-5 w-5 text-green-400" viewBox="0 0 20 20" fill="currentColor">
                                        <path
                                            fillRule="evenodd"
                                            d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.857-9.809a.75.75 0 00-1.214-.882l-3.483 4.79-1.88-1.88a.75.75 0 10-1.06 1.061l2.5 2.5a.75.75 0 001.137-.089l4-5.5z"
                                            clipRule="evenodd"
                                        />
                                    </svg>
                                ) : (
                                    <svg className="h-5 w-5 text-red-400" viewBox="0 0 20 20" fill="currentColor">
                                        <path
                                            fillRule="evenodd"
                                            d="M10 18a8 8 0 100-16 8 8 0 000 16zM8.28 7.22a.75.75 0 00-1.06 1.06L8.94 10l-1.72 1.72a.75.75 0 101.06 1.06L10 11.06l1.72 1.72a.75.75 0 101.06-1.06L11.06 10l1.72-1.72a.75.75 0 00-1.06-1.06L10 8.94 8.28 7.22z"
                                            clipRule="evenodd"
                                        />
                                    </svg>
                                )}
                            </div>
                            <div className="ml-3">
                                <p className={`text-sm font-medium ${notification.type === 'success' ? 'text-green-800' : 'text-red-800'}`}>
                                    {notification.message}
                                </p>
                            </div>
                            <div className="pl-3 ml-auto">
                                <button
                                    onClick={() => setNotification(null)}
                                    className={`rounded-md p-1.5 inline-flex ${notification.type === 'success' ? 'bg-green-50 text-green-500 hover:bg-green-100' : 'bg-red-50 text-red-500 hover:bg-red-100'}`}
                                >
                                    <svg className="h-5 w-5" viewBox="0 0 20 20" fill="currentColor">
                                        <path d="M6.28 5.22a.75.75 0 00-1.06 1.06L8.94 10l-3.72 3.72a.75.75 0 101.06 1.06L10 11.06l3.72 3.72a.75.75 0 101.06-1.06L11.06 10l3.72-3.72a.75.75 0 00-1.06-1.06L10 8.94 6.28 5.22z" />
                                    </svg>
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
            )}

            {/* Fulfil API error banner */}
            {error && (
                <div className="max-w-7xl px-4 sm:px-6 lg:px-8 pt-4 mx-auto">
                    <div className="rounded-md bg-red-50 p-4">
                        <div className="flex">
                            <div className="flex-shrink-0">
                                <svg className="h-5 w-5 text-red-400" viewBox="0 0 20 20" fill="currentColor">
                                    <path
                                        fillRule="evenodd"
                                        d="M10 18a8 8 0 100-16 8 8 0 000 16zM8.28 7.22a.75.75 0 00-1.06 1.06L8.94 10l-1.72 1.72a.75.75 0 101.06 1.06L10 11.06l1.72 1.72a.75.75 0 101.06-1.06L11.06 10l1.72-1.72a.75.75 0 00-1.06-1.06L10 8.94 8.28 7.22z"
                                        clipRule="evenodd"
                                    />
                                </svg>
                            </div>
                            <div className="ml-3">
                                <p className="text-sm font-medium text-red-800">{error}</p>
                            </div>
                            <div className="pl-3 ml-auto">
                                <button
                                    onClick={handleRefresh}
                                    className="rounded-md bg-red-50 px-3 py-1.5 text-sm font-medium text-red-800 hover:bg-red-100"
                                >
                                    Retry
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
            )}

            <div className="py-12">
                <div className="max-w-7xl space-y-6 sm:px-6 lg:px-8 mx-auto">
                    {/* Customer Details */}
                    <div className="bg-white shadow-sm sm:rounded-lg overflow-hidden">
                        <div className="p-6">
                            <div className="mb-4 flex items-center justify-between">
                                <h3 className="text-lg font-medium text-gray-900">Customer Details</h3>
                                {!editingDetails ? (
                                    <button onClick={() => setEditingDetails(true)} className="text-gray-400 hover:text-gray-600" title="Edit">
                                        <PencilIcon />
                                    </button>
                                ) : (
                                    <div className="gap-2 flex">
                                        <button
                                            onClick={cancelDetailsEdit}
                                            className="rounded border-gray-300 px-3 py-1 text-sm text-gray-600 hover:bg-gray-50 border"
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

                            <div className="gap-4 sm:grid-cols-2 lg:grid-cols-3 grid grid-cols-1">
                                <div>
                                    <label className="text-sm font-medium text-gray-500 block">Company Name</label>
                                    {!editingDetails ? (
                                        <div className="mt-1 text-sm text-gray-900 py-2">{customer.name}</div>
                                    ) : (
                                        <>
                                            <input
                                                type="text"
                                                value={detailsForm.name}
                                                onChange={(e) => setDetailsForm((prev) => ({ ...prev, name: e.target.value }))}
                                                className={`mt-1 rounded-md shadow-sm focus:ring-indigo-500 block w-full ${detailsErrors.name ? 'border-red-300 focus:border-red-500' : 'border-gray-300 focus:border-indigo-500'}`}
                                            />
                                            {detailsErrors.name && <p className="mt-1 text-xs text-red-600">{detailsErrors.name}</p>}
                                        </>
                                    )}
                                </div>

                                <div>
                                    <label className="text-sm font-medium text-gray-500 block">Discount on Price List</label>
                                    {!editingDetails ? (
                                        <div className="mt-1 text-sm text-gray-900 py-2">
                                            {customer.discount_percent !== null ? `${customer.discount_percent}%` : '-'}
                                        </div>
                                    ) : (
                                        <>
                                            <select
                                                value={detailsForm.sale_price_list}
                                                onChange={(e) => setDetailsForm((prev) => ({ ...prev, sale_price_list: e.target.value }))}
                                                className={`mt-1 rounded-md shadow-sm focus:ring-indigo-500 block w-full ${detailsErrors.sale_price_list ? 'border-red-300 focus:border-red-500' : 'border-gray-300 focus:border-indigo-500'}`}
                                            >
                                                <option value="">Select...</option>
                                                {priceLists.map((pl) => (
                                                    <option key={pl.id} value={pl.id}>
                                                        {pl.discount_percent}% Discount
                                                    </option>
                                                ))}
                                            </select>
                                            {detailsErrors.sale_price_list && (
                                                <p className="mt-1 text-xs text-red-600">{detailsErrors.sale_price_list}</p>
                                            )}
                                        </>
                                    )}
                                </div>

                                <div>
                                    <label className="text-sm font-medium text-gray-500 block">Payment Terms</label>
                                    {!editingDetails ? (
                                        <div className="mt-1 text-sm text-gray-900 py-2">{customer.payment_terms || '-'}</div>
                                    ) : (
                                        <>
                                            <select
                                                value={detailsForm.customer_payment_term}
                                                onChange={(e) => setDetailsForm((prev) => ({ ...prev, customer_payment_term: e.target.value }))}
                                                className={`mt-1 rounded-md shadow-sm focus:ring-indigo-500 block w-full ${detailsErrors.customer_payment_term ? 'border-red-300 focus:border-red-500' : 'border-gray-300 focus:border-indigo-500'}`}
                                            >
                                                <option value="">Select...</option>
                                                {paymentTerms.map((pt) => (
                                                    <option key={pt.id} value={pt.id}>
                                                        {pt.name}
                                                    </option>
                                                ))}
                                            </select>
                                            {detailsErrors.customer_payment_term && (
                                                <p className="mt-1 text-xs text-red-600">{detailsErrors.customer_payment_term}</p>
                                            )}
                                        </>
                                    )}
                                </div>

                                <div>
                                    <label className="text-sm font-medium text-gray-500 block">Shipping Terms</label>
                                    {!editingDetails ? (
                                        <div className="mt-1 text-sm text-gray-900 py-2">{customer.shipping_terms || '-'}</div>
                                    ) : (
                                        <>
                                            <select
                                                value={detailsForm.shipping_terms_category_id}
                                                onChange={(e) => setDetailsForm((prev) => ({ ...prev, shipping_terms_category_id: e.target.value }))}
                                                className={`mt-1 rounded-md shadow-sm focus:ring-indigo-500 block w-full ${detailsErrors.shipping_terms_category_id ? 'border-red-300 focus:border-red-500' : 'border-gray-300 focus:border-indigo-500'}`}
                                            >
                                                <option value="">Select...</option>
                                                {shippingTerms.map((st) => (
                                                    <option key={st.id} value={st.id}>
                                                        {st.name}
                                                    </option>
                                                ))}
                                            </select>
                                            {detailsErrors.shipping_terms_category_id && (
                                                <p className="mt-1 text-xs text-red-600">{detailsErrors.shipping_terms_category_id}</p>
                                            )}
                                        </>
                                    )}
                                </div>

                                <div>
                                    <label className="text-sm font-medium text-gray-500 block">Shelf Life Requirement</label>
                                    {!editingDetails ? (
                                        <div className="mt-1 text-sm text-gray-900 py-2">
                                            {customer.shelf_life_requirement ? `${customer.shelf_life_requirement} days` : '-'}
                                        </div>
                                    ) : (
                                        <>
                                            <input
                                                type="number"
                                                value={detailsForm.shelf_life_requirement}
                                                onChange={(e) => setDetailsForm((prev) => ({ ...prev, shelf_life_requirement: e.target.value }))}
                                                min="30"
                                                max="365"
                                                placeholder="days"
                                                className={`mt-1 rounded-md shadow-sm focus:ring-indigo-500 block w-full ${detailsErrors.shelf_life_requirement ? 'border-red-300 focus:border-red-500' : 'border-gray-300 focus:border-indigo-500'}`}
                                            />
                                            {detailsErrors.shelf_life_requirement && (
                                                <p className="mt-1 text-xs text-red-600">{detailsErrors.shelf_life_requirement}</p>
                                            )}
                                        </>
                                    )}
                                </div>

                                <div>
                                    <label className="text-sm font-medium text-gray-500 block">Vendor Guide</label>
                                    {!editingDetails ? (
                                        <div className="mt-1 text-sm text-gray-900 py-2">
                                            {customer.vendor_guide ? (
                                                <a
                                                    href={customer.vendor_guide}
                                                    target="_blank"
                                                    rel="noopener noreferrer"
                                                    className="text-indigo-600 hover:text-indigo-800 block truncate"
                                                >
                                                    {customer.vendor_guide}
                                                </a>
                                            ) : (
                                                '-'
                                            )}
                                        </div>
                                    ) : (
                                        <>
                                            <input
                                                type="url"
                                                value={detailsForm.vendor_guide}
                                                onChange={(e) => setDetailsForm((prev) => ({ ...prev, vendor_guide: e.target.value }))}
                                                placeholder="https://..."
                                                className={`mt-1 rounded-md shadow-sm focus:ring-indigo-500 block w-full ${detailsErrors.vendor_guide ? 'border-red-300 focus:border-red-500' : 'border-gray-300 focus:border-indigo-500'}`}
                                            />
                                            {detailsErrors.vendor_guide && <p className="mt-1 text-xs text-red-600">{detailsErrors.vendor_guide}</p>}
                                        </>
                                    )}
                                </div>

                                <div>
                                    <label className="text-sm font-medium text-gray-500 block">Customer Type</label>
                                    {!editingDetails ? (
                                        <div className="mt-1 text-sm text-gray-900 py-2">
                                            {customerType === 'distributor' ? 'Distributor' : 'Retailer'}
                                        </div>
                                    ) : (
                                        <>
                                            <select
                                                value={customerType}
                                                onChange={(e) => handleCustomerTypeChange(e.target.value as 'retailer' | 'distributor')}
                                                disabled={changingCustomerType}
                                                className="mt-1 rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 block w-full disabled:opacity-50"
                                            >
                                                <option value="retailer">Retailer</option>
                                                <option value="distributor">Distributor</option>
                                            </select>
                                        </>
                                    )}
                                </div>

                                {customerType === 'retailer' && (
                                    <div>
                                        <label className="text-sm font-medium text-gray-500 block">Uses Broker</label>
                                        {!editingDetails ? (
                                            <div className="mt-1 text-sm text-gray-900 py-2">
                                                {customer.broker ? (
                                                    <span className="px-2 py-0.5 rounded text-xs bg-purple-100 text-purple-800 inline-flex items-center">
                                                        Yes
                                                    </span>
                                                ) : (
                                                    'No'
                                                )}
                                            </div>
                                        ) : (
                                            <select
                                                value={detailsForm.broker}
                                                onChange={(e) => {
                                                    const newValue = e.target.value;
                                                    setDetailsForm((prev) => ({ ...prev, broker: newValue }));
                                                    if (newValue === 'true' && brokerContactsForm.length === 0) {
                                                        setBrokerContactsForm([{ name: '', email: '' }]);
                                                    }
                                                }}
                                                className="mt-1 rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 block w-full"
                                            >
                                                <option value="">Select...</option>
                                                <option value="false">No</option>
                                                <option value="true">Yes</option>
                                            </select>
                                        )}
                                    </div>
                                )}

                                <div>
                                    <label className="text-sm font-medium text-gray-500 block">EDI</label>
                                    {!editingDetails ? (
                                        <div className="mt-1 text-sm text-gray-900 py-2">
                                            {customer.ar_settings?.edi ? (
                                                <span className="bg-green-100 px-2.5 py-0.5 text-xs font-medium text-green-800 inline-flex items-center rounded-full">
                                                    Yes
                                                </span>
                                            ) : (
                                                <span className="bg-gray-100 px-2.5 py-0.5 text-xs font-medium text-gray-600 inline-flex items-center rounded-full">
                                                    No
                                                </span>
                                            )}
                                        </div>
                                    ) : (
                                        <div className="mt-1 py-2">
                                            <label className="flex items-center">
                                                <input
                                                    type="checkbox"
                                                    checked={arSettingsForm.edi}
                                                    onChange={(e) => setArSettingsForm((prev) => ({ ...prev, edi: e.target.checked }))}
                                                    className="h-4 w-4 rounded border-gray-300 text-indigo-600 focus:ring-indigo-500"
                                                />
                                                <span className="ml-2 text-sm text-gray-700">Enabled</span>
                                            </label>
                                        </div>
                                    )}
                                </div>

                                <div>
                                    <label className="text-sm font-medium text-gray-500 block">Consolidated Invoicing</label>
                                    {!editingDetails ? (
                                        <div className="mt-1 text-sm text-gray-900 py-2">
                                            {customer.ar_settings?.consolidated_invoicing ? (
                                                <span className="bg-green-100 px-2.5 py-0.5 text-xs font-medium text-green-800 inline-flex items-center rounded-full">
                                                    Yes
                                                </span>
                                            ) : (
                                                <span className="bg-gray-100 px-2.5 py-0.5 text-xs font-medium text-gray-600 inline-flex items-center rounded-full">
                                                    No
                                                </span>
                                            )}
                                        </div>
                                    ) : (
                                        <div className="mt-1 py-2">
                                            <label className="flex items-center">
                                                <input
                                                    type="checkbox"
                                                    checked={arSettingsForm.consolidated_invoicing}
                                                    onChange={(e) =>
                                                        setArSettingsForm((prev) => ({ ...prev, consolidated_invoicing: e.target.checked }))
                                                    }
                                                    className="h-4 w-4 rounded border-gray-300 text-indigo-600 focus:ring-indigo-500"
                                                />
                                                <span className="ml-2 text-sm text-gray-700">Enabled</span>
                                            </label>
                                        </div>
                                    )}
                                </div>

                                <div>
                                    <label className="text-sm font-medium text-gray-500 block">Requires Customer SKUs</label>
                                    {!editingDetails ? (
                                        <div className="mt-1 text-sm text-gray-900 py-2">
                                            {customer.ar_settings?.requires_customer_skus ? (
                                                <span className="bg-yellow-100 px-2.5 py-0.5 text-xs font-medium text-yellow-800 inline-flex items-center rounded-full">
                                                    Yes
                                                </span>
                                            ) : (
                                                <span className="bg-gray-100 px-2.5 py-0.5 text-xs font-medium text-gray-600 inline-flex items-center rounded-full">
                                                    No
                                                </span>
                                            )}
                                        </div>
                                    ) : (
                                        <div className="mt-1 py-2">
                                            <label className="flex items-center">
                                                <input
                                                    type="checkbox"
                                                    checked={arSettingsForm.requires_customer_skus}
                                                    onChange={(e) =>
                                                        setArSettingsForm((prev) => ({ ...prev, requires_customer_skus: e.target.checked }))
                                                    }
                                                    className="h-4 w-4 rounded border-gray-300 text-indigo-600 focus:ring-indigo-500"
                                                />
                                                <span className="ml-2 text-sm text-gray-700">Enabled</span>
                                            </label>
                                        </div>
                                    )}
                                </div>

                                <div>
                                    <label className="text-sm font-medium text-gray-500 block">Addl Discount on Invoice Total</label>
                                    {!editingDetails ? (
                                        <div className="mt-1 text-sm text-gray-900 py-2">
                                            {customer.ar_settings?.invoice_discount ? `${customer.ar_settings.invoice_discount}%` : '-'}
                                        </div>
                                    ) : (
                                        <input
                                            type="number"
                                            value={arSettingsForm.invoice_discount}
                                            onChange={(e) => setArSettingsForm((prev) => ({ ...prev, invoice_discount: e.target.value }))}
                                            placeholder="0"
                                            min="0"
                                            max="100"
                                            step="0.01"
                                            className="mt-1 rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 block w-full"
                                        />
                                    )}
                                </div>
                            </div>
                        </div>
                    </div>

                    {/* Broker Section - Only visible when customer is retailer and broker=true */}
                    {customerType === 'retailer' && (customer.broker || detailsForm.broker === 'true') && (
                        <div className="bg-white shadow-sm sm:rounded-lg border-purple-400 overflow-hidden border-l-4">
                            <div className="p-6">
                                <div className="mb-4 flex items-center justify-between">
                                    <h3 className="text-lg font-medium text-gray-900 gap-2 flex items-center">
                                        Broker
                                        <span className="px-2 py-0.5 rounded text-xs bg-purple-100 text-purple-800 inline-flex items-center">
                                            Commission: {customer.broker_commission ?? detailsForm.broker_commission ?? 0}%
                                        </span>
                                    </h3>
                                    {!editingBroker ? (
                                        <button onClick={() => setEditingBroker(true)} className="text-gray-400 hover:text-gray-600" title="Edit">
                                            <PencilIcon />
                                        </button>
                                    ) : (
                                        <div className="gap-2 flex">
                                            <button
                                                onClick={cancelBrokerEdit}
                                                className="rounded border-gray-300 bg-white px-3 py-1 text-sm text-gray-700 hover:bg-gray-50 border"
                                            >
                                                Cancel
                                            </button>
                                            <button
                                                onClick={saveBroker}
                                                disabled={saving || !isBrokerValid()}
                                                className="rounded bg-indigo-600 px-3 py-1 text-sm text-white hover:bg-indigo-700 disabled:opacity-50"
                                            >
                                                {saving ? 'Saving...' : 'Save'}
                                            </button>
                                        </div>
                                    )}
                                </div>

                                {!editingBroker ? (
                                    <div>
                                        <div className="gap-4 mb-4 grid grid-cols-2">
                                            <div>
                                                <h4 className="text-sm font-medium text-gray-700 mb-2">Broker Company</h4>
                                                <p className="text-sm text-gray-900">{customer.broker_company_name || '-'}</p>
                                            </div>
                                            <div>
                                                <h4 className="text-sm font-medium text-gray-700 mb-2">Commission</h4>
                                                <p className="text-sm text-gray-900">{customer.broker_commission ?? 0}%</p>
                                            </div>
                                        </div>
                                        <div>
                                            <h4 className="text-sm font-medium text-gray-700 mb-2">Broker Contacts</h4>
                                            {brokerContacts && brokerContacts.length > 0 ? (
                                                <ul className="space-y-2">
                                                    {brokerContacts.map((contact) => (
                                                        <li key={contact.id} className="text-sm">
                                                            <div className="text-gray-900">{contact.name}</div>
                                                            {contact.email && <div className="text-gray-500">{contact.email}</div>}
                                                            <div className="mt-1 gap-3 text-xs text-gray-400 flex">
                                                                <span>Sent: {formatRelativeDate(contact.last_emailed_at)}</span>
                                                                <span>Received: {formatRelativeDate(contact.last_received_at)}</span>
                                                            </div>
                                                        </li>
                                                    ))}
                                                </ul>
                                            ) : (
                                                <p className="text-sm text-gray-400">No broker contacts</p>
                                            )}
                                        </div>
                                    </div>
                                ) : (
                                    <div className="space-y-4">
                                        {detailsForm.broker === 'true' && (
                                            <>
                                                <div className="gap-4 grid grid-cols-2">
                                                    <div>
                                                        <label className="text-sm font-medium text-gray-700 block">
                                                            Broker Company Name <span className="text-red-500">*</span>
                                                        </label>
                                                        <input
                                                            type="text"
                                                            value={detailsForm.broker_company_name}
                                                            onChange={(e) =>
                                                                setDetailsForm((prev) => ({ ...prev, broker_company_name: e.target.value }))
                                                            }
                                                            className={`mt-1 rounded-md shadow-sm focus:ring-indigo-500 block w-full ${
                                                                !detailsForm.broker_company_name?.trim()
                                                                    ? 'border-red-300 focus:border-red-500'
                                                                    : 'border-gray-300 focus:border-indigo-500'
                                                            }`}
                                                            placeholder="e.g., HRG Brokers"
                                                        />
                                                        {!detailsForm.broker_company_name?.trim() && (
                                                            <p className="mt-1 text-xs text-red-600">Required</p>
                                                        )}
                                                    </div>
                                                    <div>
                                                        <label className="text-sm font-medium text-gray-700 block">
                                                            Commission (%) <span className="text-red-500">*</span>
                                                        </label>
                                                        <input
                                                            type="number"
                                                            min="0"
                                                            max="100"
                                                            step="0.1"
                                                            value={detailsForm.broker_commission}
                                                            onChange={(e) =>
                                                                setDetailsForm((prev) => ({ ...prev, broker_commission: e.target.value }))
                                                            }
                                                            className={`mt-1 w-32 rounded-md shadow-sm focus:ring-indigo-500 block ${
                                                                !detailsForm.broker_commission?.trim()
                                                                    ? 'border-red-300 focus:border-red-500'
                                                                    : 'border-gray-300 focus:border-indigo-500'
                                                            }`}
                                                            placeholder="0.0"
                                                        />
                                                        {!detailsForm.broker_commission?.trim() && (
                                                            <p className="mt-1 text-xs text-red-600">Required</p>
                                                        )}
                                                    </div>
                                                </div>
                                                <div>
                                                    <div className="mb-2 flex items-center justify-between">
                                                        <h4 className="text-sm font-medium text-gray-700">
                                                            Broker Contacts <span className="text-red-500">*</span>
                                                        </h4>
                                                        <button
                                                            type="button"
                                                            onClick={addBrokerContactForm}
                                                            className="gap-1 text-sm text-indigo-600 hover:text-indigo-800 flex items-center"
                                                        >
                                                            <PlusIcon className="h-3 w-3" /> Add
                                                        </button>
                                                    </div>
                                                    {brokerContactsForm.length === 0 ? (
                                                        <p className="text-sm text-red-500">
                                                            At least one broker contact with name and email is required
                                                        </p>
                                                    ) : (
                                                        <div className="space-y-2">
                                                            {brokerContactsForm.map((contact, idx) => (
                                                                <div key={idx} className="gap-2 flex">
                                                                    <div className="flex-1">
                                                                        <input
                                                                            type="text"
                                                                            value={contact.name}
                                                                            onChange={(e) => updateBrokerContactForm(idx, 'name', e.target.value)}
                                                                            placeholder="Name *"
                                                                            className={`rounded-md text-sm shadow-sm focus:ring-indigo-500 block w-full ${
                                                                                !contact.name || contact.name.trim().length < 2
                                                                                    ? 'border-red-300 focus:border-red-500'
                                                                                    : 'border-gray-300 focus:border-indigo-500'
                                                                            }`}
                                                                        />
                                                                    </div>
                                                                    <div className="flex-1">
                                                                        <input
                                                                            type="email"
                                                                            value={contact.email}
                                                                            onChange={(e) => updateBrokerContactForm(idx, 'email', e.target.value)}
                                                                            placeholder="Email *"
                                                                            className={`rounded-md text-sm shadow-sm focus:ring-indigo-500 block w-full ${
                                                                                !contact.email || !isValidEmail(contact.email)
                                                                                    ? 'border-red-300 focus:border-red-500'
                                                                                    : 'border-gray-300 focus:border-indigo-500'
                                                                            }`}
                                                                        />
                                                                    </div>
                                                                    <button
                                                                        type="button"
                                                                        onClick={() => removeBrokerContactForm(idx)}
                                                                        className="text-red-400 hover:text-red-600"
                                                                    >
                                                                        <XIcon />
                                                                    </button>
                                                                </div>
                                                            ))}
                                                        </div>
                                                    )}
                                                </div>
                                            </>
                                        )}
                                    </div>
                                )}
                            </div>
                        </div>
                    )}

                    {/* Distributor Customers Section - Only visible when customer is distributor */}
                    {customerType === 'distributor' && (
                        <div className="bg-white shadow-sm sm:rounded-lg border-teal-400 overflow-hidden border-l-4">
                            <div className="p-6">
                                <div className="mb-4 flex items-center justify-between">
                                    <h3 className="text-lg font-medium text-gray-900 gap-2 flex items-center">
                                        Distributor Customers
                                        <span className="px-2 py-0.5 rounded text-xs bg-teal-100 text-teal-800 inline-flex items-center">
                                            {localDistributorCustomers.length} customer{localDistributorCustomers.length !== 1 ? 's' : ''}
                                        </span>
                                    </h3>
                                </div>

                                {/* Add new distributor customer */}
                                <div className="mb-4 gap-2 flex">
                                    <input
                                        type="text"
                                        value={newDistributorCustomerName}
                                        onChange={(e) => setNewDistributorCustomerName(e.target.value)}
                                        placeholder="Enter new customer name..."
                                        className="rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 text-sm flex-1"
                                        onKeyDown={(e) => e.key === 'Enter' && addDistributorCustomer()}
                                    />
                                    <button
                                        onClick={addDistributorCustomer}
                                        disabled={!newDistributorCustomerName.trim() || addingDistributorCustomer}
                                        className="rounded bg-teal-600 px-4 py-2 text-sm text-white hover:bg-teal-700 disabled:opacity-50"
                                    >
                                        {addingDistributorCustomer ? 'Adding...' : 'Add'}
                                    </button>
                                </div>

                                {/* List of distributor customers */}
                                {localDistributorCustomers.length === 0 ? (
                                    <p className="text-sm text-gray-400">No distributor customers yet. Add one above.</p>
                                ) : (
                                    <div className="space-y-3">
                                        {localDistributorCustomers.map((dc) => (
                                            <div key={dc.id} className="border-gray-200 rounded-lg border">
                                                <div
                                                    className="p-3 hover:bg-gray-50 flex cursor-pointer items-center justify-between"
                                                    onClick={() => toggleDistributorCustomerExpanded(dc.id)}
                                                >
                                                    <div className="gap-2 flex items-center">
                                                        <svg
                                                            className={`w-4 h-4 text-gray-400 transition-transform ${expandedDistributorCustomers.has(dc.id) ? 'rotate-90' : ''}`}
                                                            fill="none"
                                                            stroke="currentColor"
                                                            viewBox="0 0 24 24"
                                                        >
                                                            <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M9 5l7 7-7 7" />
                                                        </svg>
                                                        <span className="font-medium text-gray-900">{dc.name}</span>
                                                        <span className="text-xs text-gray-500">
                                                            ({dc.contacts?.length || 0} contacts, {dc.company_urls?.length || 0} domains)
                                                        </span>
                                                    </div>
                                                    <div className="gap-2 flex items-center">
                                                        <button
                                                            onClick={(e) => {
                                                                e.stopPropagation();
                                                                startEditingDC(dc);
                                                            }}
                                                            className="text-gray-400 hover:text-gray-600 p-1"
                                                            title="Edit"
                                                        >
                                                            <svg className="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                                <path
                                                                    strokeLinecap="round"
                                                                    strokeLinejoin="round"
                                                                    strokeWidth={2}
                                                                    d="M15.232 5.232l3.536 3.536m-2.036-5.036a2.5 2.5 0 113.536 3.536L6.5 21.036H3v-3.572L16.732 3.732z"
                                                                />
                                                            </svg>
                                                        </button>
                                                        <button
                                                            onClick={(e) => {
                                                                e.stopPropagation();
                                                                setDeleteConfirmDC(dc);
                                                            }}
                                                            className="text-red-400 hover:text-red-600 p-1"
                                                            title="Delete"
                                                        >
                                                            <svg className="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                                <path
                                                                    strokeLinecap="round"
                                                                    strokeLinejoin="round"
                                                                    strokeWidth={2}
                                                                    d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"
                                                                />
                                                            </svg>
                                                        </button>
                                                    </div>
                                                </div>
                                                {expandedDistributorCustomers.has(dc.id) && (
                                                    <div className="border-gray-200 p-4 bg-gray-50 border-t">
                                                        {editingDCId === dc.id ? (
                                                            /* Edit Mode */
                                                            <div className="space-y-4">
                                                                {/* Name */}
                                                                <div>
                                                                    <label className="text-sm font-medium text-gray-700 mb-1 block">Name</label>
                                                                    <input
                                                                        type="text"
                                                                        value={editingDCName}
                                                                        onChange={(e) => setEditingDCName(e.target.value)}
                                                                        className="rounded-md border-gray-300 shadow-sm focus:border-teal-500 focus:ring-teal-500 text-sm w-full"
                                                                    />
                                                                </div>
                                                                {/* Email Domains */}
                                                                <div>
                                                                    <label className="text-sm font-medium text-gray-700 mb-1 block">
                                                                        Email Domains
                                                                    </label>
                                                                    <div className="space-y-2">
                                                                        {editingDCUrls.map((url, idx) => (
                                                                            <div key={idx} className="gap-2 flex">
                                                                                <input
                                                                                    type="text"
                                                                                    value={url}
                                                                                    onChange={(e) => updateDCUrl(idx, e.target.value)}
                                                                                    placeholder="example.com"
                                                                                    className="rounded-md border-gray-300 shadow-sm focus:border-teal-500 focus:ring-teal-500 text-sm flex-1"
                                                                                />
                                                                                <button
                                                                                    onClick={() => removeDCUrl(idx)}
                                                                                    className="text-red-400 hover:text-red-600 p-2"
                                                                                    title="Remove"
                                                                                >
                                                                                    <svg
                                                                                        className="w-4 h-4"
                                                                                        fill="none"
                                                                                        stroke="currentColor"
                                                                                        viewBox="0 0 24 24"
                                                                                    >
                                                                                        <path
                                                                                            strokeLinecap="round"
                                                                                            strokeLinejoin="round"
                                                                                            strokeWidth={2}
                                                                                            d="M6 18L18 6M6 6l12 12"
                                                                                        />
                                                                                    </svg>
                                                                                </button>
                                                                            </div>
                                                                        ))}
                                                                        <button
                                                                            onClick={addDCUrl}
                                                                            className="text-sm text-teal-600 hover:text-teal-800"
                                                                        >
                                                                            + Add domain
                                                                        </button>
                                                                    </div>
                                                                </div>
                                                                {/* Save/Cancel buttons */}
                                                                <div className="gap-2 pt-2 flex justify-end">
                                                                    <button
                                                                        onClick={cancelEditingDC}
                                                                        className="px-3 py-1.5 text-sm text-gray-600 hover:text-gray-800"
                                                                    >
                                                                        Cancel
                                                                    </button>
                                                                    <button
                                                                        onClick={saveDistributorCustomer}
                                                                        disabled={savingDC || !editingDCName.trim()}
                                                                        className="px-3 py-1.5 text-sm bg-teal-600 text-white rounded hover:bg-teal-700 disabled:opacity-50"
                                                                    >
                                                                        {savingDC ? 'Saving...' : 'Save'}
                                                                    </button>
                                                                </div>
                                                            </div>
                                                        ) : (
                                                            /* View Mode */
                                                            <div className="text-sm text-gray-600">
                                                                <p className="font-medium mb-2">Email Domains:</p>
                                                                {dc.company_urls?.length > 0 ? (
                                                                    <div className="gap-1 mb-4 flex flex-wrap">
                                                                        {dc.company_urls.map((url, idx) => (
                                                                            <span
                                                                                key={idx}
                                                                                className="bg-teal-100 px-2 py-0.5 text-xs text-teal-700 inline-flex items-center rounded-full"
                                                                            >
                                                                                {url}
                                                                            </span>
                                                                        ))}
                                                                    </div>
                                                                ) : (
                                                                    <p className="text-gray-400 text-xs mb-4">No domains configured</p>
                                                                )}
                                                            </div>
                                                        )}

                                                        {/* Contacts Section - Always visible */}
                                                        <div className="mt-4 pt-4 border-gray-200 border-t">
                                                            <p className="font-medium text-sm text-gray-700 mb-3">
                                                                Contacts ({dc.contacts?.length || 0})
                                                            </p>

                                                            {/* Add new contact */}
                                                            <div className="gap-2 mb-3 flex">
                                                                <input
                                                                    type="email"
                                                                    value={newDCContactEmail}
                                                                    onChange={(e) => setNewDCContactEmail(e.target.value)}
                                                                    placeholder="Enter email address..."
                                                                    className="rounded-md border-gray-300 shadow-sm focus:border-teal-500 focus:ring-teal-500 text-sm flex-1"
                                                                    onKeyDown={(e) => e.key === 'Enter' && addDistributorCustomerContact(dc.id)}
                                                                />
                                                                <button
                                                                    onClick={() => addDistributorCustomerContact(dc.id)}
                                                                    disabled={!newDCContactEmail.trim() || addingDCContact}
                                                                    className="px-3 py-1.5 text-sm bg-teal-600 text-white rounded hover:bg-teal-700 disabled:opacity-50"
                                                                >
                                                                    {addingDCContact ? 'Adding...' : 'Add'}
                                                                </button>
                                                            </div>

                                                            {/* Contact list */}
                                                            {dc.contacts?.length > 0 ? (
                                                                <div className="space-y-2">
                                                                    {dc.contacts.map((contact) => (
                                                                        <div
                                                                            key={contact.id}
                                                                            className="bg-white rounded border-gray-200 p-2 flex items-center justify-between border"
                                                                        >
                                                                            {editingDCContactId === contact.id ? (
                                                                                /* Editing contact */
                                                                                <div className="gap-2 flex flex-1 items-center">
                                                                                    <input
                                                                                        type="text"
                                                                                        value={editingDCContactName}
                                                                                        onChange={(e) => setEditingDCContactName(e.target.value)}
                                                                                        placeholder="Name"
                                                                                        className="rounded border-gray-300 text-sm flex-1"
                                                                                    />
                                                                                    <span className="text-xs text-gray-500">{contact.email}</span>
                                                                                    <select
                                                                                        value={editingDCContactType}
                                                                                        onChange={(e) => setEditingDCContactType(e.target.value)}
                                                                                        className="rounded border-gray-300 text-xs"
                                                                                    >
                                                                                        <option value="uncategorized">Uncategorized</option>
                                                                                        <option value="buyer">Buyer</option>
                                                                                        <option value="accounts_payable">Accounts Payable</option>
                                                                                        <option value="other">Other</option>
                                                                                    </select>
                                                                                    <button
                                                                                        onClick={() => saveDistributorCustomerContact(dc.id)}
                                                                                        disabled={savingDCContact}
                                                                                        className="text-teal-600 hover:text-teal-800 p-1"
                                                                                    >
                                                                                        <svg
                                                                                            className="w-4 h-4"
                                                                                            fill="none"
                                                                                            stroke="currentColor"
                                                                                            viewBox="0 0 24 24"
                                                                                        >
                                                                                            <path
                                                                                                strokeLinecap="round"
                                                                                                strokeLinejoin="round"
                                                                                                strokeWidth={2}
                                                                                                d="M5 13l4 4L19 7"
                                                                                            />
                                                                                        </svg>
                                                                                    </button>
                                                                                    <button
                                                                                        onClick={cancelEditingDCContact}
                                                                                        className="text-gray-400 hover:text-gray-600 p-1"
                                                                                    >
                                                                                        <svg
                                                                                            className="w-4 h-4"
                                                                                            fill="none"
                                                                                            stroke="currentColor"
                                                                                            viewBox="0 0 24 24"
                                                                                        >
                                                                                            <path
                                                                                                strokeLinecap="round"
                                                                                                strokeLinejoin="round"
                                                                                                strokeWidth={2}
                                                                                                d="M6 18L18 6M6 6l12 12"
                                                                                            />
                                                                                        </svg>
                                                                                    </button>
                                                                                </div>
                                                                            ) : (
                                                                                /* Viewing contact */
                                                                                <>
                                                                                    <div className="flex-1">
                                                                                        <span className="text-sm text-gray-900">
                                                                                            {contact.name || (
                                                                                                <span className="text-gray-400 italic">No name</span>
                                                                                            )}
                                                                                        </span>
                                                                                        <span className="text-sm text-gray-500 ml-2">
                                                                                            {contact.email}
                                                                                        </span>
                                                                                        <span
                                                                                            className={`ml-2 px-2 py-0.5 text-xs font-medium inline-flex items-center rounded-full ${
                                                                                                contact.type === 'buyer'
                                                                                                    ? 'bg-blue-100 text-blue-800'
                                                                                                    : contact.type === 'accounts_payable'
                                                                                                      ? 'bg-purple-100 text-purple-800'
                                                                                                      : contact.type === 'other'
                                                                                                        ? 'bg-gray-100 text-gray-800'
                                                                                                        : 'bg-yellow-100 text-yellow-800'
                                                                                            }`}
                                                                                        >
                                                                                            {contact.type === 'accounts_payable'
                                                                                                ? 'AP'
                                                                                                : contact.type}
                                                                                        </span>
                                                                                    </div>
                                                                                    <div className="gap-1 flex items-center">
                                                                                        <button
                                                                                            onClick={() => startEditingDCContact(contact)}
                                                                                            className="text-gray-400 hover:text-gray-600 p-1"
                                                                                            title="Edit"
                                                                                        >
                                                                                            <svg
                                                                                                className="w-4 h-4"
                                                                                                fill="none"
                                                                                                stroke="currentColor"
                                                                                                viewBox="0 0 24 24"
                                                                                            >
                                                                                                <path
                                                                                                    strokeLinecap="round"
                                                                                                    strokeLinejoin="round"
                                                                                                    strokeWidth={2}
                                                                                                    d="M15.232 5.232l3.536 3.536m-2.036-5.036a2.5 2.5 0 113.536 3.536L6.5 21.036H3v-3.572L16.732 3.732z"
                                                                                                />
                                                                                            </svg>
                                                                                        </button>
                                                                                        <button
                                                                                            onClick={() =>
                                                                                                deleteDistributorCustomerContact(dc.id, contact.id)
                                                                                            }
                                                                                            disabled={deletingDCContactId === contact.id}
                                                                                            className="text-red-400 hover:text-red-600 p-1 disabled:opacity-50"
                                                                                            title="Delete"
                                                                                        >
                                                                                            <svg
                                                                                                className="w-4 h-4"
                                                                                                fill="none"
                                                                                                stroke="currentColor"
                                                                                                viewBox="0 0 24 24"
                                                                                            >
                                                                                                <path
                                                                                                    strokeLinecap="round"
                                                                                                    strokeLinejoin="round"
                                                                                                    strokeWidth={2}
                                                                                                    d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"
                                                                                                />
                                                                                            </svg>
                                                                                        </button>
                                                                                    </div>
                                                                                </>
                                                                            )}
                                                                        </div>
                                                                    ))}
                                                                </div>
                                                            ) : (
                                                                <p className="text-gray-400 text-xs">
                                                                    No contacts yet. Add one above or they will be discovered via Gmail sync.
                                                                </p>
                                                            )}
                                                        </div>
                                                    </div>
                                                )}
                                            </div>
                                        ))}
                                    </div>
                                )}
                            </div>
                        </div>
                    )}

                    {/* Delete Distributor Customer Confirmation Modal */}
                    {deleteConfirmDC && (
                        <div className="inset-0 fixed z-50 overflow-y-auto">
                            <div className="p-4 flex min-h-full items-center justify-center">
                                <div className="inset-0 bg-gray-500 bg-opacity-75 fixed" onClick={() => setDeleteConfirmDC(null)} />
                                <div className="bg-white rounded-lg shadow-xl max-w-md p-6 relative w-full">
                                    <h3 className="text-lg font-medium text-gray-900 mb-4">Delete Distributor Customer?</h3>
                                    <p className="text-sm text-gray-600 mb-6">
                                        Are you sure you want to delete <strong>{deleteConfirmDC.name}</strong>? This will permanently remove all
                                        their contacts and email history.
                                    </p>
                                    <div className="gap-3 flex justify-end">
                                        <button
                                            onClick={() => setDeleteConfirmDC(null)}
                                            className="rounded border-gray-300 px-4 py-2 text-sm text-gray-700 hover:bg-gray-50 border"
                                            disabled={deletingDC}
                                        >
                                            Cancel
                                        </button>
                                        <button
                                            onClick={deleteDistributorCustomer}
                                            disabled={deletingDC}
                                            className="rounded bg-red-600 px-4 py-2 text-sm text-white hover:bg-red-700 disabled:opacity-50"
                                        >
                                            {deletingDC ? 'Deleting...' : 'Delete'}
                                        </button>
                                    </div>
                                </div>
                            </div>
                        </div>
                    )}

                    {/* Contacts */}
                    <div className="bg-white shadow-sm sm:rounded-lg overflow-hidden">
                        <div className="p-6">
                            <div className="mb-4 flex items-center justify-between">
                                <h3 className="text-lg font-medium text-gray-900">Contacts</h3>
                                {!editingContacts ? (
                                    <button onClick={() => setEditingContacts(true)} className="text-gray-400 hover:text-gray-600" title="Edit">
                                        <PencilIcon />
                                    </button>
                                ) : (
                                    <div className="gap-2 flex">
                                        <button
                                            onClick={cancelContactsEdit}
                                            className="rounded border-gray-300 px-3 py-1 text-sm text-gray-600 hover:bg-gray-50 border"
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
                                <>
                                    <div className="gap-6 sm:grid-cols-3 grid">
                                        {/* Buyers */}
                                        <div>
                                            <h4 className="mb-2 text-sm font-medium text-gray-700">Buyers</h4>
                                            {(buyerContacts && buyerContacts.length > 0) || (localBuyers && localBuyers.length > 0) ? (
                                                <ul className="space-y-3">
                                                    {buyerContacts?.map((contact, idx) => (
                                                        <li key={`fulfil-${idx}`} className="text-sm">
                                                            <div className="text-gray-900">{contact.name}</div>
                                                            {contact.email && <div className="text-gray-500">{contact.email}</div>}
                                                            <div className="mt-1 gap-3 text-xs text-gray-400 flex">
                                                                <span
                                                                    title={
                                                                        contact.last_emailed_at
                                                                            ? new Date(contact.last_emailed_at).toLocaleString()
                                                                            : undefined
                                                                    }
                                                                >
                                                                    Sent: {formatRelativeDate(contact.last_emailed_at)}
                                                                </span>
                                                                <span
                                                                    title={
                                                                        contact.last_received_at
                                                                            ? new Date(contact.last_received_at).toLocaleString()
                                                                            : undefined
                                                                    }
                                                                >
                                                                    Received: {formatRelativeDate(contact.last_received_at)}
                                                                </span>
                                                            </div>
                                                        </li>
                                                    ))}
                                                    {localBuyers?.map((contact) => (
                                                        <li key={`local-${contact.id}`} className="text-sm bg-blue-50 rounded p-2 -mx-2">
                                                            <div className="flex items-start justify-between">
                                                                <div>
                                                                    <div className="text-gray-900">
                                                                        {contact.name || <span className="text-gray-400 italic">No name</span>}
                                                                    </div>
                                                                    <div className="text-gray-500">{contact.email}</div>
                                                                    <div className="mt-1 gap-3 text-xs text-gray-400 flex">
                                                                        <span>Sent: {formatRelativeDate(contact.last_emailed_at ?? null)}</span>
                                                                        <span>Received: {formatRelativeDate(contact.last_received_at ?? null)}</span>
                                                                    </div>
                                                                </div>
                                                                <span className="text-xs text-blue-600 bg-blue-100 px-1.5 py-0.5 rounded">Local</span>
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
                                            {(customer.accounts_payable && customer.accounts_payable.length > 0) ||
                                            (localAP && localAP.length > 0) ? (
                                                <ul className="space-y-2">
                                                    {customer.accounts_payable?.map((contact, idx) => (
                                                        <li key={`fulfil-${idx}`} className="text-sm">
                                                            <div className="text-gray-900">{contact.name}</div>
                                                            {contact.value && (
                                                                <div className="text-gray-500 break-all">
                                                                    {contact.value.startsWith('http') ? (
                                                                        <a
                                                                            href={contact.value}
                                                                            target="_blank"
                                                                            rel="noopener noreferrer"
                                                                            className="text-indigo-600 hover:text-indigo-800"
                                                                        >
                                                                            {contact.value}
                                                                        </a>
                                                                    ) : (
                                                                        contact.value
                                                                    )}
                                                                </div>
                                                            )}
                                                        </li>
                                                    ))}
                                                    {localAP?.map((contact) => (
                                                        <li key={`local-${contact.id}`} className="text-sm bg-blue-50 rounded p-2 -mx-2">
                                                            <div className="flex items-start justify-between">
                                                                <div>
                                                                    <div className="text-gray-900">
                                                                        {contact.name || <span className="text-gray-400 italic">No name</span>}
                                                                    </div>
                                                                    <div className="text-gray-500">{contact.email}</div>
                                                                </div>
                                                                <span className="text-xs text-blue-600 bg-blue-100 px-1.5 py-0.5 rounded">Local</span>
                                                            </div>
                                                        </li>
                                                    ))}
                                                </ul>
                                            ) : (
                                                <p className="text-sm text-gray-400">-</p>
                                            )}
                                        </div>

                                        {/* Other */}
                                        <div>
                                            <h4 className="mb-2 text-sm font-medium text-gray-700">Other</h4>
                                            {(customer.other && customer.other.length > 0) || (localOther && localOther.length > 0) ? (
                                                <ul className="space-y-2">
                                                    {customer.other?.map((contact, idx) => (
                                                        <li key={`fulfil-${idx}`} className="text-sm">
                                                            <div className="gap-2 flex items-center">
                                                                <span className="text-gray-900">{contact.name}</span>
                                                                {contact.function && (
                                                                    <span className="px-1.5 py-0.5 text-xs bg-gray-100 text-gray-600 rounded">
                                                                        {contact.function}
                                                                    </span>
                                                                )}
                                                            </div>
                                                            {contact.email && <div className="text-gray-500">{contact.email}</div>}
                                                        </li>
                                                    ))}
                                                    {localOther?.map((contact) => (
                                                        <li key={`local-${contact.id}`} className="text-sm bg-blue-50 rounded p-2 -mx-2">
                                                            <div className="flex items-start justify-between">
                                                                <div>
                                                                    <div className="text-gray-900">
                                                                        {contact.name || <span className="text-gray-400 italic">No name</span>}
                                                                    </div>
                                                                    <div className="text-gray-500">{contact.email}</div>
                                                                </div>
                                                                <span className="text-xs text-blue-600 bg-blue-100 px-1.5 py-0.5 rounded">Local</span>
                                                            </div>
                                                        </li>
                                                    ))}
                                                </ul>
                                            ) : (
                                                <p className="text-sm text-gray-400">-</p>
                                            )}
                                        </div>
                                    </div>

                                    {/* Uncategorized Contacts Section */}
                                    {uncategorizedContacts && uncategorizedContacts.length > 0 && (
                                        <div className="mt-6 pt-6 border-gray-200 border-t">
                                            <h4 className="mb-3 text-sm font-medium text-gray-700 gap-2 flex items-center">
                                                <span className="px-2 py-0.5 rounded text-xs bg-yellow-100 text-yellow-800 inline-flex items-center">
                                                    Uncategorized
                                                </span>
                                                <span className="text-gray-500 font-normal">
                                                    ({uncategorizedContacts.length} discovered from emails)
                                                </span>
                                            </h4>
                                            <div className="gap-3 sm:grid-cols-2 lg:grid-cols-3 grid">
                                                {uncategorizedContacts.map((contact) => (
                                                    <div key={contact.id} className="p-3 bg-yellow-50 rounded-lg border-yellow-100 border">
                                                        <div className="flex items-start justify-between">
                                                            <div className="min-w-0 flex-1">
                                                                <div className="text-sm text-gray-900 font-medium truncate">
                                                                    {contact.name || <span className="text-gray-400 italic">No name</span>}
                                                                </div>
                                                                <div className="text-sm text-gray-500 truncate">{contact.email}</div>
                                                                <div className="mt-1 gap-3 text-xs text-gray-400 flex">
                                                                    <span>Sent: {formatRelativeDate(contact.last_emailed_at)}</span>
                                                                    <span>Received: {formatRelativeDate(contact.last_received_at)}</span>
                                                                </div>
                                                            </div>
                                                            <div className="ml-2 gap-1 flex flex-col">
                                                                <select
                                                                    value={categorizingContacts[contact.id] || ''}
                                                                    onChange={(e) => {
                                                                        const type = e.target.value;
                                                                        if (type) {
                                                                            setCategorizingContacts((prev) => ({ ...prev, [contact.id]: type }));
                                                                            categorizeContact(contact.id, type);
                                                                        }
                                                                    }}
                                                                    className="text-xs rounded border-gray-300 py-1 pl-2 pr-6 focus:border-indigo-500 focus:ring-indigo-500"
                                                                >
                                                                    <option value="">Categorize...</option>
                                                                    <option value="buyer">Buyer</option>
                                                                    <option value="accounts_payable">Accounts Payable</option>
                                                                    <option value="other">Other</option>
                                                                </select>
                                                                <button
                                                                    onClick={() => deleteLocalContact(contact.id)}
                                                                    className="text-xs text-red-500 hover:text-red-700"
                                                                >
                                                                    Delete
                                                                </button>
                                                            </div>
                                                        </div>
                                                    </div>
                                                ))}
                                            </div>
                                        </div>
                                    )}
                                </>
                            ) : (
                                <div className="space-y-6">
                                    {/* Buyers Edit */}
                                    <div>
                                        <div className="mb-2 flex items-center justify-between">
                                            <h4 className="text-sm font-medium text-gray-700">Buyers</h4>
                                            <button
                                                type="button"
                                                onClick={addBuyer}
                                                className="gap-1 text-sm text-indigo-600 hover:text-indigo-800 flex items-center"
                                            >
                                                <PlusIcon className="h-3 w-3" /> Add
                                            </button>
                                        </div>
                                        {contactsErrors.buyers && <p className="mb-2 text-xs text-red-600">{contactsErrors.buyers}</p>}
                                        <div className="space-y-2">
                                            {contactsForm.buyers.map((buyer, idx) => (
                                                <div key={idx} className="gap-2 flex">
                                                    <div className="flex-1">
                                                        <input
                                                            type="text"
                                                            value={buyer.name}
                                                            onChange={(e) => updateBuyer(idx, 'name', e.target.value)}
                                                            placeholder="Name"
                                                            className={`rounded-md text-sm shadow-sm focus:ring-indigo-500 block w-full ${contactsErrors[`buyers.${idx}.name`] ? 'border-red-300' : 'border-gray-300'}`}
                                                        />
                                                    </div>
                                                    <div className="flex-1">
                                                        <input
                                                            type="email"
                                                            value={buyer.email}
                                                            onChange={(e) => updateBuyer(idx, 'email', e.target.value)}
                                                            placeholder="Email"
                                                            className={`rounded-md text-sm shadow-sm focus:ring-indigo-500 block w-full ${contactsErrors[`buyers.${idx}.email`] ? 'border-red-300' : 'border-gray-300'}`}
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
                                        <h4 className="mb-2 text-sm font-medium text-gray-700">
                                            Accounts Payable <span className="text-red-500">*</span>
                                        </h4>

                                        {/* AP Method Selection */}
                                        <div className="mb-3">
                                            <p className="text-xs text-gray-500 mb-2">Does customer use an AP portal?</p>
                                            <div className="gap-4 flex">
                                                <label className="flex items-center">
                                                    <input
                                                        type="radio"
                                                        name="ap_method_edit"
                                                        checked={contactsForm.ap_method === 'inbox'}
                                                        onChange={() => setContactsForm((prev) => ({ ...prev, ap_method: 'inbox' }))}
                                                        className="h-4 w-4 text-indigo-600 focus:ring-indigo-500 border-gray-300"
                                                    />
                                                    <span className="ml-2 text-sm text-gray-700">No - Email only</span>
                                                </label>
                                                <label className="flex items-center">
                                                    <input
                                                        type="radio"
                                                        name="ap_method_edit"
                                                        checked={contactsForm.ap_method === 'portal'}
                                                        onChange={() => setContactsForm((prev) => ({ ...prev, ap_method: 'portal' }))}
                                                        className="h-4 w-4 text-indigo-600 focus:ring-indigo-500 border-gray-300"
                                                    />
                                                    <span className="ml-2 text-sm text-gray-700">Yes - Uses web portal</span>
                                                </label>
                                            </div>
                                        </div>

                                        {/* Portal URL (when portal selected) */}
                                        {contactsForm.ap_method === 'portal' && (
                                            <div className="mb-3 p-3 bg-gray-50 rounded-md">
                                                <label className="text-xs font-medium text-gray-700 mb-1 block">
                                                    Portal URL <span className="text-red-500">*</span>
                                                </label>
                                                <input
                                                    type="url"
                                                    value={contactsForm.ap_portal_url}
                                                    onChange={(e) => setContactsForm((prev) => ({ ...prev, ap_portal_url: e.target.value }))}
                                                    placeholder="https://vendor-portal.example.com"
                                                    className={`rounded-md text-sm shadow-sm focus:ring-indigo-500 block w-full ${contactsErrors.ap_portal_url ? 'border-red-300' : 'border-gray-300'}`}
                                                />
                                                {contactsErrors.ap_portal_url && (
                                                    <p className="mt-1 text-sm text-red-600">{contactsErrors.ap_portal_url}</p>
                                                )}
                                            </div>
                                        )}

                                        {/* AP Contacts (always required) */}
                                        <div>
                                            <div className="mb-2 flex items-center justify-between">
                                                <span className="text-xs text-gray-500">
                                                    At least one AP contact required
                                                    {contactsForm.ap_method === 'portal' ? ' (in addition to portal)' : ''}
                                                </span>
                                                <button
                                                    type="button"
                                                    onClick={addAP}
                                                    className="gap-1 text-sm text-indigo-600 hover:text-indigo-800 flex items-center"
                                                >
                                                    <PlusIcon className="h-3 w-3" /> Add Contact
                                                </button>
                                            </div>
                                            {contactsErrors.accounts_payable && (
                                                <p className="mb-2 text-sm text-red-600">{contactsErrors.accounts_payable}</p>
                                            )}
                                            <div className="space-y-2">
                                                {contactsForm.accounts_payable.map((ap, idx) => (
                                                    <div key={idx} className="gap-2 flex">
                                                        <div className="flex-1">
                                                            <input
                                                                type="text"
                                                                value={ap.name}
                                                                onChange={(e) => updateAP(idx, 'name', e.target.value)}
                                                                placeholder="Name"
                                                                className={`rounded-md text-sm shadow-sm focus:ring-indigo-500 block w-full ${contactsErrors[`accounts_payable.${idx}.name`] ? 'border-red-300' : 'border-gray-300'}`}
                                                            />
                                                        </div>
                                                        <div className="flex-1">
                                                            <input
                                                                type="email"
                                                                value={ap.value}
                                                                onChange={(e) => updateAP(idx, 'value', e.target.value)}
                                                                placeholder="Email"
                                                                className={`rounded-md text-sm shadow-sm focus:ring-indigo-500 block w-full ${contactsErrors[`accounts_payable.${idx}.value`] ? 'border-red-300' : 'border-gray-300'}`}
                                                            />
                                                        </div>
                                                        {contactsForm.accounts_payable.length > 1 && (
                                                            <button
                                                                type="button"
                                                                onClick={() => removeAP(idx)}
                                                                className="text-red-400 hover:text-red-600"
                                                            >
                                                                <XIcon />
                                                            </button>
                                                        )}
                                                    </div>
                                                ))}
                                            </div>
                                        </div>
                                    </div>

                                    {/* Other Edit */}
                                    <div>
                                        <div className="mb-2 flex items-center justify-between">
                                            <h4 className="text-sm font-medium text-gray-700">Other</h4>
                                            <button
                                                type="button"
                                                onClick={addOther}
                                                className="gap-1 text-sm text-indigo-600 hover:text-indigo-800 flex items-center"
                                            >
                                                <PlusIcon className="h-3 w-3" /> Add
                                            </button>
                                        </div>
                                        {contactsForm.other.length === 0 ? (
                                            <p className="text-sm text-gray-400">No other contacts</p>
                                        ) : (
                                            <div className="space-y-2">
                                                {contactsForm.other.map((other, idx) => (
                                                    <div key={idx} className="gap-2 flex">
                                                        <div className="flex-1">
                                                            <input
                                                                type="text"
                                                                value={other.name}
                                                                onChange={(e) => updateOther(idx, 'name', e.target.value)}
                                                                placeholder="Name"
                                                                className={`rounded-md text-sm shadow-sm focus:ring-indigo-500 block w-full ${contactsErrors[`other.${idx}.name`] ? 'border-red-300' : 'border-gray-300'}`}
                                                            />
                                                        </div>
                                                        <div className="w-28">
                                                            <input
                                                                type="text"
                                                                value={other.function || ''}
                                                                onChange={(e) => updateOther(idx, 'function', e.target.value)}
                                                                placeholder="Function"
                                                                className="rounded-md text-sm shadow-sm focus:ring-indigo-500 border-gray-300 block w-full"
                                                            />
                                                        </div>
                                                        <div className="flex-1">
                                                            <input
                                                                type="email"
                                                                value={other.email}
                                                                onChange={(e) => updateOther(idx, 'email', e.target.value)}
                                                                placeholder="Email"
                                                                className={`rounded-md text-sm shadow-sm focus:ring-indigo-500 block w-full ${contactsErrors[`other.${idx}.email`] ? 'border-red-300' : 'border-gray-300'}`}
                                                            />
                                                        </div>
                                                        <button
                                                            type="button"
                                                            onClick={() => removeOther(idx)}
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
                    <div className="bg-white shadow-sm sm:rounded-lg overflow-hidden">
                        <div className="p-6">
                            <div className="mb-4 flex items-center justify-between">
                                <div>
                                    <h3 className="text-lg font-medium text-gray-900">Company Domains</h3>
                                    <p className="text-sm text-gray-500">Email domains used for Gmail sync matching</p>
                                </div>
                                {!editingCompanyUrls ? (
                                    <button onClick={() => setEditingCompanyUrls(true)} className="text-gray-400 hover:text-gray-600" title="Edit">
                                        <PencilIcon />
                                    </button>
                                ) : (
                                    <div className="gap-2 flex">
                                        <button
                                            onClick={cancelCompanyUrlsEdit}
                                            className="rounded border-gray-300 px-3 py-1 text-sm text-gray-600 hover:bg-gray-50 border"
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
                                        <div className="gap-2 flex flex-wrap">
                                            {customer.company_urls.map((url, idx) => (
                                                <span
                                                    key={idx}
                                                    className="bg-gray-100 px-3 py-1 text-sm text-gray-700 inline-flex items-center rounded-full"
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
                                        <div key={idx} className="gap-2 flex">
                                            <input
                                                type="text"
                                                value={url}
                                                onChange={(e) => updateCompanyUrl(idx, e.target.value)}
                                                placeholder="e.g., example.com"
                                                className="rounded-md border-gray-300 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500 block flex-1"
                                            />
                                            <button type="button" onClick={() => removeCompanyUrl(idx)} className="text-red-400 hover:text-red-600">
                                                <XIcon />
                                            </button>
                                        </div>
                                    ))}
                                    <button
                                        type="button"
                                        onClick={addCompanyUrl}
                                        className="gap-1 text-sm text-indigo-600 hover:text-indigo-800 flex items-center"
                                    >
                                        <PlusIcon className="h-3 w-3" /> Add Domain
                                    </button>
                                </div>
                            )}
                        </div>
                    </div>

                    {/* Email Activity */}
                    <EmailActivityPanel entityType="customer" entityId={customer.id} />

                    {/* Revenue Chart */}
                    <div className="bg-white shadow-sm sm:rounded-lg overflow-hidden">
                        <div className="p-6">
                            <div className="mb-4 flex items-center justify-between">
                                <h3 className="text-lg font-medium text-gray-900">Revenue</h3>
                                <div className="gap-6 flex text-right">
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
                                            {revenueChange >= 0 ? '+ ' : '- '}
                                            {formatCurrency(Math.abs(revenueChange))}
                                        </span>
                                    </div>
                                </div>
                            </div>
                            {/* Legend */}
                            <div className="mb-2 gap-4 flex justify-end">
                                <div className="gap-1 flex items-center">
                                    <div className="h-3 w-3 rounded bg-indigo-500"></div>
                                    <span className="text-xs text-gray-500">Current Year</span>
                                </div>
                                <div className="gap-1 flex items-center">
                                    <div className="h-3 w-3 rounded bg-gray-300"></div>
                                    <span className="text-xs text-gray-500">Prior Year</span>
                                </div>
                            </div>
                            <div className="flex" style={{ height: '220px' }}>
                                {/* Y-Axis */}
                                <div className="pr-2 flex flex-col justify-between text-right" style={{ width: '60px' }}>
                                    {yAxisValues.map((value, idx) => (
                                        <span key={idx} className="text-xs text-gray-500">
                                            {formatCompactCurrency(value)}
                                        </span>
                                    ))}
                                </div>

                                {/* Chart Area */}
                                <div className="flex flex-1 flex-col">
                                    <div className="gap-2 border-gray-200 relative flex flex-1 items-end border-b border-l">
                                        {/* Horizontal grid lines */}
                                        {yAxisValues.slice(1, -1).map((_, idx) => (
                                            <div
                                                key={idx}
                                                className="left-0 right-0 border-gray-100 absolute border-t"
                                                style={{ bottom: `${((idx + 1) / yAxisSteps) * 100}%` }}
                                            />
                                        ))}

                                        {/* Bars - grouped by month */}
                                        {monthlyRevenue.map((month, idx) => {
                                            const currentHeightPercent = (month.revenue / maxRevenue) * 100;
                                            const priorHeightPercent = (month.prior_year_revenue / maxRevenue) * 100;
                                            return (
                                                <div key={idx} className="gap-0.5 relative z-10 flex h-full flex-1 items-end justify-center">
                                                    {/* Prior Year Bar */}
                                                    <div
                                                        className="bg-gray-300 rounded-t w-2/5"
                                                        style={{
                                                            height: `${priorHeightPercent}%`,
                                                            minHeight: month.prior_year_revenue > 0 ? '4px' : '0',
                                                        }}
                                                        title={`${month.prior_year_month}: ${formatCurrency(month.prior_year_revenue)}`}
                                                    />
                                                    {/* Current Year Bar */}
                                                    <div
                                                        className="bg-indigo-500 rounded-t w-2/5"
                                                        style={{
                                                            height: `${currentHeightPercent}%`,
                                                            minHeight: month.revenue > 0 ? '4px' : '0',
                                                        }}
                                                        title={`${month.month}: ${formatCurrency(month.revenue)}`}
                                                    />
                                                </div>
                                            );
                                        })}
                                    </div>

                                    {/* X-Axis Labels */}
                                    <div className="gap-2 pt-1 flex">
                                        {monthlyRevenue.map((month, idx) => (
                                            <div key={idx} className="flex-1 text-center">
                                                <span className="text-xs text-gray-500">{month.month_name}</span>
                                            </div>
                                        ))}
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div className="gap-6 lg:grid-cols-2 grid">
                        {/* Top Products */}
                        <div className="bg-white shadow-sm sm:rounded-lg overflow-hidden">
                            <div className="p-6">
                                <h3 className="mb-4 text-lg font-medium text-gray-900">Top Products (T12M)</h3>
                                {topProducts.length > 0 ? (
                                    <table className="divide-gray-200 min-w-full divide-y">
                                        <thead>
                                            <tr>
                                                <th className="pb-2 text-xs font-medium text-gray-500 text-left uppercase">SKU</th>
                                                <th className="pb-2 text-xs font-medium text-gray-500 text-right uppercase">Units</th>
                                                <th className="pb-2 text-xs font-medium text-gray-500 text-right uppercase">Revenue</th>
                                            </tr>
                                        </thead>
                                        <tbody className="divide-gray-200 divide-y">
                                            {topProducts.map((product, idx) => (
                                                <tr key={idx}>
                                                    <td className="py-2 text-sm text-gray-900">{product.sku}</td>
                                                    <td className="py-2 text-sm text-gray-500 text-right">{product.units_sold}</td>
                                                    <td className="py-2 text-sm text-gray-900 text-right">{formatCurrency(product.revenue)}</td>
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
                        <div className="bg-white shadow-sm sm:rounded-lg overflow-hidden">
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
                                    <table className="divide-gray-200 min-w-full divide-y">
                                        <thead>
                                            <tr>
                                                <th className="pb-2 text-xs font-medium text-gray-500 text-left uppercase">PO #</th>
                                                <th className="pb-2 text-xs font-medium text-gray-500 text-left uppercase">Ship Date</th>
                                                <th className="pb-2 text-xs font-medium text-gray-500 text-right uppercase">Amount</th>
                                                <th className="pb-2 text-xs font-medium text-gray-500 text-right uppercase">Status</th>
                                            </tr>
                                        </thead>
                                        <tbody className="divide-gray-200 divide-y">
                                            {upcomingOrders.map((order) => {
                                                const shipStatus = getShipmentStatus(order.shipping_end_date);
                                                return (
                                                    <tr key={order.id}>
                                                        <td className="py-2 text-sm text-gray-900">{order.reference || '-'}</td>
                                                        <td className="py-2 text-sm text-gray-500">{formatDate(order.shipping_end_date)}</td>
                                                        <td className="py-2 text-sm text-gray-900 text-right">
                                                            {formatCurrency(order.total_amount)}
                                                        </td>
                                                        <td className={`py-2 text-sm text-right ${shipStatus.className}`}>{shipStatus.text}</td>
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
                    <div className="bg-white shadow-sm sm:rounded-lg overflow-hidden">
                        <div className="p-6">
                            <div className="mb-4 flex items-center justify-between">
                                <h3 className="text-lg font-medium text-gray-900">Outstanding Invoices</h3>
                                {outstandingInvoices.length > 0 && (
                                    <div className="gap-6 flex text-right">
                                        <div>
                                            <span className="text-sm text-gray-500">Total Outstanding: </span>
                                            <span className="font-semibold text-gray-900">
                                                {formatCurrency(outstandingInvoices.reduce((sum, i) => sum + i.balance, 0))}
                                            </span>
                                        </div>
                                        <div>
                                            <span className="text-sm text-gray-500">Overdue: </span>
                                            <span className="font-semibold text-orange-600">
                                                {formatCurrency(
                                                    outstandingInvoices.filter((i) => i.days_overdue > 0).reduce((sum, i) => sum + i.balance, 0),
                                                )}
                                            </span>
                                        </div>
                                        <div>
                                            <span className="text-sm text-gray-500">30+ Days: </span>
                                            <span className="font-semibold text-red-600">
                                                {formatCurrency(
                                                    outstandingInvoices.filter((i) => i.days_overdue > 30).reduce((sum, i) => sum + i.balance, 0),
                                                )}
                                            </span>
                                        </div>
                                    </div>
                                )}
                            </div>
                            {outstandingInvoices.length > 0 ? (
                                <table className="divide-gray-200 min-w-full divide-y">
                                    <thead>
                                        <tr>
                                            <th className="pb-2 text-xs font-medium text-gray-500 text-left uppercase">Invoice #</th>
                                            <th className="pb-2 text-xs font-medium text-gray-500 text-left uppercase">Due Date</th>
                                            <th className="pb-2 text-xs font-medium text-gray-500 text-right uppercase">Total</th>
                                            <th className="pb-2 text-xs font-medium text-gray-500 text-right uppercase">Balance</th>
                                            <th className="pb-2 text-xs font-medium text-gray-500 text-right uppercase">Status</th>
                                            <th className="pb-2 text-xs font-medium text-gray-500 text-right uppercase">Actions</th>
                                        </tr>
                                    </thead>
                                    <tbody className="divide-gray-200 divide-y">
                                        {outstandingInvoices.map((invoice) => {
                                            const status = getInvoiceStatus(invoice.days_overdue);
                                            return (
                                                <tr key={invoice.id}>
                                                    <td className="py-2 text-sm text-gray-900">{invoice.number || '-'}</td>
                                                    <td className="py-2 text-sm text-gray-500">{formatDate(invoice.due_date)}</td>
                                                    <td className="py-2 text-sm text-gray-500 text-right">{formatCurrency(invoice.total_amount)}</td>
                                                    <td className="py-2 text-sm text-gray-900 text-right">{formatCurrency(invoice.balance)}</td>
                                                    <td className={`py-2 text-sm text-right ${status.className}`}>{status.text}</td>
                                                    <td className="py-2 text-right">
                                                        <div className="gap-2 flex justify-end">
                                                            <button
                                                                onClick={() => handleDownloadPdf(invoice.id)}
                                                                className="text-indigo-600 hover:text-indigo-900 text-sm"
                                                                title="Download PDF"
                                                            >
                                                                <svg className="h-4 w-4 inline" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                                    <path
                                                                        strokeLinecap="round"
                                                                        strokeLinejoin="round"
                                                                        strokeWidth={2}
                                                                        d="M12 10v6m0 0l-3-3m3 3l3-3m2 8H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"
                                                                    />
                                                                </svg>
                                                            </button>
                                                            <button
                                                                onClick={() => handleRegeneratePdf(invoice.id, invoice.number)}
                                                                className="text-gray-400 hover:text-gray-600 text-sm"
                                                                title="Regenerate PDF"
                                                            >
                                                                <svg className="h-4 w-4 inline" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                                    <path
                                                                        strokeLinecap="round"
                                                                        strokeLinejoin="round"
                                                                        strokeWidth={2}
                                                                        d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"
                                                                    />
                                                                </svg>
                                                            </button>
                                                        </div>
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

                    {/* Customer SKU Mapping */}
                    <div className="bg-white shadow-sm sm:rounded-lg overflow-hidden">
                        <div className="p-6">
                            <div className="mb-4">
                                <h3 className="text-lg font-medium text-gray-900">Customer SKU Mapping</h3>
                                <p className="text-sm text-gray-500">Map Yums SKUs to this customer's internal SKUs for invoices</p>
                            </div>

                            {/* Add new mapping */}
                            <div className="mb-4 gap-4 p-4 bg-gray-50 rounded-md flex items-end">
                                <div className="flex-1">
                                    <label className="text-sm font-medium text-gray-700 mb-1 block">Yums SKU</label>
                                    <select
                                        value={newSkuYums}
                                        onChange={(e) => setNewSkuYums(e.target.value)}
                                        className="rounded-md border-gray-300 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500 block w-full"
                                    >
                                        <option value="">Select a product...</option>
                                        {availableProducts.map((p) => (
                                            <option key={p.id} value={p.sku}>
                                                {p.sku} - {p.name}
                                            </option>
                                        ))}
                                    </select>
                                </div>
                                <div className="flex-1">
                                    <label className="text-sm font-medium text-gray-700 mb-1 block">Customer SKU</label>
                                    <input
                                        type="text"
                                        value={newSkuCustomer}
                                        onChange={(e) => setNewSkuCustomer(e.target.value)}
                                        placeholder="Enter customer's SKU"
                                        className="rounded-md border-gray-300 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500 block w-full"
                                    />
                                </div>
                                <button
                                    onClick={addCustomerSku}
                                    disabled={!newSkuYums || !newSkuCustomer || addingSku}
                                    className="rounded-md bg-indigo-600 px-4 py-2 text-sm font-medium text-white hover:bg-indigo-700 disabled:opacity-50"
                                >
                                    {addingSku ? 'Adding...' : 'Add Mapping'}
                                </button>
                            </div>

                            {/* Existing mappings */}
                            {customerSkus.length > 0 ? (
                                <table className="divide-gray-200 min-w-full divide-y">
                                    <thead>
                                        <tr>
                                            <th className="pb-2 text-xs font-medium text-gray-500 text-left uppercase">Yums SKU</th>
                                            <th className="pb-2 text-xs font-medium text-gray-500 text-left uppercase">Customer SKU</th>
                                            <th className="pb-2 text-xs font-medium text-gray-500 text-right uppercase">Actions</th>
                                        </tr>
                                    </thead>
                                    <tbody className="divide-gray-200 divide-y">
                                        {customerSkus.map((sku) => (
                                            <tr key={sku.id}>
                                                <td className="py-2 text-sm text-gray-900">{sku.yums_sku}</td>
                                                <td className="py-2 text-sm text-gray-900">{sku.customer_sku}</td>
                                                <td className="py-2 text-right">
                                                    <button
                                                        onClick={() => deleteCustomerSku(sku.id)}
                                                        className="text-red-500 hover:text-red-700 text-sm"
                                                    >
                                                        Delete
                                                    </button>
                                                </td>
                                            </tr>
                                        ))}
                                    </tbody>
                                </table>
                            ) : (
                                <p className="text-sm text-gray-500">No SKU mappings configured. Add a mapping above to get started.</p>
                            )}
                        </div>
                    </div>

                    {/* Last updated */}
                    <div className="text-xs text-gray-400 text-right">Last updated: {new Date(lastUpdated).toLocaleString()}</div>
                </div>
            </div>
        </AuthenticatedLayout>
    );
}
