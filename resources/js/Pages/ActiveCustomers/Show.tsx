import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import EmailActivityPanel from '@/Components/EmailActivityPanel';
import { Head, Link, router, usePage } from '@inertiajs/react';
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

interface APContact {
    name: string;
    value: string;
}

interface OtherContact {
    name: string;
    email: string;
    function?: string;
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
    broker: boolean;
    broker_commission: number | null;
    broker_company_name: string | null;
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

interface Props {
    customer: Customer;
    buyerContacts: BuyerContact[];
    localBuyers: LocalContact[];
    localAP: LocalContact[];
    localOther: LocalContact[];
    uncategorizedContacts: UncategorizedContact[];
    brokerContacts: BrokerContact[];
    monthlyRevenue: MonthlyRevenue[];
    topProducts: TopProduct[];
    upcomingOrders: UpcomingOrder[];
    outstandingInvoices: OutstandingInvoice[];
    lastUpdated: string;
    priceLists: PriceList[];
    paymentTerms: PaymentTerm[];
    shippingTerms: ShippingTerm[];
    error?: string;
}

interface CustomerDetailsForm {
    name: string;
    sale_price_list: string;
    customer_payment_term: string;
    shipping_terms_category_id: string;
    shelf_life_requirement: string;
    vendor_guide: string;
    broker: string;  // "true", "false", or "" for unselected
    broker_commission: string;
    broker_company_name: string;
}

interface BrokerContactForm {
    name: string;
    email: string;
}

interface ContactsForm {
    buyers: Contact[];
    ap_method: '' | 'inbox' | 'portal';
    ap_portal_url: string;
    accounts_payable: APContact[];
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
    localBuyers,
    localAP,
    localOther,
    uncategorizedContacts,
    brokerContacts,
    monthlyRevenue,
    topProducts,
    upcomingOrders,
    outstandingInvoices,
    lastUpdated,
    priceLists,
    paymentTerms,
    shippingTerms,
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
        broker: customer.broker === true ? 'true' : customer.broker === false ? 'false' : '',
        broker_commission: customer.broker_commission?.toString() || '',
        broker_company_name: customer.broker_company_name || '',
    });

    // Broker section states
    const [editingBroker, setEditingBroker] = useState(false);
    const [brokerContactsForm, setBrokerContactsForm] = useState<BrokerContactForm[]>(
        brokerContacts?.map(c => ({ name: c.name, email: c.email })) || []
    );

    // Derive AP method from existing data
    const deriveApMethod = (): { method: '' | 'inbox' | 'portal'; portalUrl: string; contacts: APContact[] } => {
        const apContacts = customer.accounts_payable || [];
        // Check if there's an "AP Portal" entry with a URL
        const portalEntry = apContacts.find(ap => ap.name === 'AP Portal' && ap.value?.startsWith('http'));
        if (portalEntry) {
            return { method: 'portal', portalUrl: portalEntry.value, contacts: [] };
        }
        if (apContacts.length > 0) {
            return { method: 'inbox', portalUrl: '', contacts: [...apContacts] };
        }
        return { method: '', portalUrl: '', contacts: [] };
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

        // Validate AP based on method
        if (contactsForm.ap_method === 'portal') {
            if (!contactsForm.ap_portal_url || !isValidUrl(contactsForm.ap_portal_url)) {
                errors.ap_portal_url = 'Valid portal URL is required (https://...)';
            }
        } else if (contactsForm.ap_method === 'inbox') {
            contactsForm.accounts_payable.forEach((ap, idx) => {
                if (!ap.name || ap.name.length < 2) {
                    errors[`accounts_payable.${idx}.name`] = 'Name is required';
                }
                if (!ap.value || !isValidEmail(ap.value)) {
                    errors[`accounts_payable.${idx}.value`] = 'Valid email is required';
                }
            });
        }
        // If method is '', that's fine - no AP required

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
            sale_price_list: priceLists.find(pl => pl.discount_percent === customer.discount_percent)?.id.toString() || '',
            customer_payment_term: paymentTerms.find(pt => pt.name === customer.payment_terms)?.id.toString() || '',
            shipping_terms_category_id: shippingTerms.find(st => st.name === customer.shipping_terms)?.id.toString() || '',
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
        } catch (error) {
            setNotification({ type: 'error', message: 'Failed to save changes. Please try again.' });
        } finally {
            setSaving(false);
        }
    };

    const saveContacts = async () => {
        if (Object.keys(contactsErrors).length > 0) return;

        // Transform AP data based on method
        let accountsPayable: APContact[] = [];
        if (contactsForm.ap_method === 'portal' && contactsForm.ap_portal_url) {
            accountsPayable = [{ name: 'AP Portal', value: contactsForm.ap_portal_url }];
        } else if (contactsForm.ap_method === 'inbox') {
            accountsPayable = contactsForm.accounts_payable;
        }
        // If method is '', send empty array

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
                    other: contactsForm.other.map(o => ({
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
        } catch (error) {
            setNotification({ type: 'error', message: 'Failed to save changes. Please try again.' });
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

    const addOther = () => {
        setContactsForm(prev => ({
            ...prev,
            other: [...prev.other, { name: '', email: '', function: '' }],
        }));
    };

    const removeOther = (index: number) => {
        setContactsForm(prev => ({
            ...prev,
            other: prev.other.filter((_, i) => i !== index),
        }));
    };

    const updateOther = (index: number, field: 'name' | 'email' | 'function', value: string) => {
        setContactsForm(prev => ({
            ...prev,
            other: prev.other.map((o, i) => i === index ? { ...o, [field]: value } : o),
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
        } catch (error) {
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
        } catch (error) {
            setNotification({ type: 'error', message: 'Failed to delete contact. Please try again.' });
        }
    };

    // Broker contact management
    const cancelBrokerEdit = () => {
        setBrokerContactsForm(brokerContacts?.map(c => ({ name: c.name, email: c.email })) || []);
        setDetailsForm(prev => ({
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
        const validContacts = brokerContactsForm.filter(c =>
            c.name && c.name.trim().length >= 2 &&
            c.email && isValidEmail(c.email)
        );
        if (validContacts.length === 0) {
            return false;
        }

        return true;
    };

    const addBrokerContactForm = () => {
        setBrokerContactsForm(prev => [...prev, { name: '', email: '' }]);
    };

    const removeBrokerContactForm = (index: number) => {
        setBrokerContactsForm(prev => prev.filter((_, i) => i !== index));
    };

    const updateBrokerContactForm = (index: number, field: 'name' | 'email', value: string) => {
        setBrokerContactsForm(prev => prev.map((c, i) => i === index ? { ...c, [field]: value } : c));
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
                    broker_contacts: brokerContactsForm.filter(c => c.name.trim()),
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
        } catch (error) {
            setNotification({ type: 'error', message: 'Failed to save broker settings. Please try again.' });
        } finally {
            setSaving(false);
        }
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
                setNotification({ type: 'success', message: 'Company domains updated successfully' });
                router.reload({ only: ['customer'] });
            } else {
                const data = await response.json();
                setNotification({ type: 'error', message: data.message || 'Failed to save changes' });
            }
        } catch (error) {
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

            {/* Success banner */}
            {showSuccessBanner && flash?.success && (
                <div className="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8 pt-4">
                    <div className="rounded-md bg-green-50 p-4">
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
                                    onClick={() => setShowSuccessBanner(false)}
                                    className="inline-flex rounded-md bg-green-50 p-1.5 text-green-500 hover:bg-green-100"
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
                <div className="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8 pt-4">
                    <div className={`rounded-md p-4 ${notification.type === 'success' ? 'bg-green-50' : 'bg-red-50'}`}>
                        <div className="flex">
                            <div className="flex-shrink-0">
                                {notification.type === 'success' ? (
                                    <svg className="h-5 w-5 text-green-400" viewBox="0 0 20 20" fill="currentColor">
                                        <path fillRule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.857-9.809a.75.75 0 00-1.214-.882l-3.483 4.79-1.88-1.88a.75.75 0 10-1.06 1.061l2.5 2.5a.75.75 0 001.137-.089l4-5.5z" clipRule="evenodd" />
                                    </svg>
                                ) : (
                                    <svg className="h-5 w-5 text-red-400" viewBox="0 0 20 20" fill="currentColor">
                                        <path fillRule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zM8.28 7.22a.75.75 0 00-1.06 1.06L8.94 10l-1.72 1.72a.75.75 0 101.06 1.06L10 11.06l1.72 1.72a.75.75 0 101.06-1.06L11.06 10l1.72-1.72a.75.75 0 00-1.06-1.06L10 8.94 8.28 7.22z" clipRule="evenodd" />
                                    </svg>
                                )}
                            </div>
                            <div className="ml-3">
                                <p className={`text-sm font-medium ${notification.type === 'success' ? 'text-green-800' : 'text-red-800'}`}>
                                    {notification.message}
                                </p>
                            </div>
                            <div className="ml-auto pl-3">
                                <button
                                    onClick={() => setNotification(null)}
                                    className={`inline-flex rounded-md p-1.5 ${notification.type === 'success' ? 'bg-green-50 text-green-500 hover:bg-green-100' : 'bg-red-50 text-red-500 hover:bg-red-100'}`}
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
                <div className="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8 pt-4">
                    <div className="rounded-md bg-red-50 p-4">
                        <div className="flex">
                            <div className="flex-shrink-0">
                                <svg className="h-5 w-5 text-red-400" viewBox="0 0 20 20" fill="currentColor">
                                    <path fillRule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zM8.28 7.22a.75.75 0 00-1.06 1.06L8.94 10l-1.72 1.72a.75.75 0 101.06 1.06L10 11.06l1.72 1.72a.75.75 0 101.06-1.06L11.06 10l1.72-1.72a.75.75 0 00-1.06-1.06L10 8.94 8.28 7.22z" clipRule="evenodd" />
                                </svg>
                            </div>
                            <div className="ml-3">
                                <p className="text-sm font-medium text-red-800">{error}</p>
                            </div>
                            <div className="ml-auto pl-3">
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
                                    <div>
                                        <dt className="text-sm font-medium text-gray-500">Broker</dt>
                                        <dd className="text-sm text-gray-900">
                                            {customer.broker ? (
                                                <span className="inline-flex items-center px-2 py-0.5 rounded text-xs bg-purple-100 text-purple-800">
                                                    Yes
                                                </span>
                                            ) : 'No'}
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

                                    <div>
                                        <label className="block text-sm font-medium text-gray-700">Uses Broker</label>
                                        <select
                                            value={detailsForm.broker}
                                            onChange={(e) => {
                                                const newValue = e.target.value;
                                                setDetailsForm(prev => ({ ...prev, broker: newValue }));
                                                // If switching to TRUE and no broker contacts, add one
                                                if (newValue === 'true' && brokerContactsForm.length === 0) {
                                                    setBrokerContactsForm([{ name: '', email: '' }]);
                                                }
                                            }}
                                            className="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                                        >
                                            <option value="">Select...</option>
                                            <option value="false">No</option>
                                            <option value="true">Yes</option>
                                        </select>
                                    </div>

                                </div>
                            )}
                        </div>
                    </div>

                    {/* Broker Section - Only visible when broker=true */}
                    {(customer.broker || detailsForm.broker === 'true') && (
                    <div className="overflow-hidden bg-white shadow-sm sm:rounded-lg border-l-4 border-purple-400">
                        <div className="p-6">
                            <div className="mb-4 flex items-center justify-between">
                                <h3 className="text-lg font-medium text-gray-900 flex items-center gap-2">
                                    Broker
                                    <span className="inline-flex items-center px-2 py-0.5 rounded text-xs bg-purple-100 text-purple-800">
                                        Commission: {customer.broker_commission ?? detailsForm.broker_commission ?? 0}%
                                    </span>
                                </h3>
                                    {!editingBroker ? (
                                        <button
                                            onClick={() => setEditingBroker(true)}
                                            className="text-gray-400 hover:text-gray-600"
                                            title="Edit"
                                        >
                                            <PencilIcon />
                                        </button>
                                    ) : (
                                        <div className="flex gap-2">
                                            <button
                                                onClick={cancelBrokerEdit}
                                                className="rounded border border-gray-300 bg-white px-3 py-1 text-sm text-gray-700 hover:bg-gray-50"
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
                                            <div className="grid grid-cols-2 gap-4 mb-4">
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
                                                                {contact.email && (
                                                                    <div className="text-gray-500">{contact.email}</div>
                                                                )}
                                                                <div className="mt-1 flex gap-3 text-xs text-gray-400">
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
                                                <div className="grid grid-cols-2 gap-4">
                                                    <div>
                                                        <label className="block text-sm font-medium text-gray-700">
                                                            Broker Company Name <span className="text-red-500">*</span>
                                                        </label>
                                                        <input
                                                            type="text"
                                                            value={detailsForm.broker_company_name}
                                                            onChange={(e) => setDetailsForm(prev => ({ ...prev, broker_company_name: e.target.value }))}
                                                            className={`mt-1 block w-full rounded-md shadow-sm focus:ring-indigo-500 ${
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
                                                        <label className="block text-sm font-medium text-gray-700">
                                                            Commission (%) <span className="text-red-500">*</span>
                                                        </label>
                                                        <input
                                                            type="number"
                                                            min="0"
                                                            max="100"
                                                            step="0.1"
                                                            value={detailsForm.broker_commission}
                                                            onChange={(e) => setDetailsForm(prev => ({ ...prev, broker_commission: e.target.value }))}
                                                            className={`mt-1 block w-32 rounded-md shadow-sm focus:ring-indigo-500 ${
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
                                                            className="flex items-center gap-1 text-sm text-indigo-600 hover:text-indigo-800"
                                                        >
                                                            <PlusIcon className="h-3 w-3" /> Add
                                                        </button>
                                                    </div>
                                                    {brokerContactsForm.length === 0 ? (
                                                        <p className="text-sm text-red-500">At least one broker contact with name and email is required</p>
                                                    ) : (
                                                <div className="space-y-2">
                                                    {brokerContactsForm.map((contact, idx) => (
                                                        <div key={idx} className="flex gap-2">
                                                            <div className="flex-1">
                                                                <input
                                                                    type="text"
                                                                    value={contact.name}
                                                                    onChange={(e) => updateBrokerContactForm(idx, 'name', e.target.value)}
                                                                    placeholder="Name *"
                                                                    className={`block w-full rounded-md text-sm shadow-sm focus:ring-indigo-500 ${
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
                                                                    className={`block w-full rounded-md text-sm shadow-sm focus:ring-indigo-500 ${
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
                                <>
                                <div className="grid gap-6 sm:grid-cols-3">
                                    {/* Buyers */}
                                    <div>
                                        <h4 className="mb-2 text-sm font-medium text-gray-700">Buyers</h4>
                                        {(buyerContacts && buyerContacts.length > 0) || (localBuyers && localBuyers.length > 0) ? (
                                            <ul className="space-y-3">
                                                {buyerContacts?.map((contact, idx) => (
                                                    <li key={`fulfil-${idx}`} className="text-sm">
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
                                                {localBuyers?.map((contact) => (
                                                    <li key={`local-${contact.id}`} className="text-sm bg-blue-50 rounded p-2 -mx-2">
                                                        <div className="flex justify-between items-start">
                                                            <div>
                                                                <div className="text-gray-900">{contact.name || <span className="text-gray-400 italic">No name</span>}</div>
                                                                <div className="text-gray-500">{contact.email}</div>
                                                                <div className="mt-1 flex gap-3 text-xs text-gray-400">
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
                                        {(customer.accounts_payable && customer.accounts_payable.length > 0) || (localAP && localAP.length > 0) ? (
                                            <ul className="space-y-2">
                                                {customer.accounts_payable?.map((contact, idx) => (
                                                    <li key={`fulfil-${idx}`} className="text-sm">
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
                                                {localAP?.map((contact) => (
                                                    <li key={`local-${contact.id}`} className="text-sm bg-blue-50 rounded p-2 -mx-2">
                                                        <div className="flex justify-between items-start">
                                                            <div>
                                                                <div className="text-gray-900">{contact.name || <span className="text-gray-400 italic">No name</span>}</div>
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
                                                        <div className="flex items-center gap-2">
                                                            <span className="text-gray-900">{contact.name}</span>
                                                            {contact.function && (
                                                                <span className="px-1.5 py-0.5 text-xs bg-gray-100 text-gray-600 rounded">
                                                                    {contact.function}
                                                                </span>
                                                            )}
                                                        </div>
                                                        {contact.email && (
                                                            <div className="text-gray-500">{contact.email}</div>
                                                        )}
                                                    </li>
                                                ))}
                                                {localOther?.map((contact) => (
                                                    <li key={`local-${contact.id}`} className="text-sm bg-blue-50 rounded p-2 -mx-2">
                                                        <div className="flex justify-between items-start">
                                                            <div>
                                                                <div className="text-gray-900">{contact.name || <span className="text-gray-400 italic">No name</span>}</div>
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
                                    <div className="mt-6 pt-6 border-t border-gray-200">
                                        <h4 className="mb-3 text-sm font-medium text-gray-700 flex items-center gap-2">
                                            <span className="inline-flex items-center px-2 py-0.5 rounded text-xs bg-yellow-100 text-yellow-800">
                                                Uncategorized
                                            </span>
                                            <span className="text-gray-500 font-normal">
                                                ({uncategorizedContacts.length} discovered from emails)
                                            </span>
                                        </h4>
                                        <div className="grid gap-3 sm:grid-cols-2 lg:grid-cols-3">
                                            {uncategorizedContacts.map((contact) => (
                                                <div key={contact.id} className="p-3 bg-yellow-50 rounded-lg border border-yellow-100">
                                                    <div className="flex justify-between items-start">
                                                        <div className="flex-1 min-w-0">
                                                            <div className="text-sm text-gray-900 font-medium truncate">
                                                                {contact.name || <span className="text-gray-400 italic">No name</span>}
                                                            </div>
                                                            <div className="text-sm text-gray-500 truncate">{contact.email}</div>
                                                            <div className="mt-1 flex gap-3 text-xs text-gray-400">
                                                                <span>Sent: {formatRelativeDate(contact.last_emailed_at)}</span>
                                                                <span>Received: {formatRelativeDate(contact.last_received_at)}</span>
                                                            </div>
                                                        </div>
                                                        <div className="ml-2 flex flex-col gap-1">
                                                            <select
                                                                value={categorizingContacts[contact.id] || ''}
                                                                onChange={(e) => {
                                                                    const type = e.target.value;
                                                                    if (type) {
                                                                        setCategorizingContacts(prev => ({ ...prev, [contact.id]: type }));
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
                                        <h4 className="mb-2 text-sm font-medium text-gray-700">Accounts Payable</h4>

                                        {/* AP Method Selection */}
                                        <div className="mb-3 flex gap-4">
                                            <label className="flex items-center">
                                                <input
                                                    type="radio"
                                                    name="ap_method_edit"
                                                    checked={contactsForm.ap_method === ''}
                                                    onChange={() => setContactsForm(prev => ({ ...prev, ap_method: '' }))}
                                                    className="h-4 w-4 text-indigo-600 focus:ring-indigo-500 border-gray-300"
                                                />
                                                <span className="ml-2 text-sm text-gray-700">Not set</span>
                                            </label>
                                            <label className="flex items-center">
                                                <input
                                                    type="radio"
                                                    name="ap_method_edit"
                                                    checked={contactsForm.ap_method === 'inbox'}
                                                    onChange={() => setContactsForm(prev => ({ ...prev, ap_method: 'inbox' }))}
                                                    className="h-4 w-4 text-indigo-600 focus:ring-indigo-500 border-gray-300"
                                                />
                                                <span className="ml-2 text-sm text-gray-700">Email inbox</span>
                                            </label>
                                            <label className="flex items-center">
                                                <input
                                                    type="radio"
                                                    name="ap_method_edit"
                                                    checked={contactsForm.ap_method === 'portal'}
                                                    onChange={() => setContactsForm(prev => ({ ...prev, ap_method: 'portal' }))}
                                                    className="h-4 w-4 text-indigo-600 focus:ring-indigo-500 border-gray-300"
                                                />
                                                <span className="ml-2 text-sm text-gray-700">Web portal</span>
                                            </label>
                                        </div>

                                        {/* Portal URL (when portal selected) */}
                                        {contactsForm.ap_method === 'portal' && (
                                            <div>
                                                <input
                                                    type="url"
                                                    value={contactsForm.ap_portal_url}
                                                    onChange={(e) => setContactsForm(prev => ({ ...prev, ap_portal_url: e.target.value }))}
                                                    placeholder="https://vendor-portal.example.com"
                                                    className={`block w-full rounded-md text-sm shadow-sm focus:ring-indigo-500 ${contactsErrors.ap_portal_url ? 'border-red-300' : 'border-gray-300'}`}
                                                />
                                                {contactsErrors.ap_portal_url && (
                                                    <p className="mt-1 text-sm text-red-600">{contactsErrors.ap_portal_url}</p>
                                                )}
                                                <p className="mt-1 text-xs text-gray-500">Will be saved as contact "AP Portal"</p>
                                            </div>
                                        )}

                                        {/* AP Contacts (when inbox selected) */}
                                        {contactsForm.ap_method === 'inbox' && (
                                            <div>
                                                <div className="mb-2 flex items-center justify-end">
                                                    <button
                                                        type="button"
                                                        onClick={addAP}
                                                        className="flex items-center gap-1 text-sm text-indigo-600 hover:text-indigo-800"
                                                    >
                                                        <PlusIcon className="h-3 w-3" /> Add Contact
                                                    </button>
                                                </div>
                                                {contactsForm.accounts_payable.length === 0 ? (
                                                    <p className="text-sm text-gray-400">No AP contacts. Click "Add Contact" to add one.</p>
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
                                                                        type="email"
                                                                        value={ap.value}
                                                                        onChange={(e) => updateAP(idx, 'value', e.target.value)}
                                                                        placeholder="Email"
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
                                        )}
                                    </div>

                                    {/* Other Edit */}
                                    <div>
                                        <div className="mb-2 flex items-center justify-between">
                                            <h4 className="text-sm font-medium text-gray-700">Other</h4>
                                            <button
                                                type="button"
                                                onClick={addOther}
                                                className="flex items-center gap-1 text-sm text-indigo-600 hover:text-indigo-800"
                                            >
                                                <PlusIcon className="h-3 w-3" /> Add
                                            </button>
                                        </div>
                                        {contactsForm.other.length === 0 ? (
                                            <p className="text-sm text-gray-400">No other contacts</p>
                                        ) : (
                                            <div className="space-y-2">
                                                {contactsForm.other.map((other, idx) => (
                                                    <div key={idx} className="flex gap-2">
                                                        <div className="flex-1">
                                                            <input
                                                                type="text"
                                                                value={other.name}
                                                                onChange={(e) => updateOther(idx, 'name', e.target.value)}
                                                                placeholder="Name"
                                                                className={`block w-full rounded-md text-sm shadow-sm focus:ring-indigo-500 ${contactsErrors[`other.${idx}.name`] ? 'border-red-300' : 'border-gray-300'}`}
                                                            />
                                                        </div>
                                                        <div className="w-28">
                                                            <input
                                                                type="text"
                                                                value={other.function || ''}
                                                                onChange={(e) => updateOther(idx, 'function', e.target.value)}
                                                                placeholder="Function"
                                                                className="block w-full rounded-md text-sm shadow-sm focus:ring-indigo-500 border-gray-300"
                                                            />
                                                        </div>
                                                        <div className="flex-1">
                                                            <input
                                                                type="email"
                                                                value={other.email}
                                                                onChange={(e) => updateOther(idx, 'email', e.target.value)}
                                                                placeholder="Email"
                                                                className={`block w-full rounded-md text-sm shadow-sm focus:ring-indigo-500 ${contactsErrors[`other.${idx}.email`] ? 'border-red-300' : 'border-gray-300'}`}
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

                    {/* Email Activity */}
                    <EmailActivityPanel
                        entityType="customer"
                        entityId={customer.id}
                    />

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
