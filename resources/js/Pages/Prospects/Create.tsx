import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head, Link, useForm } from '@inertiajs/react';
import { useState, useEffect } from 'react';

interface Product {
    id: number;
    name: string;
    sku: string | null;
}

interface Contact {
    name: string;
    email: string;
}

interface Props {
    products: Product[];
}

interface FormData {
    company_name: string;
    company_urls: string[];
    contacts: Contact[];
    product_ids: number[];
}

interface TouchedFields {
    company_name: boolean;
    [key: string]: boolean;
}

export default function Create({ products = [] }: Props) {
    const { data, setData, post, processing, errors: formErrors } = useForm<FormData>({
        company_name: '',
        company_urls: [],
        contacts: [],
        product_ids: [],
    });

    const [newUrl, setNewUrl] = useState('');

    // Cast errors to include potential general error from server
    const errors = formErrors as typeof formErrors & { general?: string };

    const [touched, setTouched] = useState<TouchedFields>({ company_name: false });
    const [validationErrors, setValidationErrors] = useState<Record<string, string>>({});
    const [productSearch, setProductSearch] = useState('');
    const [showProductDropdown, setShowProductDropdown] = useState(false);

    // Real-time validation
    useEffect(() => {
        const errors: Record<string, string> = {};

        if (touched.company_name) {
            if (!data.company_name.trim()) {
                errors.company_name = 'Company name is required';
            } else if (data.company_name.trim().length < 2) {
                errors.company_name = 'Company name must be at least 2 characters';
            }
        }

        // Validate contacts
        data.contacts.forEach((contact, index) => {
            if (touched[`contact_${index}_name`] && contact.name && contact.name.length < 1) {
                errors[`contact_${index}_name`] = 'Contact name is required';
            }
            if (touched[`contact_${index}_email`] && contact.email && !/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(contact.email)) {
                errors[`contact_${index}_email`] = 'Invalid email address';
            }
        });

        setValidationErrors(errors);
    }, [data, touched]);

    const markTouched = (field: string) => {
        setTouched(prev => ({ ...prev, [field]: true }));
    };

    const getFieldClass = (field: string, baseClass: string) => {
        const isTouched = touched[field];
        const hasError = validationErrors[field];

        if (!isTouched) return baseClass;
        if (hasError) return `${baseClass} border-red-500 focus:border-red-500 focus:ring-red-500`;
        return `${baseClass} border-green-500 focus:border-green-500 focus:ring-green-500`;
    };

    const addContact = () => {
        setData('contacts', [...data.contacts, { name: '', email: '' }]);
    };

    const removeContact = (index: number) => {
        const newContacts = data.contacts.filter((_, i) => i !== index);
        setData('contacts', newContacts);
    };

    const updateContact = (index: number, field: keyof Contact, value: string) => {
        const newContacts = [...data.contacts];
        newContacts[index] = { ...newContacts[index], [field]: value };
        setData('contacts', newContacts);
    };

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

        // Mark all fields as touched
        setTouched(prev => ({ ...prev, company_name: true }));

        // Check for validation errors
        if (!data.company_name.trim() || data.company_name.trim().length < 2) {
            return;
        }

        post(route('prospects.store'));
    };

    const isFormValid = data.company_name.trim().length >= 2 && Object.keys(validationErrors).length === 0;

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
                <div className="mx-auto max-w-3xl sm:px-6 lg:px-8">
                    <div className="bg-white shadow-sm sm:rounded-lg">
                        <form onSubmit={handleSubmit} className="p-6 space-y-6">
                            {/* General Error */}
                            {errors.general && (
                                <div className="rounded-md bg-red-50 p-4">
                                    <p className="text-sm text-red-700">{errors.general}</p>
                                </div>
                            )}

                            {/* Company Name */}
                            <div>
                                <label htmlFor="company_name" className="block text-sm font-medium text-gray-700">
                                    Company Name <span className="text-red-500">*</span>
                                </label>
                                <input
                                    type="text"
                                    id="company_name"
                                    value={data.company_name}
                                    onChange={(e) => setData('company_name', e.target.value)}
                                    onBlur={() => markTouched('company_name')}
                                    className={getFieldClass(
                                        'company_name',
                                        'mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500'
                                    )}
                                    placeholder="Enter company name"
                                />
                                {validationErrors.company_name && (
                                    <p className="mt-1 text-sm text-red-600">{validationErrors.company_name}</p>
                                )}
                                {errors.company_name && (
                                    <p className="mt-1 text-sm text-red-600">{errors.company_name}</p>
                                )}
                            </div>

                            {/* Company URLs */}
                            <div>
                                <label className="block text-sm font-medium text-gray-700 mb-2">
                                    Company URLs / Email Domains
                                </label>
                                <p className="text-xs text-gray-500 mb-2">
                                    Add the company's website domains (e.g., company.com). These are used to match emails from Gmail.
                                    Email domains will be auto-added when you add contacts with email addresses.
                                </p>

                                {data.company_urls.length > 0 && (
                                    <div className="flex flex-wrap gap-2 mb-3">
                                        {data.company_urls.map((url, index) => (
                                            <span
                                                key={index}
                                                className="inline-flex items-center gap-1 px-3 py-1 bg-gray-100 text-gray-800 rounded-full text-sm"
                                            >
                                                {url}
                                                <button
                                                    type="button"
                                                    onClick={() => setData('company_urls', data.company_urls.filter((_, i) => i !== index))}
                                                    className="ml-1 text-gray-500 hover:text-gray-700"
                                                >
                                                    <svg className="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                        <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M6 18L18 6M6 6l12 12" />
                                                    </svg>
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

                            {/* Buyer Contacts */}
                            <div>
                                <div className="flex items-center justify-between mb-2">
                                    <label className="block text-sm font-medium text-gray-700">
                                        Buyer Contacts
                                    </label>
                                    <button
                                        type="button"
                                        onClick={addContact}
                                        className="text-sm text-indigo-600 hover:text-indigo-800"
                                    >
                                        + Add Contact
                                    </button>
                                </div>

                                {data.contacts.length === 0 && (
                                    <p className="text-sm text-gray-500 italic">No contacts added yet</p>
                                )}

                                <div className="space-y-3">
                                    {data.contacts.map((contact, index) => (
                                        <div key={index} className="flex items-start gap-3 p-3 bg-gray-50 rounded-md">
                                            <div className="flex-1">
                                                <input
                                                    type="text"
                                                    value={contact.name}
                                                    onChange={(e) => updateContact(index, 'name', e.target.value)}
                                                    onBlur={() => markTouched(`contact_${index}_name`)}
                                                    placeholder="Contact name"
                                                    className={getFieldClass(
                                                        `contact_${index}_name`,
                                                        'block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 text-sm'
                                                    )}
                                                />
                                                {validationErrors[`contact_${index}_name`] && (
                                                    <p className="mt-1 text-xs text-red-600">{validationErrors[`contact_${index}_name`]}</p>
                                                )}
                                            </div>
                                            <div className="flex-1">
                                                <input
                                                    type="email"
                                                    value={contact.email}
                                                    onChange={(e) => updateContact(index, 'email', e.target.value)}
                                                    onBlur={() => markTouched(`contact_${index}_email`)}
                                                    placeholder="Email address"
                                                    className={getFieldClass(
                                                        `contact_${index}_email`,
                                                        'block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 text-sm'
                                                    )}
                                                />
                                                {validationErrors[`contact_${index}_email`] && (
                                                    <p className="mt-1 text-xs text-red-600">{validationErrors[`contact_${index}_email`]}</p>
                                                )}
                                            </div>
                                            <button
                                                type="button"
                                                onClick={() => removeContact(index)}
                                                className="text-red-500 hover:text-red-700 p-1"
                                            >
                                                <svg className="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                    <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M6 18L18 6M6 6l12 12" />
                                                </svg>
                                            </button>
                                        </div>
                                    ))}
                                </div>
                            </div>

                            {/* Products of Interest */}
                            <div>
                                <label className="block text-sm font-medium text-gray-700 mb-2">
                                    Products of Interest
                                </label>

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
                                                    <svg className="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                        <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M6 18L18 6M6 6l12 12" />
                                                    </svg>
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

                            {/* Submit */}
                            <div className="flex justify-end gap-3 pt-4 border-t">
                                <Link
                                    href={route('prospects.index')}
                                    className="px-4 py-2 text-sm text-gray-700 hover:text-gray-900"
                                >
                                    Cancel
                                </Link>
                                <button
                                    type="submit"
                                    disabled={processing || !isFormValid}
                                    className="px-4 py-2 bg-indigo-600 text-white text-sm font-medium rounded-md hover:bg-indigo-700 disabled:opacity-50 disabled:cursor-not-allowed"
                                >
                                    {processing ? 'Creating...' : 'Create Prospect'}
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </AuthenticatedLayout>
    );
}
