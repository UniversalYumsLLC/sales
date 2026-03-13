import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import EmailActivityPanel from '@/Components/EmailActivityPanel';
import { Head, Link, router } from '@inertiajs/react';
import { useState, useEffect } from 'react';

interface Contact {
    id?: number;
    name: string;
    value: string;
    last_emailed_at?: string | null;
    last_received_at?: string | null;
}

interface Product {
    id: number;
    name: string;
    sku: string | null;
}

interface StatusInfo {
    label: string;
    description: string;
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

interface UncategorizedContact {
    id: number;
    name: string;
    value: string;
    last_emailed_at?: string | null;
    last_received_at?: string | null;
}

interface BrokerContact {
    id?: number;
    name: string;
    value: string;
    last_emailed_at?: string | null;
    last_received_at?: string | null;
}

interface OtherContact {
    id?: number;
    name: string;
    value: string;
    function?: string;
    last_emailed_at?: string | null;
    last_received_at?: string | null;
}

interface Prospect {
    id: number;
    company_name: string;
    status: string;
    notes: string | null;
    discount_percent: number | null;
    payment_terms: string | null;
    shipping_terms: string | null;
    shelf_life_requirement: number | null;
    vendor_guide: string | null;
    company_urls: string[];
    broker: boolean;
    broker_commission: number | null;
    broker_company_name: string | null;
    broker_contacts: BrokerContact[];
    buyers: Contact[];
    accounts_payable: Contact[];
    other: OtherContact[];
    uncategorized: UncategorizedContact[];
    products: Product[];
    product_ids: number[];
}

interface Props {
    prospect: Prospect;
    statuses: Record<string, StatusInfo>;
    allProducts: Product[];
    priceLists: PriceList[];
    paymentTerms: PaymentTerm[];
    shippingTerms: ShippingTerm[];
}

interface DetailsForm {
    company_name: string;
    status: string;
    discount_percent: string;  // stores priceList id as string
    payment_terms: string;     // stores paymentTerm id as string
    shipping_terms: string;    // stores shippingTerm id as string
    shelf_life_requirement: string;
    vendor_guide: string;
    company_urls: string[];
    broker: string;  // "true", "false", or "" for unselected
    broker_commission: string;
    broker_company_name: string;
}

interface BrokerContactsForm {
    broker_contacts: BrokerContact[];
}

interface ContactsForm {
    buyers: Contact[];
    ap_method: '' | 'inbox' | 'portal';
    ap_portal_url: string;
    accounts_payable: Contact[];
    other: OtherContact[];
    uncategorized: Contact[];
}

interface ProductsForm {
    product_ids: number[];
}

interface ValidationErrors {
    [key: string]: string;
}

// Icon components
function PencilIcon({ className = "h-4 w-4" }: { className?: string }) {
    return (
        <svg className={className} fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M15.232 5.232l3.536 3.536m-2.036-5.036a2.5 2.5 0 113.536 3.536L6.5 21.036H3v-3.572L16.732 3.732z" />
        </svg>
    );
}

function XIcon({ className = "h-4 w-4" }: { className?: string }) {
    return (
        <svg className={className} fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M6 18L18 6M6 6l12 12" />
        </svg>
    );
}

function PlusIcon({ className = "h-4 w-4" }: { className?: string }) {
    return (
        <svg className={className} fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M12 4v16m8-8H4" />
        </svg>
    );
}

function isValidUrl(url: string): boolean {
    try {
        new URL(url);
        return url.startsWith('http://') || url.startsWith('https://');
    } catch {
        return false;
    }
}

function isValidEmail(email: string): boolean {
    return /^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(email);
}

function formatDate(dateString: string | null | undefined): string {
    if (!dateString) return '-';
    const date = new Date(dateString);
    return date.toLocaleDateString('en-US', {
        month: 'short',
        day: 'numeric',
        year: 'numeric',
    });
}

export default function Show({ prospect, statuses, allProducts, priceLists, paymentTerms, shippingTerms }: Props) {
    // Edit mode states
    const [editingDetails, setEditingDetails] = useState(false);
    const [editingContacts, setEditingContacts] = useState(false);
    const [editingProducts, setEditingProducts] = useState(false);
    const [saving, setSaving] = useState(false);

    // Form states - map stored values to dropdown IDs
    const [detailsForm, setDetailsForm] = useState<DetailsForm>({
        company_name: prospect.company_name,
        status: prospect.status,
        discount_percent: priceLists.find(pl => pl.discount_percent === prospect.discount_percent)?.id.toString() || '',
        payment_terms: paymentTerms.find(pt => pt.name === prospect.payment_terms)?.id.toString() || '',
        shipping_terms: shippingTerms.find(st => st.name === prospect.shipping_terms)?.id.toString() || '',
        shelf_life_requirement: prospect.shelf_life_requirement?.toString() || '',
        vendor_guide: prospect.vendor_guide || '',
        company_urls: prospect.company_urls || [],
        broker: prospect.broker === true ? 'true' : prospect.broker === false ? 'false' : '',
        broker_commission: prospect.broker_commission?.toString() || '',
        broker_company_name: prospect.broker_company_name || '',
    });

    const [newCompanyUrl, setNewCompanyUrl] = useState('');

    // Broker contacts state
    const [editingBroker, setEditingBroker] = useState(false);
    const [brokerContactsForm, setBrokerContactsForm] = useState<BrokerContactsForm>({
        broker_contacts: prospect.broker_contacts?.length > 0 ? [...prospect.broker_contacts] : [],
    });
    const [brokerContactsErrors, setBrokerContactsErrors] = useState<ValidationErrors>({});

    // Promotion state
    const [promoting, setPromoting] = useState(false);
    const [promotionErrors, setPromotionErrors] = useState<ValidationErrors>({});

    // Derive AP method from existing data
    const deriveApMethod = (): { method: '' | 'inbox' | 'portal'; portalUrl: string; contacts: Contact[] } => {
        const apContacts = prospect.accounts_payable || [];
        // Check if there's a portal entry (name="AP Portal" with a URL value)
        const portalEntry = apContacts.find(c => c.name === 'AP Portal' && c.value && isValidUrl(c.value));
        if (portalEntry) {
            return { method: 'portal', portalUrl: portalEntry.value, contacts: [] };
        }
        // If there are AP contacts, it's inbox mode
        if (apContacts.length > 0) {
            return { method: 'inbox', portalUrl: '', contacts: [...apContacts] };
        }
        // No AP configured
        return { method: '', portalUrl: '', contacts: [] };
    };

    const initialApState = deriveApMethod();

    const [contactsForm, setContactsForm] = useState<ContactsForm>({
        buyers: prospect.buyers?.length > 0 ? [...prospect.buyers] : [],
        ap_method: initialApState.method,
        ap_portal_url: initialApState.portalUrl,
        accounts_payable: initialApState.contacts,
        other: prospect.other?.length > 0 ? [...prospect.other] : [],
        uncategorized: prospect.uncategorized?.length > 0 ? [...prospect.uncategorized] : [],
    });

    // Track contacts being categorized (contact id -> selected type)
    const [categorizingContacts, setCategorizingContacts] = useState<Record<number, string>>({});

    const [productsForm, setProductsForm] = useState<ProductsForm>({
        product_ids: [...prospect.product_ids],
    });

    // Product search
    const [productSearch, setProductSearch] = useState('');
    const [showProductDropdown, setShowProductDropdown] = useState(false);

    // Validation errors
    const [detailsErrors, setDetailsErrors] = useState<ValidationErrors>({});
    const [contactsErrors, setContactsErrors] = useState<ValidationErrors>({});

    // Validate details form
    useEffect(() => {
        if (!editingDetails) return;

        const errors: ValidationErrors = {};
        if (!detailsForm.company_name || detailsForm.company_name.length < 2) {
            errors.company_name = 'Company name must be at least 2 characters';
        }
        if (detailsForm.shelf_life_requirement && (isNaN(parseInt(detailsForm.shelf_life_requirement)) || parseInt(detailsForm.shelf_life_requirement) < 1)) {
            errors.shelf_life_requirement = 'Shelf life must be a positive number';
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

        // Validate buyers
        contactsForm.buyers.forEach((buyer, idx) => {
            if (!buyer.name || buyer.name.length < 1) {
                errors[`buyers.${idx}.name`] = 'Name is required';
            }
            if (buyer.value && !isValidEmail(buyer.value)) {
                errors[`buyers.${idx}.value`] = 'Invalid email address';
            }
        });

        // Validate AP based on method
        if (contactsForm.ap_method === 'portal') {
            if (!contactsForm.ap_portal_url || !isValidUrl(contactsForm.ap_portal_url)) {
                errors.ap_portal_url = 'Valid portal URL is required (https://...)';
            }
        } else if (contactsForm.ap_method === 'inbox') {
            contactsForm.accounts_payable.forEach((ap, idx) => {
                if (!ap.name || ap.name.length < 1) {
                    errors[`accounts_payable.${idx}.name`] = 'Name is required';
                }
                if (ap.value && !isValidEmail(ap.value)) {
                    errors[`accounts_payable.${idx}.value`] = 'Invalid email address';
                }
            });
        }

        // Validate other contacts
        contactsForm.other.forEach((other, idx) => {
            if (!other.name || other.name.length < 1) {
                errors[`other.${idx}.name`] = 'Name is required';
            }
            if (other.value && !isValidEmail(other.value)) {
                errors[`other.${idx}.value`] = 'Invalid email address';
            }
        });

        // Validate uncategorized
        contactsForm.uncategorized.forEach((contact, idx) => {
            if (!contact.name || contact.name.length < 1) {
                errors[`uncategorized.${idx}.name`] = 'Name is required';
            }
            if (contact.value && !isValidEmail(contact.value)) {
                errors[`uncategorized.${idx}.value`] = 'Invalid email address';
            }
        });

        setContactsErrors(errors);
    }, [contactsForm, editingContacts]);

    const cancelDetailsEdit = () => {
        setDetailsForm({
            company_name: prospect.company_name,
            status: prospect.status,
            discount_percent: priceLists.find(pl => pl.discount_percent === prospect.discount_percent)?.id.toString() || '',
            payment_terms: paymentTerms.find(pt => pt.name === prospect.payment_terms)?.id.toString() || '',
            shipping_terms: shippingTerms.find(st => st.name === prospect.shipping_terms)?.id.toString() || '',
            shelf_life_requirement: prospect.shelf_life_requirement?.toString() || '',
            vendor_guide: prospect.vendor_guide || '',
            company_urls: prospect.company_urls || [],
            broker: prospect.broker === true ? 'true' : prospect.broker === false ? 'false' : '',
            broker_commission: prospect.broker_commission?.toString() || '',
            broker_company_name: prospect.broker_company_name || '',
        });
        setNewCompanyUrl('');
        setEditingDetails(false);
        setDetailsErrors({});
    };

    const cancelContactsEdit = () => {
        const apState = deriveApMethod();
        setContactsForm({
            buyers: prospect.buyers?.length > 0 ? [...prospect.buyers] : [],
            ap_method: apState.method,
            ap_portal_url: apState.portalUrl,
            accounts_payable: apState.contacts,
            other: prospect.other?.length > 0 ? [...prospect.other] : [],
            uncategorized: prospect.uncategorized?.length > 0 ? [...prospect.uncategorized] : [],
        });
        setEditingContacts(false);
        setContactsErrors({});
        setCategorizingContacts({});
    };

    const cancelProductsEdit = () => {
        setProductsForm({
            product_ids: [...prospect.product_ids],
        });
        setEditingProducts(false);
        setProductSearch('');
        setShowProductDropdown(false);
    };

    const saveDetails = async () => {
        if (Object.keys(detailsErrors).length > 0) return;

        // Convert dropdown IDs back to stored values
        const selectedPriceList = priceLists.find(pl => pl.id.toString() === detailsForm.discount_percent);
        const selectedPaymentTerm = paymentTerms.find(pt => pt.id.toString() === detailsForm.payment_terms);
        const selectedShippingTerm = shippingTerms.find(st => st.id.toString() === detailsForm.shipping_terms);

        setSaving(true);
        try {
            const response = await fetch(route('prospects.update', prospect.id), {
                method: 'PUT',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '',
                },
                body: JSON.stringify({
                    company_name: detailsForm.company_name,
                    status: detailsForm.status,
                    discount_percent: selectedPriceList?.discount_percent ?? null,
                    payment_terms: selectedPaymentTerm?.name || null,
                    shipping_terms: selectedShippingTerm?.name || null,
                    shelf_life_requirement: detailsForm.shelf_life_requirement ? parseInt(detailsForm.shelf_life_requirement) : null,
                    vendor_guide: detailsForm.vendor_guide || null,
                    company_urls: detailsForm.company_urls,
                    broker: detailsForm.broker === 'true',
                }),
            });

            if (response.ok) {
                setEditingDetails(false);
                router.reload({ only: ['prospect'] });
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
            // Only send name and value fields (not id, last_emailed_at, last_received_at)
            const cleanContacts = (contacts: Contact[]) =>
                contacts.filter(c => c.name).map(c => ({ name: c.name, value: c.value || '' }));

            // Transform AP data based on method
            let accountsPayable: { name: string; value: string }[] = [];
            if (contactsForm.ap_method === 'portal' && contactsForm.ap_portal_url) {
                accountsPayable = [{ name: 'AP Portal', value: contactsForm.ap_portal_url }];
            } else if (contactsForm.ap_method === 'inbox') {
                accountsPayable = cleanContacts(contactsForm.accounts_payable);
            }
            // If ap_method is '', send empty array

            const response = await fetch(route('prospects.update', prospect.id), {
                method: 'PUT',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '',
                },
                body: JSON.stringify({
                    buyers: cleanContacts(contactsForm.buyers),
                    accounts_payable: accountsPayable,
                    other: contactsForm.other.filter(c => c.name.trim()).map(c => ({
                        name: c.name.trim(),
                        value: c.value?.trim() || '',
                        function: c.function?.trim() || '',
                    })),
                    uncategorized: cleanContacts(contactsForm.uncategorized),
                }),
            });

            if (response.ok) {
                setEditingContacts(false);
                setCategorizingContacts({});
                router.reload({ only: ['prospect'] });
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

    const saveProducts = async () => {
        setSaving(true);
        try {
            const response = await fetch(route('prospects.update', prospect.id), {
                method: 'PUT',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '',
                },
                body: JSON.stringify({
                    product_ids: productsForm.product_ids,
                }),
            });

            if (response.ok) {
                setEditingProducts(false);
                setProductSearch('');
                setShowProductDropdown(false);
                router.reload({ only: ['prospect'] });
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

    // Contact management - Buyers
    const addBuyer = () => {
        setContactsForm(prev => ({
            ...prev,
            buyers: [...prev.buyers, { name: '', value: '' }],
        }));
    };

    const removeBuyer = (index: number) => {
        setContactsForm(prev => ({
            ...prev,
            buyers: prev.buyers.filter((_, i) => i !== index),
        }));
    };

    const updateBuyer = (index: number, field: 'name' | 'value', value: string) => {
        setContactsForm(prev => ({
            ...prev,
            buyers: prev.buyers.map((b, i) => i === index ? { ...b, [field]: value } : b),
        }));
    };

    // Contact management - AP
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

    // Contact management - Other
    const addOther = () => {
        setContactsForm(prev => ({
            ...prev,
            other: [...prev.other, { name: '', value: '', function: '' }],
        }));
    };

    const removeOther = (index: number) => {
        setContactsForm(prev => ({
            ...prev,
            other: prev.other.filter((_, i) => i !== index),
        }));
    };

    const updateOther = (index: number, field: 'name' | 'value' | 'function', value: string) => {
        setContactsForm(prev => ({
            ...prev,
            other: prev.other.map((o, i) => i === index ? { ...o, [field]: value } : o),
        }));
    };

    // Contact management - Uncategorized
    const addUncategorized = () => {
        setContactsForm(prev => ({
            ...prev,
            uncategorized: [...prev.uncategorized, { name: '', value: '' }],
        }));
    };

    const removeUncategorized = (index: number) => {
        setContactsForm(prev => ({
            ...prev,
            uncategorized: prev.uncategorized.filter((_, i) => i !== index),
        }));
    };

    const updateUncategorized = (index: number, field: 'name' | 'value', value: string) => {
        setContactsForm(prev => ({
            ...prev,
            uncategorized: prev.uncategorized.map((c, i) => i === index ? { ...c, [field]: value } : c),
        }));
    };

    // Categorize a contact (move from uncategorized to a specific type)
    const categorizeContact = async (contactId: number, newType: string) => {
        if (!contactId || !newType) return;

        try {
            const response = await fetch(route('prospects.contacts.categorize', { prospectId: prospect.id, contactId }), {
                method: 'PATCH',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '',
                },
                body: JSON.stringify({ type: newType }),
            });

            if (response.ok) {
                setCategorizingContacts({});
                router.reload({ only: ['prospect'] });
            } else {
                const data = await response.json();
                alert(data.message || 'Failed to categorize contact');
            }
        } catch (error) {
            alert('Failed to categorize contact');
        }
    };

    // Broker contact management
    const addBrokerContact = () => {
        setBrokerContactsForm(prev => ({
            ...prev,
            broker_contacts: [...prev.broker_contacts, { name: '', value: '' }],
        }));
    };

    const removeBrokerContact = (index: number) => {
        setBrokerContactsForm(prev => ({
            ...prev,
            broker_contacts: prev.broker_contacts.filter((_, i) => i !== index),
        }));
    };

    const updateBrokerContact = (index: number, field: 'name' | 'value', value: string) => {
        setBrokerContactsForm(prev => ({
            ...prev,
            broker_contacts: prev.broker_contacts.map((c, i) => i === index ? { ...c, [field]: value } : c),
        }));
    };

    const cancelBrokerEdit = () => {
        setBrokerContactsForm({
            broker_contacts: prospect.broker_contacts?.length > 0 ? [...prospect.broker_contacts] : [],
        });
        setEditingBroker(false);
        setBrokerContactsErrors({});
    };

    const saveBroker = async () => {
        if (Object.keys(brokerContactsErrors).length > 0) return;

        setSaving(true);
        try {
            const cleanContacts = (contacts: BrokerContact[]) =>
                contacts.filter(c => c.name).map(c => ({ name: c.name, value: c.value || '' }));

            const response = await fetch(route('prospects.update', prospect.id), {
                method: 'PUT',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '',
                },
                body: JSON.stringify({
                    broker: detailsForm.broker === 'true',
                    broker_commission: detailsForm.broker_commission ? parseFloat(detailsForm.broker_commission) : null,
                    broker_company_name: detailsForm.broker_company_name || null,
                    broker_contacts: cleanContacts(brokerContactsForm.broker_contacts),
                }),
            });

            if (response.ok) {
                setEditingBroker(false);
                router.reload({ only: ['prospect'] });
            } else {
                const data = await response.json();
                alert(data.message || 'Failed to save broker settings');
            }
        } catch (error) {
            alert('Failed to save broker settings');
        } finally {
            setSaving(false);
        }
    };

    // Validate broker contacts
    useEffect(() => {
        if (!editingBroker) return;

        const errors: ValidationErrors = {};
        brokerContactsForm.broker_contacts.forEach((contact, idx) => {
            if (!contact.name || contact.name.length < 1) {
                errors[`broker_contacts.${idx}.name`] = 'Name is required';
            }
            if (contact.value && !isValidEmail(contact.value)) {
                errors[`broker_contacts.${idx}.value`] = 'Invalid email address';
            }
        });
        setBrokerContactsErrors(errors);
    }, [brokerContactsForm, editingBroker]);

    const brokerContactsValid = Object.keys(brokerContactsErrors).length === 0;

    // Handle prospect promotion to active customer
    const handlePromote = async () => {
        setPromoting(true);
        setPromotionErrors({});

        router.post(route('prospects.promote', prospect.id), {}, {
            onSuccess: () => {
                // Redirect handled by Inertia
                setPromoting(false);
            },
            onError: (errors) => {
                // Validation errors - enable edit mode and show errors
                setPromotionErrors(errors as ValidationErrors);
                const errorKeys = Object.keys(errors);
                const hasDetailErrors = errorKeys.some(k =>
                    ['company_name', 'discount_percent', 'payment_terms', 'shipping_terms', 'shelf_life_requirement', 'vendor_guide'].includes(k)
                );
                const hasContactErrors = errorKeys.some(k =>
                    k.startsWith('buyers') || k.startsWith('accounts_payable') || k.startsWith('other')
                );
                const hasBrokerErrors = errorKeys.some(k => k.startsWith('broker_contacts'));

                if (hasDetailErrors) setEditingDetails(true);
                if (hasContactErrors) setEditingContacts(true);
                if (hasBrokerErrors) setEditingBroker(true);
                setPromoting(false);
            },
            onFinish: () => {
                setPromoting(false);
            },
        });
    };

    // Product management
    const addProduct = (productId: number) => {
        if (!productsForm.product_ids.includes(productId)) {
            setProductsForm(prev => ({
                ...prev,
                product_ids: [...prev.product_ids, productId],
            }));
        }
        setProductSearch('');
        setShowProductDropdown(false);
    };

    const removeProduct = (productId: number) => {
        setProductsForm(prev => ({
            ...prev,
            product_ids: prev.product_ids.filter(id => id !== productId),
        }));
    };

    const filteredProducts = allProducts.filter(p =>
        !productsForm.product_ids.includes(p.id) &&
        (p.name?.toLowerCase().includes(productSearch.toLowerCase()) ||
         p.sku?.toLowerCase().includes(productSearch.toLowerCase()))
    ).slice(0, 10);

    const selectedProducts = allProducts.filter(p => productsForm.product_ids.includes(p.id));

    const detailsValid = Object.keys(detailsErrors).length === 0;
    const contactsValid = Object.keys(contactsErrors).length === 0;

    // Merge promotion errors with form errors for highlighting
    const allDetailsErrors = { ...detailsErrors, ...Object.fromEntries(
        Object.entries(promotionErrors).filter(([k]) =>
            ['company_name', 'discount_percent', 'payment_terms', 'shipping_terms', 'shelf_life_requirement', 'vendor_guide'].includes(k)
        )
    )};
    const allContactsErrors = { ...contactsErrors, ...Object.fromEntries(
        Object.entries(promotionErrors).filter(([k]) =>
            k.startsWith('buyers') || k.startsWith('accounts_payable') || k.startsWith('other')
        )
    )};
    const allBrokerErrors = { ...brokerContactsErrors, ...Object.fromEntries(
        Object.entries(promotionErrors).filter(([k]) => k.startsWith('broker_contacts'))
    )};

    return (
        <AuthenticatedLayout
            header={
                <div className="flex items-center justify-between">
                    <div className="flex items-center gap-4">
                        <Link
                            href={route('prospects.index')}
                            className="text-gray-500 hover:text-gray-700"
                        >
                            &larr; Back
                        </Link>
                        <h2 className="text-xl font-semibold leading-tight text-gray-800">
                            {prospect.company_name}
                        </h2>
                    </div>
                    <button
                        onClick={handlePromote}
                        disabled={promoting || saving}
                        className="rounded-md bg-purple-600 px-4 py-2 text-sm font-medium text-white hover:bg-purple-700 focus:outline-none focus:ring-2 focus:ring-purple-500 focus:ring-offset-2 disabled:opacity-50 disabled:cursor-not-allowed"
                    >
                        {promoting ? 'Promoting...' : 'Promote to Active Customer'}
                    </button>
                </div>
            }
        >
            <Head title={prospect.company_name} />

            {/* Promotion loading overlay */}
            {promoting && (
                <div className="fixed inset-0 z-50 flex items-center justify-center bg-gray-900/50">
                    <div className="rounded-lg bg-white p-6 shadow-xl">
                        <div className="flex items-center gap-3">
                            <svg className="h-6 w-6 animate-spin text-purple-600" fill="none" viewBox="0 0 24 24">
                                <circle className="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" strokeWidth="4" />
                                <path className="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z" />
                            </svg>
                            <span className="text-lg font-medium text-gray-900">Promoting to Active Customer...</span>
                        </div>
                        <p className="mt-2 text-sm text-gray-500">Creating customer in Fulfil and migrating data...</p>
                    </div>
                </div>
            )}

            {/* Promotion errors banner */}
            {Object.keys(promotionErrors).length > 0 && (
                <div className="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8 pt-4">
                    <div className="rounded-md bg-red-50 p-4">
                        <div className="flex">
                            <div className="flex-shrink-0">
                                <svg className="h-5 w-5 text-red-400" viewBox="0 0 20 20" fill="currentColor">
                                    <path fillRule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zM8.28 7.22a.75.75 0 00-1.06 1.06L8.94 10l-1.72 1.72a.75.75 0 101.06 1.06L10 11.06l1.72 1.72a.75.75 0 101.06-1.06L11.06 10l1.72-1.72a.75.75 0 00-1.06-1.06L10 8.94 8.28 7.22z" clipRule="evenodd" />
                                </svg>
                            </div>
                            <div className="ml-3">
                                <h3 className="text-sm font-medium text-red-800">
                                    Cannot promote to active customer - please fix the following issues:
                                </h3>
                                <div className="mt-2 text-sm text-red-700">
                                    <ul className="list-disc space-y-1 pl-5">
                                        {Object.entries(promotionErrors).map(([key, message]) => (
                                            <li key={key}>{message}</li>
                                        ))}
                                    </ul>
                                </div>
                            </div>
                            <div className="ml-auto pl-3">
                                <button
                                    onClick={() => setPromotionErrors({})}
                                    className="inline-flex rounded-md bg-red-50 p-1.5 text-red-500 hover:bg-red-100"
                                >
                                    <XIcon className="h-5 w-5" />
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
            )}

            <div className="py-12">
                <div className="mx-auto max-w-7xl space-y-6 sm:px-6 lg:px-8">
                    {/* Prospect Details */}
                    <div className="overflow-hidden bg-white shadow-sm sm:rounded-lg">
                        <div className="p-6">
                            <div className="mb-4 flex items-center justify-between">
                                <h3 className="text-lg font-medium text-gray-900">Prospect Details</h3>
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
                                        <dd className="text-sm text-gray-900">{prospect.company_name}</dd>
                                    </div>
                                    <div className="relative group">
                                        <dt className="text-sm font-medium text-gray-500">Status</dt>
                                        <dd className="text-sm text-gray-900">
                                            {statuses[prospect.status]?.label || prospect.status}
                                        </dd>
                                        {/* Tooltip */}
                                        {statuses[prospect.status] && (
                                            <div className="absolute left-0 top-full mt-1 hidden group-hover:block z-10">
                                                <div className="bg-gray-900 text-white text-xs rounded py-1 px-2 whitespace-nowrap">
                                                    {statuses[prospect.status].description}
                                                </div>
                                            </div>
                                        )}
                                    </div>
                                    <div>
                                        <dt className="text-sm font-medium text-gray-500">Discount</dt>
                                        <dd className="text-sm text-gray-900">
                                            {prospect.discount_percent !== null ? `${prospect.discount_percent}%` : '-'}
                                        </dd>
                                    </div>
                                    <div>
                                        <dt className="text-sm font-medium text-gray-500">Payment Terms</dt>
                                        <dd className="text-sm text-gray-900">{prospect.payment_terms || '-'}</dd>
                                    </div>
                                    <div>
                                        <dt className="text-sm font-medium text-gray-500">Shipping Terms</dt>
                                        <dd className="text-sm text-gray-900">{prospect.shipping_terms || '-'}</dd>
                                    </div>
                                    <div>
                                        <dt className="text-sm font-medium text-gray-500">Shelf Life Requirement</dt>
                                        <dd className="text-sm text-gray-900">
                                            {prospect.shelf_life_requirement ? `${prospect.shelf_life_requirement} days` : '-'}
                                        </dd>
                                    </div>
                                    <div className="sm:col-span-2">
                                        <dt className="text-sm font-medium text-gray-500">Vendor Guide</dt>
                                        <dd className="text-sm text-gray-900">
                                            {prospect.vendor_guide ? (
                                                <a href={prospect.vendor_guide} target="_blank" rel="noopener noreferrer" className="text-indigo-600 hover:text-indigo-800">
                                                    {prospect.vendor_guide}
                                                </a>
                                            ) : '-'}
                                        </dd>
                                    </div>
                                    <div className="sm:col-span-4">
                                        <dt className="text-sm font-medium text-gray-500">Company URLs / Email Domains</dt>
                                        <dd className="text-sm text-gray-900">
                                            {prospect.company_urls && prospect.company_urls.length > 0 ? (
                                                <div className="flex flex-wrap gap-1 mt-1">
                                                    {prospect.company_urls.map((url, idx) => (
                                                        <span key={idx} className="inline-flex items-center px-2 py-0.5 rounded text-xs bg-gray-100 text-gray-800">
                                                            {url}
                                                        </span>
                                                    ))}
                                                </div>
                                            ) : '-'}
                                        </dd>
                                    </div>
                                    <div>
                                        <dt className="text-sm font-medium text-gray-500">Broker</dt>
                                        <dd className="text-sm text-gray-900">
                                            {prospect.broker ? (
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
                                        <label className="block text-sm font-medium text-gray-700">Company Name <span className="text-red-500">*</span></label>
                                        <input
                                            type="text"
                                            value={detailsForm.company_name}
                                            onChange={(e) => setDetailsForm(prev => ({ ...prev, company_name: e.target.value }))}
                                            className={`mt-1 block w-full rounded-md shadow-sm focus:ring-indigo-500 ${allDetailsErrors.company_name ? 'border-red-300 focus:border-red-500' : 'border-gray-300 focus:border-indigo-500'}`}
                                        />
                                        {allDetailsErrors.company_name && <p className="mt-1 text-xs text-red-600">{allDetailsErrors.company_name}</p>}
                                    </div>

                                    <div className="relative">
                                        <label className="block text-sm font-medium text-gray-700">Status</label>
                                        <div className="relative group inline-block w-full">
                                            <select
                                                value={detailsForm.status}
                                                onChange={(e) => setDetailsForm(prev => ({ ...prev, status: e.target.value }))}
                                                className="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                                            >
                                                {Object.entries(statuses).map(([value, info]) => (
                                                    <option key={value} value={value}>
                                                        {info.label}
                                                    </option>
                                                ))}
                                            </select>
                                            {/* Tooltip */}
                                            {statuses[detailsForm.status] && (
                                                <div className="absolute left-0 top-full mt-1 hidden group-hover:block z-10">
                                                    <div className="bg-gray-900 text-white text-xs rounded py-1 px-2 whitespace-nowrap">
                                                        {statuses[detailsForm.status].description}
                                                    </div>
                                                </div>
                                            )}
                                        </div>
                                    </div>

                                    <div>
                                        <label className="block text-sm font-medium text-gray-700">Discount Level <span className="text-red-500">*</span></label>
                                        <select
                                            value={detailsForm.discount_percent}
                                            onChange={(e) => setDetailsForm(prev => ({ ...prev, discount_percent: e.target.value }))}
                                            className={`mt-1 block w-full rounded-md shadow-sm focus:ring-indigo-500 ${allDetailsErrors.discount_percent ? 'border-red-300 focus:border-red-500' : 'border-gray-300 focus:border-indigo-500'}`}
                                        >
                                            <option value="">Select...</option>
                                            {priceLists.map((pl) => (
                                                <option key={pl.id} value={pl.id}>{pl.discount_percent}% Discount</option>
                                            ))}
                                        </select>
                                        {allDetailsErrors.discount_percent && <p className="mt-1 text-xs text-red-600">{allDetailsErrors.discount_percent}</p>}
                                    </div>

                                    <div>
                                        <label className="block text-sm font-medium text-gray-700">Payment Terms <span className="text-red-500">*</span></label>
                                        <select
                                            value={detailsForm.payment_terms}
                                            onChange={(e) => setDetailsForm(prev => ({ ...prev, payment_terms: e.target.value }))}
                                            className={`mt-1 block w-full rounded-md shadow-sm focus:ring-indigo-500 ${allDetailsErrors.payment_terms ? 'border-red-300 focus:border-red-500' : 'border-gray-300 focus:border-indigo-500'}`}
                                        >
                                            <option value="">Select...</option>
                                            {paymentTerms.map((pt) => (
                                                <option key={pt.id} value={pt.id}>{pt.name}</option>
                                            ))}
                                        </select>
                                        {allDetailsErrors.payment_terms && <p className="mt-1 text-xs text-red-600">{allDetailsErrors.payment_terms}</p>}
                                    </div>

                                    <div>
                                        <label className="block text-sm font-medium text-gray-700">Shipping Terms <span className="text-red-500">*</span></label>
                                        <select
                                            value={detailsForm.shipping_terms}
                                            onChange={(e) => setDetailsForm(prev => ({ ...prev, shipping_terms: e.target.value }))}
                                            className={`mt-1 block w-full rounded-md shadow-sm focus:ring-indigo-500 ${allDetailsErrors.shipping_terms ? 'border-red-300 focus:border-red-500' : 'border-gray-300 focus:border-indigo-500'}`}
                                        >
                                            <option value="">Select...</option>
                                            {shippingTerms.map((st) => (
                                                <option key={st.id} value={st.id}>{st.name}</option>
                                            ))}
                                        </select>
                                        {allDetailsErrors.shipping_terms && <p className="mt-1 text-xs text-red-600">{allDetailsErrors.shipping_terms}</p>}
                                    </div>

                                    <div>
                                        <label className="block text-sm font-medium text-gray-700">Shelf Life Requirement (days) <span className="text-red-500">*</span></label>
                                        <input
                                            type="number"
                                            value={detailsForm.shelf_life_requirement}
                                            onChange={(e) => setDetailsForm(prev => ({ ...prev, shelf_life_requirement: e.target.value }))}
                                            min="1"
                                            placeholder="e.g., 90"
                                            className={`mt-1 block w-full rounded-md shadow-sm focus:ring-indigo-500 ${allDetailsErrors.shelf_life_requirement ? 'border-red-300 focus:border-red-500' : 'border-gray-300 focus:border-indigo-500'}`}
                                        />
                                        {allDetailsErrors.shelf_life_requirement && <p className="mt-1 text-xs text-red-600">{allDetailsErrors.shelf_life_requirement}</p>}
                                    </div>

                                    <div className="sm:col-span-2 lg:col-span-3">
                                        <label className="block text-sm font-medium text-gray-700">Vendor Guide URL</label>
                                        <input
                                            type="url"
                                            value={detailsForm.vendor_guide}
                                            onChange={(e) => setDetailsForm(prev => ({ ...prev, vendor_guide: e.target.value }))}
                                            placeholder="https://..."
                                            className={`mt-1 block w-full rounded-md shadow-sm focus:ring-indigo-500 ${allDetailsErrors.vendor_guide ? 'border-red-300 focus:border-red-500' : 'border-gray-300 focus:border-indigo-500'}`}
                                        />
                                        {allDetailsErrors.vendor_guide && <p className="mt-1 text-xs text-red-600">{allDetailsErrors.vendor_guide}</p>}
                                    </div>

                                    <div className="sm:col-span-2 lg:col-span-3">
                                        <label className="block text-sm font-medium text-gray-700">Company URLs / Email Domains</label>
                                        <p className="text-xs text-gray-500 mb-2">
                                            Domains used for matching emails from Gmail. Email domains are auto-added from contacts.
                                        </p>

                                        {detailsForm.company_urls.length > 0 && (
                                            <div className="flex flex-wrap gap-2 mb-2">
                                                {detailsForm.company_urls.map((url, index) => (
                                                    <span
                                                        key={index}
                                                        className="inline-flex items-center gap-1 px-2 py-1 bg-gray-100 text-gray-800 rounded text-sm"
                                                    >
                                                        {url}
                                                        <button
                                                            type="button"
                                                            onClick={() => setDetailsForm(prev => ({
                                                                ...prev,
                                                                company_urls: prev.company_urls.filter((_, i) => i !== index)
                                                            }))}
                                                            className="text-gray-500 hover:text-gray-700"
                                                        >
                                                            <XIcon className="w-3 h-3" />
                                                        </button>
                                                    </span>
                                                ))}
                                            </div>
                                        )}

                                        <div className="flex gap-2">
                                            <input
                                                type="text"
                                                value={newCompanyUrl}
                                                onChange={(e) => setNewCompanyUrl(e.target.value)}
                                                onKeyDown={(e) => {
                                                    if (e.key === 'Enter') {
                                                        e.preventDefault();
                                                        if (newCompanyUrl.trim() && !detailsForm.company_urls.includes(newCompanyUrl.trim().toLowerCase())) {
                                                            setDetailsForm(prev => ({
                                                                ...prev,
                                                                company_urls: [...prev.company_urls, newCompanyUrl.trim().toLowerCase()]
                                                            }));
                                                            setNewCompanyUrl('');
                                                        }
                                                    }
                                                }}
                                                placeholder="Enter domain (e.g., company.com)"
                                                className="flex-1 rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 text-sm"
                                            />
                                            <button
                                                type="button"
                                                onClick={() => {
                                                    if (newCompanyUrl.trim() && !detailsForm.company_urls.includes(newCompanyUrl.trim().toLowerCase())) {
                                                        setDetailsForm(prev => ({
                                                            ...prev,
                                                            company_urls: [...prev.company_urls, newCompanyUrl.trim().toLowerCase()]
                                                        }));
                                                        setNewCompanyUrl('');
                                                    }
                                                }}
                                                className="px-3 py-2 bg-gray-100 text-gray-700 text-sm rounded-md hover:bg-gray-200"
                                            >
                                                Add
                                            </button>
                                        </div>
                                    </div>

                                    <div>
                                        <label className="block text-sm font-medium text-gray-700">Uses Broker <span className="text-red-500">*</span></label>
                                        <select
                                            value={detailsForm.broker}
                                            onChange={(e) => setDetailsForm(prev => ({ ...prev, broker: e.target.value }))}
                                            className={`mt-1 block w-full rounded-md shadow-sm focus:ring-indigo-500 ${allDetailsErrors.broker ? 'border-red-300 focus:border-red-500' : 'border-gray-300 focus:border-indigo-500'}`}
                                        >
                                            <option value="">Select...</option>
                                            <option value="false">No</option>
                                            <option value="true">Yes</option>
                                        </select>
                                        {allDetailsErrors.broker && <p className="mt-1 text-xs text-red-600">{allDetailsErrors.broker}</p>}
                                    </div>
                                </div>
                            )}
                        </div>
                    </div>

                    {/* Broker Section - Only visible when broker=true */}
                    {(prospect.broker || detailsForm.broker === 'true') && (
                        <div className="overflow-hidden bg-white shadow-sm sm:rounded-lg border-l-4 border-purple-400">
                            <div className="p-6">
                                <div className="mb-4 flex items-center justify-between">
                                    <h3 className="text-lg font-medium text-gray-900 flex items-center gap-2">
                                        Broker
                                        <span className="inline-flex items-center px-2 py-0.5 rounded text-xs bg-purple-100 text-purple-800">
                                            Commission: {prospect.broker_commission ?? detailsForm.broker_commission ?? 0}%
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
                                                disabled={!brokerContactsValid || saving}
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
                                                <p className="text-sm text-gray-900">{prospect.broker_company_name || '-'}</p>
                                            </div>
                                            <div>
                                                <h4 className="text-sm font-medium text-gray-700 mb-2">Commission</h4>
                                                <p className="text-sm text-gray-900">{prospect.broker_commission ?? 0}%</p>
                                            </div>
                                        </div>
                                        <div>
                                            <h4 className="text-sm font-medium text-gray-700 mb-2">Broker Contacts</h4>
                                            {prospect.broker_contacts && prospect.broker_contacts.length > 0 ? (
                                                <ul className="space-y-2">
                                                    {prospect.broker_contacts.map((contact, idx) => (
                                                        <li key={idx} className="text-sm">
                                                            <div className="text-gray-900">{contact.name}</div>
                                                            {contact.value && (
                                                                <div className="text-gray-500">{contact.value}</div>
                                                            )}
                                                            <div className="mt-1 flex gap-3 text-xs text-gray-400">
                                                                <span>Sent: {formatDate(contact.last_emailed_at)}</span>
                                                                <span>Received: {formatDate(contact.last_received_at)}</span>
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
                                        <div className="grid grid-cols-2 gap-4">
                                            <div>
                                                <label className="block text-sm font-medium text-gray-700">Broker Company Name</label>
                                                <input
                                                    type="text"
                                                    value={detailsForm.broker_company_name}
                                                    onChange={(e) => setDetailsForm(prev => ({ ...prev, broker_company_name: e.target.value }))}
                                                    className="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                                                    placeholder="e.g., HRG Brokers"
                                                />
                                            </div>
                                            <div>
                                                <label className="block text-sm font-medium text-gray-700">Commission (%)</label>
                                                <input
                                                    type="number"
                                                    min="0"
                                                    max="100"
                                                    step="0.1"
                                                    value={detailsForm.broker_commission}
                                                    onChange={(e) => setDetailsForm(prev => ({ ...prev, broker_commission: e.target.value }))}
                                                    className="mt-1 block w-32 rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                                                    placeholder="0.0"
                                                />
                                            </div>
                                        </div>
                                        <div>
                                            <div className="mb-2 flex items-center justify-between">
                                                <h4 className="text-sm font-medium text-gray-700">Broker Contacts</h4>
                                                <button
                                                    type="button"
                                                    onClick={addBrokerContact}
                                                    className="flex items-center gap-1 text-sm text-indigo-600 hover:text-indigo-800"
                                                >
                                                    <PlusIcon className="h-3 w-3" /> Add
                                                </button>
                                            </div>
                                            {brokerContactsForm.broker_contacts.length === 0 ? (
                                                <p className="text-sm text-gray-400">No broker contacts</p>
                                            ) : (
                                                <div className="space-y-2">
                                                    {brokerContactsForm.broker_contacts.map((contact, idx) => (
                                                        <div key={idx} className="flex gap-2">
                                                            <div className="flex-1">
                                                                <input
                                                                    type="text"
                                                                    value={contact.name}
                                                                    onChange={(e) => updateBrokerContact(idx, 'name', e.target.value)}
                                                                    placeholder="Name"
                                                                    className={`block w-full rounded-md text-sm shadow-sm focus:ring-indigo-500 ${brokerContactsErrors[`broker_contacts.${idx}.name`] ? 'border-red-300' : 'border-gray-300'}`}
                                                                />
                                                            </div>
                                                            <div className="flex-1">
                                                                <input
                                                                    type="email"
                                                                    value={contact.value}
                                                                    onChange={(e) => updateBrokerContact(idx, 'value', e.target.value)}
                                                                    placeholder="Email"
                                                                    className={`block w-full rounded-md text-sm shadow-sm focus:ring-indigo-500 ${brokerContactsErrors[`broker_contacts.${idx}.value`] ? 'border-red-300' : 'border-gray-300'}`}
                                                                />
                                                            </div>
                                                            <button
                                                                type="button"
                                                                onClick={() => removeBrokerContact(idx)}
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
                    )}

                    {/* Contacts */}
                    <div className={`overflow-hidden bg-white shadow-sm sm:rounded-lg ${allContactsErrors.buyers ? 'ring-2 ring-red-500' : ''}`}>
                        <div className="p-6">
                            <div className="mb-4 flex items-center justify-between">
                                <div className="flex items-center gap-2">
                                    <h3 className="text-lg font-medium text-gray-900">Contacts</h3>
                                    {allContactsErrors.buyers && (
                                        <span className="text-sm text-red-600">({allContactsErrors.buyers})</span>
                                    )}
                                </div>
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
                                        {prospect.buyers && prospect.buyers.length > 0 ? (
                                            <ul className="space-y-3">
                                                {prospect.buyers.map((contact, idx) => (
                                                    <li key={idx} className="text-sm border-b border-gray-100 pb-2 last:border-0 last:pb-0">
                                                        <div className="text-gray-900 font-medium">{contact.name}</div>
                                                        {contact.value && (
                                                            <div className="text-gray-500">{contact.value}</div>
                                                        )}
                                                        <div className="mt-1 flex gap-4 text-xs text-gray-400">
                                                            <span title="Last email sent to this buyer">
                                                                Sent: {formatDate(contact.last_emailed_at)}
                                                            </span>
                                                            <span title="Last email received from this buyer">
                                                                Received: {formatDate(contact.last_received_at)}
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
                                        {prospect.accounts_payable && prospect.accounts_payable.length > 0 ? (
                                            <ul className="space-y-2">
                                                {prospect.accounts_payable.map((contact, idx) => (
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

                                    {/* Other */}
                                    <div>
                                        <h4 className="mb-2 text-sm font-medium text-gray-700">Other</h4>
                                        {prospect.other && prospect.other.length > 0 ? (
                                            <ul className="space-y-2">
                                                {prospect.other.map((contact, idx) => (
                                                    <li key={idx} className="text-sm">
                                                        <div className="flex items-center gap-2">
                                                            <span className="text-gray-900">{contact.name}</span>
                                                            {contact.function && (
                                                                <span className="px-1.5 py-0.5 text-xs bg-gray-100 text-gray-600 rounded">
                                                                    {contact.function}
                                                                </span>
                                                            )}
                                                        </div>
                                                        {contact.value && (
                                                            <div className="text-gray-500">{contact.value}</div>
                                                        )}
                                                    </li>
                                                ))}
                                            </ul>
                                        ) : (
                                            <p className="text-sm text-gray-400">-</p>
                                        )}
                                    </div>
                                </div>

                                {/* Uncategorized Contacts Section */}
                                {prospect.uncategorized && prospect.uncategorized.length > 0 && (
                                    <div className="mt-6 pt-6 border-t border-gray-200">
                                        <h4 className="mb-3 text-sm font-medium text-gray-700 flex items-center gap-2">
                                            <span className="inline-flex items-center px-2 py-0.5 rounded text-xs bg-yellow-100 text-yellow-800">
                                                Uncategorized
                                            </span>
                                            <span className="text-gray-500 font-normal">
                                                ({prospect.uncategorized.length} discovered from emails)
                                            </span>
                                        </h4>
                                        <div className="grid gap-3 sm:grid-cols-2 lg:grid-cols-3">
                                            {prospect.uncategorized.map((contact) => (
                                                <div key={contact.id} className="p-3 bg-yellow-50 rounded-lg border border-yellow-100">
                                                    <div className="flex justify-between items-start">
                                                        <div className="flex-1 min-w-0">
                                                            <div className="text-sm text-gray-900 font-medium truncate">
                                                                {contact.name || <span className="text-gray-400 italic">No name</span>}
                                                            </div>
                                                            <div className="text-sm text-gray-500 truncate">{contact.value}</div>
                                                            <div className="mt-1 flex gap-3 text-xs text-gray-400">
                                                                <span>Sent: {formatDate(contact.last_emailed_at)}</span>
                                                                <span>Received: {formatDate(contact.last_received_at)}</span>
                                                            </div>
                                                        </div>
                                                        <div className="ml-2">
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
                                        {contactsForm.buyers.length === 0 ? (
                                            <p className="text-sm text-gray-400">No buyer contacts</p>
                                        ) : (
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
                                                                value={buyer.value}
                                                                onChange={(e) => updateBuyer(idx, 'value', e.target.value)}
                                                                placeholder="Email (optional)"
                                                                className={`block w-full rounded-md text-sm shadow-sm focus:ring-indigo-500 ${contactsErrors[`buyers.${idx}.value`] ? 'border-red-300' : 'border-gray-300'}`}
                                                            />
                                                        </div>
                                                        <button
                                                            type="button"
                                                            onClick={() => removeBuyer(idx)}
                                                            className="text-red-400 hover:text-red-600"
                                                        >
                                                            <XIcon />
                                                        </button>
                                                    </div>
                                                ))}
                                            </div>
                                        )}
                                    </div>

                                    {/* AP Edit */}
                                    <div>
                                        <h4 className="text-sm font-medium text-gray-700 mb-2">Accounts Payable</h4>
                                        <div className="flex gap-4 mb-3">
                                            <label className="flex items-center gap-2 cursor-pointer">
                                                <input
                                                    type="radio"
                                                    name="ap_method_edit"
                                                    checked={contactsForm.ap_method === ''}
                                                    onChange={() => setContactsForm(prev => ({ ...prev, ap_method: '' }))}
                                                    className="text-indigo-600 focus:ring-indigo-500"
                                                />
                                                <span className="text-sm text-gray-700">Not set</span>
                                            </label>
                                            <label className="flex items-center gap-2 cursor-pointer">
                                                <input
                                                    type="radio"
                                                    name="ap_method_edit"
                                                    checked={contactsForm.ap_method === 'inbox'}
                                                    onChange={() => setContactsForm(prev => ({ ...prev, ap_method: 'inbox' }))}
                                                    className="text-indigo-600 focus:ring-indigo-500"
                                                />
                                                <span className="text-sm text-gray-700">Email inbox</span>
                                            </label>
                                            <label className="flex items-center gap-2 cursor-pointer">
                                                <input
                                                    type="radio"
                                                    name="ap_method_edit"
                                                    checked={contactsForm.ap_method === 'portal'}
                                                    onChange={() => setContactsForm(prev => ({ ...prev, ap_method: 'portal' }))}
                                                    className="text-indigo-600 focus:ring-indigo-500"
                                                />
                                                <span className="text-sm text-gray-700">Web portal</span>
                                            </label>
                                        </div>

                                        {contactsForm.ap_method === 'portal' && (
                                            <div className="ml-6">
                                                <input
                                                    type="url"
                                                    value={contactsForm.ap_portal_url}
                                                    onChange={(e) => setContactsForm(prev => ({ ...prev, ap_portal_url: e.target.value }))}
                                                    placeholder="https://portal.example.com/invoices"
                                                    className={`block w-full rounded-md text-sm shadow-sm focus:ring-indigo-500 ${contactsErrors.ap_portal_url ? 'border-red-300' : 'border-gray-300'}`}
                                                />
                                                {contactsErrors.ap_portal_url && (
                                                    <p className="mt-1 text-sm text-red-600">{contactsErrors.ap_portal_url}</p>
                                                )}
                                            </div>
                                        )}

                                        {contactsForm.ap_method === 'inbox' && (
                                            <div className="ml-6">
                                                <div className="mb-2 flex items-center justify-between">
                                                    <span className="text-xs text-gray-500">AP contacts</span>
                                                    <button
                                                        type="button"
                                                        onClick={addAP}
                                                        className="flex items-center gap-1 text-sm text-indigo-600 hover:text-indigo-800"
                                                    >
                                                        <PlusIcon className="h-3 w-3" /> Add
                                                    </button>
                                                </div>
                                                {contactsForm.accounts_payable.length === 0 ? (
                                                    <p className="text-sm text-gray-400">No AP contacts - click Add to create one</p>
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
                                                                value={other.value}
                                                                onChange={(e) => updateOther(idx, 'value', e.target.value)}
                                                                placeholder="Email (optional)"
                                                                className={`block w-full rounded-md text-sm shadow-sm focus:ring-indigo-500 ${contactsErrors[`other.${idx}.value`] ? 'border-red-300' : 'border-gray-300'}`}
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

                                    {/* Uncategorized Edit */}
                                    <div className="pt-4 border-t border-gray-200">
                                        <div className="mb-2 flex items-center justify-between">
                                            <h4 className="text-sm font-medium text-gray-700 flex items-center gap-2">
                                                <span className="inline-flex items-center px-2 py-0.5 rounded text-xs bg-yellow-100 text-yellow-800">
                                                    Uncategorized
                                                </span>
                                            </h4>
                                            <button
                                                type="button"
                                                onClick={addUncategorized}
                                                className="flex items-center gap-1 text-sm text-indigo-600 hover:text-indigo-800"
                                            >
                                                <PlusIcon className="h-3 w-3" /> Add
                                            </button>
                                        </div>
                                        <p className="text-xs text-gray-500 mb-2">
                                            These contacts were discovered from emails. Edit their name or categorize them to move to the appropriate section.
                                        </p>
                                        {contactsForm.uncategorized.length === 0 ? (
                                            <p className="text-sm text-gray-400">No uncategorized contacts</p>
                                        ) : (
                                            <div className="space-y-2">
                                                {contactsForm.uncategorized.map((contact, idx) => (
                                                    <div key={idx} className="flex gap-2 bg-yellow-50 p-2 rounded">
                                                        <div className="flex-1">
                                                            <input
                                                                type="text"
                                                                value={contact.name}
                                                                onChange={(e) => updateUncategorized(idx, 'name', e.target.value)}
                                                                placeholder="Name"
                                                                className={`block w-full rounded-md text-sm shadow-sm focus:ring-indigo-500 ${contactsErrors[`uncategorized.${idx}.name`] ? 'border-red-300' : 'border-gray-300'}`}
                                                            />
                                                        </div>
                                                        <div className="flex-1">
                                                            <input
                                                                type="email"
                                                                value={contact.value}
                                                                onChange={(e) => updateUncategorized(idx, 'value', e.target.value)}
                                                                placeholder="Email"
                                                                className={`block w-full rounded-md text-sm shadow-sm focus:ring-indigo-500 ${contactsErrors[`uncategorized.${idx}.value`] ? 'border-red-300' : 'border-gray-300'}`}
                                                            />
                                                        </div>
                                                        <button
                                                            type="button"
                                                            onClick={() => removeUncategorized(idx)}
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

                    {/* Email Activity */}
                    <EmailActivityPanel
                        entityType="prospect"
                        entityId={prospect.id}
                    />

                    {/* Products of Interest */}
                    <div className="bg-white shadow-sm sm:rounded-lg">
                        <div className="p-6">
                            <div className="mb-4 flex items-center justify-between">
                                <h3 className="text-lg font-medium text-gray-900">Products of Interest</h3>
                                {!editingProducts ? (
                                    <button
                                        onClick={() => setEditingProducts(true)}
                                        className="text-gray-400 hover:text-gray-600"
                                        title="Edit"
                                    >
                                        <PencilIcon />
                                    </button>
                                ) : (
                                    <div className="flex gap-2">
                                        <button
                                            onClick={cancelProductsEdit}
                                            className="rounded border border-gray-300 px-3 py-1 text-sm text-gray-600 hover:bg-gray-50"
                                            disabled={saving}
                                        >
                                            Cancel
                                        </button>
                                        <button
                                            onClick={saveProducts}
                                            disabled={saving}
                                            className="rounded bg-indigo-600 px-3 py-1 text-sm text-white hover:bg-indigo-700 disabled:opacity-50"
                                        >
                                            {saving ? 'Saving...' : 'Save'}
                                        </button>
                                    </div>
                                )}
                            </div>

                            {!editingProducts ? (
                                <div>
                                    {prospect.products && prospect.products.length > 0 ? (
                                        <div className="flex flex-wrap gap-2">
                                            {prospect.products.map((product) => (
                                                <span
                                                    key={product.id}
                                                    className="inline-flex items-center px-3 py-1 bg-gray-100 text-gray-800 rounded-full text-sm"
                                                >
                                                    {product.sku && <span className="text-gray-500 mr-1">[{product.sku}]</span>}
                                                    {product.name}
                                                </span>
                                            ))}
                                        </div>
                                    ) : (
                                        <p className="text-sm text-gray-400">No products of interest</p>
                                    )}
                                </div>
                            ) : (
                                <div>
                                    {/* Selected Products */}
                                    {selectedProducts.length > 0 && (
                                        <div className="flex flex-wrap gap-2 mb-3">
                                            {selectedProducts.map((product) => (
                                                <span
                                                    key={product.id}
                                                    className="inline-flex items-center gap-1 px-3 py-1 bg-indigo-100 text-indigo-800 rounded-full text-sm"
                                                >
                                                    {product.sku && <span className="text-indigo-600">[{product.sku}]</span>}
                                                    {product.name}
                                                    <button
                                                        type="button"
                                                        onClick={() => removeProduct(product.id)}
                                                        className="ml-1 text-indigo-600 hover:text-indigo-800"
                                                    >
                                                        <XIcon className="w-4 h-4" />
                                                    </button>
                                                </span>
                                            ))}
                                        </div>
                                    )}

                                    {/* Product Search */}
                                    <div className="relative">
                                        <input
                                            type="text"
                                            value={productSearch}
                                            onChange={(e) => {
                                                setProductSearch(e.target.value);
                                                setShowProductDropdown(true);
                                            }}
                                            onFocus={() => setShowProductDropdown(true)}
                                            placeholder="Search products by name or SKU..."
                                            className="block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                                        />

                                        {showProductDropdown && productSearch && filteredProducts.length > 0 && (
                                            <div className="absolute z-50 bottom-full mb-1 w-full bg-white border border-gray-200 rounded-md shadow-lg max-h-60 overflow-auto">
                                                {filteredProducts.map((product) => (
                                                    <button
                                                        key={product.id}
                                                        type="button"
                                                        onClick={() => addProduct(product.id)}
                                                        className="w-full px-4 py-2 text-left hover:bg-gray-100 text-sm"
                                                    >
                                                        {product.sku && <span className="text-gray-500">[{product.sku}]</span>}{' '}
                                                        {product.name}
                                                    </button>
                                                ))}
                                            </div>
                                        )}

                                        {showProductDropdown && productSearch && filteredProducts.length === 0 && (
                                            <div className="absolute z-50 bottom-full mb-1 w-full bg-white border border-gray-200 rounded-md shadow-lg p-4 text-sm text-gray-500">
                                                No products found
                                            </div>
                                        )}
                                    </div>

                                    {/* Click outside to close dropdown */}
                                    {showProductDropdown && (
                                        <div
                                            className="fixed inset-0 z-0"
                                            onClick={() => setShowProductDropdown(false)}
                                        />
                                    )}
                                </div>
                            )}
                        </div>
                    </div>
                </div>
            </div>
        </AuthenticatedLayout>
    );
}
