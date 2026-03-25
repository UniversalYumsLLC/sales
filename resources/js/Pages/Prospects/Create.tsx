import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head, Link, useForm } from '@inertiajs/react';
import { useState, useEffect } from 'react';

interface Product {
    id: number;
    name: string;
    sku: string | null;
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

interface Contact {
    name: string;
    email: string;
}

interface APContact {
    name: string;
    value: string;
}

interface BrokerContact {
    name: string;
    email: string;
}

interface OtherContact {
    name: string;
    email: string;
    function?: string;
}

interface Props {
    products: Product[];
    priceLists: PriceList[];
    paymentTerms: PaymentTerm[];
    shippingTerms: ShippingTerm[];
}

interface ValidationErrors {
    [key: string]: string;
}

interface TouchedFields {
    [key: string]: boolean;
}

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

export default function Create({ products = [], priceLists = [], paymentTerms = [], shippingTerms = [] }: Props) {
    const { data, setData, post, processing, errors: serverErrors } = useForm({
        company_name: '',
        company_urls: [] as string[],
        // Commercial terms (stored as display values for prospects)
        discount_percent: '',
        payment_terms: '',
        shipping_terms: '',
        // Requirements
        shelf_life_requirement: '',
        vendor_guide: '',
        // Broker
        broker: '' as '' | 'true' | 'false',
        broker_company_name: '',
        broker_commission: '',
        broker_contacts: [] as BrokerContact[],
        // Contacts
        buyers: [{ name: '', email: '' }] as Contact[],
        ap_method: '' as '' | 'inbox' | 'portal',
        ap_portal_url: '',
        accounts_payable: [] as APContact[],
        other: [] as OtherContact[],
        // AR Settings
        ar_edi: false,
        ar_consolidated_invoicing: false,
        ar_requires_customer_skus: false,
        ar_invoice_discount: '',
        // Prospect-specific
        customer_type: '',
        product_ids: [] as number[],
    });

    // Cast errors to include potential general error from server
    const errors = serverErrors as typeof serverErrors & { general?: string };

    const [newUrl, setNewUrl] = useState('');
    const [touched, setTouched] = useState<TouchedFields>({});
    const [validationErrors, setValidationErrors] = useState<ValidationErrors>({});
    const [productSearch, setProductSearch] = useState('');
    const [showProductDropdown, setShowProductDropdown] = useState(false);

    const markTouched = (field: string) => {
        setTouched(prev => ({ ...prev, [field]: true }));
    };

    // Real-time validation
    useEffect(() => {
        const errs: ValidationErrors = {};

        // Company name (only required field)
        if (touched.company_name) {
            if (!data.company_name.trim()) {
                errs.company_name = 'Company name is required';
            } else if (data.company_name.trim().length < 2) {
                errs.company_name = 'Company name must be at least 2 characters';
            }
        }

        // Shelf life (optional, but validate range if provided)
        if (touched.shelf_life_requirement && data.shelf_life_requirement) {
            const days = parseInt(data.shelf_life_requirement);
            if (isNaN(days) || days < 30) {
                errs.shelf_life_requirement = 'Must be at least 30 days';
            } else if (days > 365) {
                errs.shelf_life_requirement = 'Cannot exceed 365 days';
            }
        }

        // Vendor guide (optional, but validate URL if provided)
        if (touched.vendor_guide && data.vendor_guide && !isValidUrl(data.vendor_guide)) {
            errs.vendor_guide = 'Must be a valid URL (https://...)';
        }

        // Broker fields (only required when broker is true)
        if (data.broker === 'true') {
            if (touched.broker_company_name && !data.broker_company_name) {
                errs.broker_company_name = 'Broker company name is required';
            }
            if (touched.broker_commission) {
                if (!data.broker_commission) {
                    errs.broker_commission = 'Broker commission is required';
                } else {
                    const commission = parseFloat(data.broker_commission);
                    if (isNaN(commission) || commission < 0 || commission > 100) {
                        errs.broker_commission = 'Commission must be between 0 and 100';
                    }
                }
            }
            data.broker_contacts.forEach((contact, idx) => {
                if (touched[`broker_contacts.${idx}.name`] && !contact.name) {
                    errs[`broker_contacts.${idx}.name`] = 'Name is required';
                }
                if (touched[`broker_contacts.${idx}.email`] && contact.email && !isValidEmail(contact.email)) {
                    errs[`broker_contacts.${idx}.email`] = 'Must be a valid email';
                }
            });
        }

        // Buyer validation (optional, but validate if filled)
        data.buyers.forEach((buyer, idx) => {
            if (touched[`buyers.${idx}.name`] && buyer.name && buyer.name.length < 2) {
                errs[`buyers.${idx}.name`] = 'Name must be at least 2 characters';
            }
            if (touched[`buyers.${idx}.email`] && buyer.email && !isValidEmail(buyer.email)) {
                errs[`buyers.${idx}.email`] = 'Must be a valid email';
            }
        });

        // AP validation
        if (data.ap_method === 'portal' && touched.ap_portal_url && data.ap_portal_url && !isValidUrl(data.ap_portal_url)) {
            errs.ap_portal_url = 'Must be a valid URL (https://...)';
        }
        if (data.ap_method === 'inbox') {
            data.accounts_payable.forEach((ap, idx) => {
                if (touched[`ap.${idx}.name`] && ap.name && ap.name.length < 2) {
                    errs[`ap.${idx}.name`] = 'Name must be at least 2 characters';
                }
                if (touched[`ap.${idx}.value`] && ap.value) {
                    const isEmail = isValidEmail(ap.value);
                    const isUrl2 = isValidUrl(ap.value);
                    if (!isEmail && !isUrl2) {
                        errs[`ap.${idx}.value`] = 'Must be a valid email or URL';
                    }
                }
            });
        }

        // Other contacts
        data.other.forEach((contact, idx) => {
            if (touched[`other.${idx}.name`] && contact.name && contact.name.length < 2) {
                errs[`other.${idx}.name`] = 'Name must be at least 2 characters';
            }
            if (touched[`other.${idx}.email`] && contact.email && !isValidEmail(contact.email)) {
                errs[`other.${idx}.email`] = 'Must be a valid email';
            }
        });

        // AR invoice discount
        if (touched.ar_invoice_discount && data.ar_invoice_discount) {
            const disc = parseFloat(data.ar_invoice_discount);
            if (isNaN(disc) || disc < 0 || disc > 100) {
                errs.ar_invoice_discount = 'Must be between 0 and 100';
            }
        }

        setValidationErrors(errs);
    }, [data, touched]);

    // Contact management
    const addBuyer = () => setData('buyers', [...data.buyers, { name: '', email: '' }]);
    const removeBuyer = (idx: number) => setData('buyers', data.buyers.filter((_, i) => i !== idx));
    const updateBuyer = (idx: number, field: keyof Contact, value: string) => {
        const updated = [...data.buyers];
        updated[idx] = { ...updated[idx], [field]: value };
        setData('buyers', updated);
    };

    const addAP = () => setData('accounts_payable', [...data.accounts_payable, { name: '', value: '' }]);
    const removeAP = (idx: number) => setData('accounts_payable', data.accounts_payable.filter((_, i) => i !== idx));
    const updateAP = (idx: number, field: keyof APContact, value: string) => {
        const updated = [...data.accounts_payable];
        updated[idx] = { ...updated[idx], [field]: value };
        setData('accounts_payable', updated);
    };

    const addOther = () => setData('other', [...data.other, { name: '', email: '', function: '' }]);
    const removeOther = (idx: number) => setData('other', data.other.filter((_, i) => i !== idx));
    const updateOther = (idx: number, field: keyof OtherContact, value: string) => {
        const updated = [...data.other];
        updated[idx] = { ...updated[idx], [field]: value };
        setData('other', updated);
    };

    const addBrokerContact = () => setData('broker_contacts', [...data.broker_contacts, { name: '', email: '' }]);
    const removeBrokerContact = (idx: number) => setData('broker_contacts', data.broker_contacts.filter((_, i) => i !== idx));
    const updateBrokerContact = (idx: number, field: keyof BrokerContact, value: string) => {
        const updated = [...data.broker_contacts];
        updated[idx] = { ...updated[idx], [field]: value };
        setData('broker_contacts', updated);
    };

    // Product management
    const addProduct = (productId: number) => {
        if (!data.product_ids.includes(productId)) {
            setData('product_ids', [...data.product_ids, productId]);
        }
        setProductSearch('');
        setShowProductDropdown(false);
    };
    const removeProduct = (productId: number) => {
        setData('product_ids', data.product_ids.filter(id => id !== productId));
    };
    const filteredProducts = products.filter(p =>
        !data.product_ids.includes(p.id) &&
        (p.name?.toLowerCase().includes(productSearch.toLowerCase()) ||
         p.sku?.toLowerCase().includes(productSearch.toLowerCase()))
    ).slice(0, 10);
    const selectedProducts = products.filter(p => data.product_ids.includes(p.id));

    const handleSubmit = (e: React.FormEvent) => {
        e.preventDefault();
        setTouched(prev => ({ ...prev, company_name: true }));

        if (!data.company_name.trim() || data.company_name.trim().length < 2) {
            return;
        }

        // Transform data before submission:
        // Convert dropdown IDs back to stored values for prospect
        const selectedPriceList = priceLists.find(pl => pl.id.toString() === data.discount_percent);
        const selectedPaymentTerm = paymentTerms.find(pt => pt.id.toString() === data.payment_terms);
        const selectedShippingTerm = shippingTerms.find(st => st.id.toString() === data.shipping_terms);

        // Build accounts_payable from ap_method
        let accountsPayable: APContact[] = [];
        if (data.ap_method === 'portal' && data.ap_portal_url) {
            accountsPayable = [{ name: 'AP Portal', value: data.ap_portal_url }];
        } else if (data.ap_method === 'inbox') {
            accountsPayable = data.accounts_payable.filter(ap => ap.name.trim());
        }

        // Use setData to update the form before posting - but since Inertia's post
        // uses the current data state, we need to use the transform option
        post(route('prospects.store'), {
            // @ts-expect-error - Inertia transform types
            transform: (formData: typeof data) => ({
                company_name: formData.company_name,
                company_urls: formData.company_urls,
                discount_percent: selectedPriceList?.discount_percent ?? null,
                payment_terms: selectedPaymentTerm?.name || null,
                shipping_terms: selectedShippingTerm?.name || null,
                shelf_life_requirement: formData.shelf_life_requirement ? parseInt(formData.shelf_life_requirement) : null,
                vendor_guide: formData.vendor_guide || null,
                broker: formData.broker === 'true',
                broker_company_name: formData.broker_company_name || null,
                broker_commission: formData.broker_commission ? parseFloat(formData.broker_commission) : null,
                broker_contacts: formData.broker_contacts.filter(c => c.name.trim()),
                buyers: formData.buyers.filter(b => b.name.trim()),
                accounts_payable: accountsPayable,
                other: formData.other.filter(o => o.name.trim()),
                ar_edi: formData.ar_edi,
                ar_consolidated_invoicing: formData.ar_consolidated_invoicing,
                ar_requires_customer_skus: formData.ar_requires_customer_skus,
                ar_invoice_discount: formData.ar_invoice_discount ? parseFloat(formData.ar_invoice_discount) : null,
                customer_type: formData.customer_type || null,
                product_ids: formData.product_ids,
            }),
        });
    };

    const isFormValid = data.company_name.trim().length >= 2 && Object.keys(validationErrors).length === 0;

    const inputClass = 'mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500';
    const selectClass = 'mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500';
    const errorInputClass = 'mt-1 block w-full rounded-md border-red-300 shadow-sm focus:border-red-500 focus:ring-red-500';

    const getClass = (field: string, base: string = inputClass) => {
        if (!touched[field]) return base;
        if (validationErrors[field]) return errorInputClass;
        return base;
    };

    return (
        <AuthenticatedLayout
            header={
                <div className="flex items-center justify-between">
                    <h2 className="text-xl font-semibold leading-tight text-gray-800">
                        New Prospect
                    </h2>
                    <Link
                        href={route('prospects.index')}
                        className="text-sm text-gray-500 hover:text-gray-700"
                    >
                        Cancel
                    </Link>
                </div>
            }
        >
            <Head title="New Prospect" />

            <div className="py-12">
                <div className="mx-auto max-w-4xl sm:px-6 lg:px-8">
                    <form onSubmit={handleSubmit} className="space-y-6">
                        {/* General Error */}
                        {errors.general && (
                            <div className="rounded-md bg-red-50 p-4">
                                <p className="text-sm text-red-700">{errors.general}</p>
                            </div>
                        )}

                        {/* Company Information */}
                        <div className="bg-white shadow-sm sm:rounded-lg p-6">
                            <h3 className="text-lg font-medium text-gray-900 mb-4">Company Information</h3>

                            <div className="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-3">
                                {/* Company Name */}
                                <div className="sm:col-span-2 lg:col-span-3">
                                    <label className="block text-sm font-medium text-gray-700">
                                        Company Name <span className="text-red-500">*</span>
                                    </label>
                                    <input
                                        type="text"
                                        value={data.company_name}
                                        onChange={(e) => setData('company_name', e.target.value)}
                                        onBlur={() => markTouched('company_name')}
                                        className={getClass('company_name')}
                                        placeholder="Enter company name"
                                    />
                                    {validationErrors.company_name && <p className="mt-1 text-sm text-red-600">{validationErrors.company_name}</p>}
                                    {errors.company_name && <p className="mt-1 text-sm text-red-600">{errors.company_name}</p>}
                                </div>

                                {/* Customer Type */}
                                <div>
                                    <label className="block text-sm font-medium text-gray-700">Customer Type</label>
                                    <input
                                        type="text"
                                        value={data.customer_type}
                                        onChange={(e) => setData('customer_type', e.target.value)}
                                        className={inputClass}
                                        placeholder="e.g., Retail, Foodservice"
                                    />
                                </div>

                                {/* Discount Level */}
                                <div>
                                    <label className="block text-sm font-medium text-gray-700">Discount on Price List</label>
                                    <select
                                        value={data.discount_percent}
                                        onChange={(e) => setData('discount_percent', e.target.value)}
                                        className={selectClass}
                                    >
                                        <option value="">Select...</option>
                                        {priceLists.map((pl) => (
                                            <option key={pl.id} value={pl.id}>{pl.discount_percent}% Discount</option>
                                        ))}
                                    </select>
                                </div>

                                {/* Payment Terms */}
                                <div>
                                    <label className="block text-sm font-medium text-gray-700">Payment Terms</label>
                                    <select
                                        value={data.payment_terms}
                                        onChange={(e) => setData('payment_terms', e.target.value)}
                                        className={selectClass}
                                    >
                                        <option value="">Select...</option>
                                        {paymentTerms.map((pt) => (
                                            <option key={pt.id} value={pt.id}>{pt.name}</option>
                                        ))}
                                    </select>
                                </div>

                                {/* Shipping Terms */}
                                <div>
                                    <label className="block text-sm font-medium text-gray-700">Shipping Terms</label>
                                    <select
                                        value={data.shipping_terms}
                                        onChange={(e) => setData('shipping_terms', e.target.value)}
                                        className={selectClass}
                                    >
                                        <option value="">Select...</option>
                                        {shippingTerms.map((st) => (
                                            <option key={st.id} value={st.id}>{st.name}</option>
                                        ))}
                                    </select>
                                </div>

                                {/* Shelf Life */}
                                <div>
                                    <label className="block text-sm font-medium text-gray-700">Shelf Life Requirement (days)</label>
                                    <input
                                        type="number"
                                        value={data.shelf_life_requirement}
                                        onChange={(e) => setData('shelf_life_requirement', e.target.value)}
                                        onBlur={() => markTouched('shelf_life_requirement')}
                                        min="30"
                                        max="365"
                                        placeholder="e.g., 90"
                                        className={getClass('shelf_life_requirement')}
                                    />
                                    {validationErrors.shelf_life_requirement && <p className="mt-1 text-sm text-red-600">{validationErrors.shelf_life_requirement}</p>}
                                </div>

                                {/* Vendor Guide */}
                                <div className="sm:col-span-2">
                                    <label className="block text-sm font-medium text-gray-700">Vendor Guide URL</label>
                                    <input
                                        type="url"
                                        value={data.vendor_guide}
                                        onChange={(e) => setData('vendor_guide', e.target.value)}
                                        onBlur={() => markTouched('vendor_guide')}
                                        placeholder="https://..."
                                        className={getClass('vendor_guide')}
                                    />
                                    {validationErrors.vendor_guide && <p className="mt-1 text-sm text-red-600">{validationErrors.vendor_guide}</p>}
                                </div>

                                {/* Company URLs */}
                                <div className="sm:col-span-2 lg:col-span-3">
                                    <label className="block text-sm font-medium text-gray-700">Company URLs / Email Domains</label>
                                    <p className="text-xs text-gray-500 mb-2">
                                        Domains used for matching emails from Gmail. Email domains are auto-added from contacts.
                                    </p>
                                    {data.company_urls.length > 0 && (
                                        <div className="flex flex-wrap gap-2 mb-2">
                                            {data.company_urls.map((url, index) => (
                                                <span key={index} className="inline-flex items-center gap-1 px-2 py-1 bg-gray-100 text-gray-800 rounded text-sm">
                                                    {url}
                                                    <button
                                                        type="button"
                                                        onClick={() => setData('company_urls', data.company_urls.filter((_, i) => i !== index))}
                                                        className="text-gray-500 hover:text-gray-700"
                                                    >
                                                        <svg className="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M6 18L18 6M6 6l12 12" /></svg>
                                                    </button>
                                                </span>
                                            ))}
                                        </div>
                                    )}
                                    <div className="flex gap-2">
                                        <input
                                            type="text"
                                            value={newUrl}
                                            onChange={(e) => setNewUrl(e.target.value)}
                                            onKeyDown={(e) => {
                                                if (e.key === 'Enter') {
                                                    e.preventDefault();
                                                    if (newUrl.trim() && !data.company_urls.includes(newUrl.trim().toLowerCase())) {
                                                        setData('company_urls', [...data.company_urls, newUrl.trim().toLowerCase()]);
                                                        setNewUrl('');
                                                    }
                                                }
                                            }}
                                            placeholder="Enter domain (e.g., company.com)"
                                            className="flex-1 rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 text-sm"
                                        />
                                        <button
                                            type="button"
                                            onClick={() => {
                                                if (newUrl.trim() && !data.company_urls.includes(newUrl.trim().toLowerCase())) {
                                                    setData('company_urls', [...data.company_urls, newUrl.trim().toLowerCase()]);
                                                    setNewUrl('');
                                                }
                                            }}
                                            className="px-3 py-2 bg-gray-100 text-gray-700 text-sm rounded-md hover:bg-gray-200"
                                        >
                                            Add
                                        </button>
                                    </div>
                                </div>

                                {/* Broker */}
                                <div>
                                    <label className="block text-sm font-medium text-gray-700">Uses Broker</label>
                                    <select
                                        value={data.broker}
                                        onChange={(e) => setData('broker', e.target.value as '' | 'true' | 'false')}
                                        className={selectClass}
                                    >
                                        <option value="">Select...</option>
                                        <option value="false">No</option>
                                        <option value="true">Yes</option>
                                    </select>
                                </div>

                                {/* AR Settings - inline with other company fields */}
                                <div className="sm:col-span-2 lg:col-span-3 border-t border-gray-100 pt-4 mt-2">
                                    <h4 className="text-sm font-medium text-gray-700 mb-3">Invoicing Preferences</h4>
                                    <div className="grid grid-cols-2 sm:grid-cols-4 gap-4">
                                        <label className="flex items-center gap-2 cursor-pointer">
                                            <input
                                                type="checkbox"
                                                checked={data.ar_edi}
                                                onChange={(e) => setData('ar_edi', e.target.checked)}
                                                className="rounded border-gray-300 text-indigo-600 focus:ring-indigo-500"
                                            />
                                            <span className="text-sm text-gray-700">EDI</span>
                                        </label>
                                        <label className="flex items-center gap-2 cursor-pointer">
                                            <input
                                                type="checkbox"
                                                checked={data.ar_consolidated_invoicing}
                                                onChange={(e) => setData('ar_consolidated_invoicing', e.target.checked)}
                                                className="rounded border-gray-300 text-indigo-600 focus:ring-indigo-500"
                                            />
                                            <span className="text-sm text-gray-700">Consolidated Invoicing</span>
                                        </label>
                                        <label className="flex items-center gap-2 cursor-pointer">
                                            <input
                                                type="checkbox"
                                                checked={data.ar_requires_customer_skus}
                                                onChange={(e) => setData('ar_requires_customer_skus', e.target.checked)}
                                                className="rounded border-gray-300 text-indigo-600 focus:ring-indigo-500"
                                            />
                                            <span className="text-sm text-gray-700">Requires Customer SKUs</span>
                                        </label>
                                        <div>
                                            <label className="block text-sm text-gray-700 mb-1">Invoice Discount %</label>
                                            <input
                                                type="number"
                                                value={data.ar_invoice_discount}
                                                onChange={(e) => setData('ar_invoice_discount', e.target.value)}
                                                onBlur={() => markTouched('ar_invoice_discount')}
                                                min="0"
                                                max="100"
                                                step="0.01"
                                                placeholder="0.00"
                                                className="block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 text-sm"
                                            />
                                            {validationErrors.ar_invoice_discount && <p className="mt-1 text-xs text-red-600">{validationErrors.ar_invoice_discount}</p>}
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        {/* Broker Section */}
                        {data.broker === 'true' && (
                            <div className="bg-white shadow-sm sm:rounded-lg p-6 border-l-4 border-purple-400">
                                <h3 className="text-lg font-medium text-gray-900 mb-4">Broker Information</h3>
                                <div className="grid grid-cols-1 gap-4 sm:grid-cols-2">
                                    <div>
                                        <label className="block text-sm font-medium text-gray-700">Broker Company Name</label>
                                        <input
                                            type="text"
                                            value={data.broker_company_name}
                                            onChange={(e) => setData('broker_company_name', e.target.value)}
                                            onBlur={() => markTouched('broker_company_name')}
                                            className={getClass('broker_company_name')}
                                            placeholder="e.g., HRG Brokers"
                                        />
                                        {validationErrors.broker_company_name && <p className="mt-1 text-sm text-red-600">{validationErrors.broker_company_name}</p>}
                                    </div>
                                    <div>
                                        <label className="block text-sm font-medium text-gray-700">Commission (%)</label>
                                        <input
                                            type="number"
                                            value={data.broker_commission}
                                            onChange={(e) => setData('broker_commission', e.target.value)}
                                            onBlur={() => markTouched('broker_commission')}
                                            min="0"
                                            max="100"
                                            step="0.1"
                                            placeholder="0.0"
                                            className={getClass('broker_commission', 'mt-1 block w-32 rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500')}
                                        />
                                        {validationErrors.broker_commission && <p className="mt-1 text-sm text-red-600">{validationErrors.broker_commission}</p>}
                                    </div>
                                </div>

                                {/* Broker Contacts */}
                                <div className="mt-4">
                                    <div className="flex items-center justify-between mb-2">
                                        <h4 className="text-sm font-medium text-gray-700">Broker Contacts</h4>
                                        <button type="button" onClick={addBrokerContact} className="text-sm text-indigo-600 hover:text-indigo-800">+ Add</button>
                                    </div>
                                    {data.broker_contacts.length === 0 && <p className="text-sm text-gray-400 italic">No broker contacts added</p>}
                                    <div className="space-y-2">
                                        {data.broker_contacts.map((contact, idx) => (
                                            <div key={idx} className="flex gap-2">
                                                <input
                                                    type="text"
                                                    value={contact.name}
                                                    onChange={(e) => updateBrokerContact(idx, 'name', e.target.value)}
                                                    onBlur={() => markTouched(`broker_contacts.${idx}.name`)}
                                                    placeholder="Name"
                                                    className={`flex-1 rounded-md text-sm shadow-sm focus:ring-indigo-500 ${validationErrors[`broker_contacts.${idx}.name`] ? 'border-red-300' : 'border-gray-300'}`}
                                                />
                                                <input
                                                    type="email"
                                                    value={contact.email}
                                                    onChange={(e) => updateBrokerContact(idx, 'email', e.target.value)}
                                                    onBlur={() => markTouched(`broker_contacts.${idx}.email`)}
                                                    placeholder="Email"
                                                    className={`flex-1 rounded-md text-sm shadow-sm focus:ring-indigo-500 ${validationErrors[`broker_contacts.${idx}.email`] ? 'border-red-300' : 'border-gray-300'}`}
                                                />
                                                <button type="button" onClick={() => removeBrokerContact(idx)} className="text-red-400 hover:text-red-600">
                                                    <svg className="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M6 18L18 6M6 6l12 12" /></svg>
                                                </button>
                                            </div>
                                        ))}
                                    </div>
                                </div>
                            </div>
                        )}

                        {/* Contacts */}
                        <div className="bg-white shadow-sm sm:rounded-lg p-6">
                            <h3 className="text-lg font-medium text-gray-900 mb-4">Contacts</h3>

                            {/* Buyers */}
                            <div className="mb-6">
                                <div className="flex items-center justify-between mb-2">
                                    <h4 className="text-sm font-medium text-gray-700">Buyers</h4>
                                    <button type="button" onClick={addBuyer} className="text-sm text-indigo-600 hover:text-indigo-800">+ Add</button>
                                </div>
                                {data.buyers.length === 0 && <p className="text-sm text-gray-400 italic">No buyer contacts</p>}
                                <div className="space-y-2">
                                    {data.buyers.map((buyer, idx) => (
                                        <div key={idx} className="flex gap-2">
                                            <input
                                                type="text"
                                                value={buyer.name}
                                                onChange={(e) => updateBuyer(idx, 'name', e.target.value)}
                                                onBlur={() => markTouched(`buyers.${idx}.name`)}
                                                placeholder="Name"
                                                className={`flex-1 rounded-md text-sm shadow-sm focus:ring-indigo-500 ${validationErrors[`buyers.${idx}.name`] ? 'border-red-300' : 'border-gray-300'}`}
                                            />
                                            <input
                                                type="email"
                                                value={buyer.email}
                                                onChange={(e) => updateBuyer(idx, 'email', e.target.value)}
                                                onBlur={() => markTouched(`buyers.${idx}.email`)}
                                                placeholder="Email"
                                                className={`flex-1 rounded-md text-sm shadow-sm focus:ring-indigo-500 ${validationErrors[`buyers.${idx}.email`] ? 'border-red-300' : 'border-gray-300'}`}
                                            />
                                            <button type="button" onClick={() => removeBuyer(idx)} className="text-red-400 hover:text-red-600">
                                                <svg className="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M6 18L18 6M6 6l12 12" /></svg>
                                            </button>
                                        </div>
                                    ))}
                                </div>
                            </div>

                            {/* Accounts Payable */}
                            <div className="mb-6">
                                <h4 className="text-sm font-medium text-gray-700 mb-2">Accounts Payable</h4>
                                <div className="flex gap-4 mb-3">
                                    <label className="flex items-center gap-2 cursor-pointer">
                                        <input type="radio" name="ap_method" checked={data.ap_method === ''} onChange={() => setData('ap_method', '')} className="text-indigo-600 focus:ring-indigo-500" />
                                        <span className="text-sm text-gray-700">Not set</span>
                                    </label>
                                    <label className="flex items-center gap-2 cursor-pointer">
                                        <input type="radio" name="ap_method" checked={data.ap_method === 'inbox'} onChange={() => setData('ap_method', 'inbox')} className="text-indigo-600 focus:ring-indigo-500" />
                                        <span className="text-sm text-gray-700">Email inbox</span>
                                    </label>
                                    <label className="flex items-center gap-2 cursor-pointer">
                                        <input type="radio" name="ap_method" checked={data.ap_method === 'portal'} onChange={() => setData('ap_method', 'portal')} className="text-indigo-600 focus:ring-indigo-500" />
                                        <span className="text-sm text-gray-700">Web portal</span>
                                    </label>
                                </div>
                                {data.ap_method === 'portal' && (
                                    <div className="ml-6">
                                        <input
                                            type="url"
                                            value={data.ap_portal_url}
                                            onChange={(e) => setData('ap_portal_url', e.target.value)}
                                            onBlur={() => markTouched('ap_portal_url')}
                                            placeholder="https://portal.example.com/invoices"
                                            className={getClass('ap_portal_url')}
                                        />
                                        {validationErrors.ap_portal_url && <p className="mt-1 text-sm text-red-600">{validationErrors.ap_portal_url}</p>}
                                    </div>
                                )}
                                {data.ap_method === 'inbox' && (
                                    <div className="ml-6">
                                        <div className="flex items-center justify-between mb-2">
                                            <span className="text-xs text-gray-500">AP contacts</span>
                                            <button type="button" onClick={addAP} className="text-sm text-indigo-600 hover:text-indigo-800">+ Add</button>
                                        </div>
                                        {data.accounts_payable.length === 0 && <p className="text-sm text-gray-400 italic">Click Add to create an AP contact</p>}
                                        <div className="space-y-2">
                                            {data.accounts_payable.map((ap, idx) => (
                                                <div key={idx} className="flex gap-2">
                                                    <input
                                                        type="text"
                                                        value={ap.name}
                                                        onChange={(e) => updateAP(idx, 'name', e.target.value)}
                                                        onBlur={() => markTouched(`ap.${idx}.name`)}
                                                        placeholder="Name"
                                                        className={`flex-1 rounded-md text-sm shadow-sm focus:ring-indigo-500 ${validationErrors[`ap.${idx}.name`] ? 'border-red-300' : 'border-gray-300'}`}
                                                    />
                                                    <input
                                                        type="text"
                                                        value={ap.value}
                                                        onChange={(e) => updateAP(idx, 'value', e.target.value)}
                                                        onBlur={() => markTouched(`ap.${idx}.value`)}
                                                        placeholder="Email or portal URL"
                                                        className={`flex-1 rounded-md text-sm shadow-sm focus:ring-indigo-500 ${validationErrors[`ap.${idx}.value`] ? 'border-red-300' : 'border-gray-300'}`}
                                                    />
                                                    <button type="button" onClick={() => removeAP(idx)} className="text-red-400 hover:text-red-600">
                                                        <svg className="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M6 18L18 6M6 6l12 12" /></svg>
                                                    </button>
                                                </div>
                                            ))}
                                        </div>
                                    </div>
                                )}
                            </div>

                            {/* Other Contacts */}
                            <div>
                                <div className="flex items-center justify-between mb-2">
                                    <h4 className="text-sm font-medium text-gray-700">Other Contacts</h4>
                                    <button type="button" onClick={addOther} className="text-sm text-indigo-600 hover:text-indigo-800">+ Add</button>
                                </div>
                                {data.other.length === 0 && <p className="text-sm text-gray-400 italic">No other contacts</p>}
                                <div className="space-y-2">
                                    {data.other.map((contact, idx) => (
                                        <div key={idx} className="flex gap-2">
                                            <input
                                                type="text"
                                                value={contact.name}
                                                onChange={(e) => updateOther(idx, 'name', e.target.value)}
                                                onBlur={() => markTouched(`other.${idx}.name`)}
                                                placeholder="Name"
                                                className={`flex-1 rounded-md text-sm shadow-sm focus:ring-indigo-500 ${validationErrors[`other.${idx}.name`] ? 'border-red-300' : 'border-gray-300'}`}
                                            />
                                            <input
                                                type="text"
                                                value={contact.function || ''}
                                                onChange={(e) => updateOther(idx, 'function', e.target.value)}
                                                placeholder="Function"
                                                className="w-28 rounded-md text-sm shadow-sm focus:ring-indigo-500 border-gray-300"
                                            />
                                            <input
                                                type="email"
                                                value={contact.email}
                                                onChange={(e) => updateOther(idx, 'email', e.target.value)}
                                                onBlur={() => markTouched(`other.${idx}.email`)}
                                                placeholder="Email"
                                                className={`flex-1 rounded-md text-sm shadow-sm focus:ring-indigo-500 ${validationErrors[`other.${idx}.email`] ? 'border-red-300' : 'border-gray-300'}`}
                                            />
                                            <button type="button" onClick={() => removeOther(idx)} className="text-red-400 hover:text-red-600">
                                                <svg className="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M6 18L18 6M6 6l12 12" /></svg>
                                            </button>
                                        </div>
                                    ))}
                                </div>
                            </div>
                        </div>

                        {/* Products of Interest */}
                        <div className="bg-white shadow-sm sm:rounded-lg p-6">
                            <h3 className="text-lg font-medium text-gray-900 mb-4">Products of Interest</h3>

                            {selectedProducts.length > 0 && (
                                <div className="flex flex-wrap gap-2 mb-3">
                                    {selectedProducts.map((product) => (
                                        <span key={product.id} className="inline-flex items-center gap-1 px-3 py-1 bg-indigo-100 text-indigo-800 rounded-full text-sm">
                                            {product.sku && <span className="text-indigo-600">[{product.sku}]</span>}
                                            {product.name}
                                            <button type="button" onClick={() => removeProduct(product.id)} className="ml-1 text-indigo-600 hover:text-indigo-800">
                                                <svg className="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M6 18L18 6M6 6l12 12" /></svg>
                                            </button>
                                        </span>
                                    ))}
                                </div>
                            )}

                            <div className="relative">
                                <input
                                    type="text"
                                    value={productSearch}
                                    onChange={(e) => { setProductSearch(e.target.value); setShowProductDropdown(true); }}
                                    onFocus={() => setShowProductDropdown(true)}
                                    placeholder="Search products by name or SKU..."
                                    className="block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                                />
                                {showProductDropdown && productSearch && filteredProducts.length > 0 && (
                                    <div className="absolute z-50 bottom-full mb-1 w-full bg-white border border-gray-200 rounded-md shadow-lg max-h-60 overflow-auto">
                                        {filteredProducts.map((product) => (
                                            <button key={product.id} type="button" onClick={() => addProduct(product.id)} className="w-full px-4 py-2 text-left hover:bg-gray-100 text-sm">
                                                {product.sku && <span className="text-gray-500">[{product.sku}]</span>} {product.name}
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
                            {showProductDropdown && (
                                <div className="fixed inset-0 z-0" onClick={() => setShowProductDropdown(false)} />
                            )}
                        </div>

                        {/* Submit */}
                        <div className="flex justify-end gap-3 pt-2">
                            <Link
                                href={route('prospects.index')}
                                className="px-4 py-2 text-sm text-gray-700 hover:text-gray-900"
                            >
                                Cancel
                            </Link>
                            <button
                                type="submit"
                                disabled={processing || !isFormValid}
                                className="px-6 py-2 bg-indigo-600 text-white text-sm font-medium rounded-md hover:bg-indigo-700 disabled:opacity-50 disabled:cursor-not-allowed"
                            >
                                {processing ? 'Creating...' : 'Create Prospect'}
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </AuthenticatedLayout>
    );
}
