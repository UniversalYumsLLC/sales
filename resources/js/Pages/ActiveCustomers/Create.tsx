import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head, Link, useForm } from '@inertiajs/react';
import { FormEventHandler, useState, useEffect } from 'react';

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

interface Props {
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

export default function Create({ priceLists = [], paymentTerms = [], shippingTerms = [] }: Props) {
    const { data, setData, post, processing, errors: serverErrors } = useForm({
        name: '',
        sale_price_list: '',
        customer_payment_term: '',
        shipping_terms_category_id: '',
        shelf_life_requirement: '',
        vendor_guide: '',
        buyers: [{ name: '', email: '' }] as Contact[],
        accounts_payable: [] as APContact[],
        logistics: [] as Contact[],
    });

    // Track which fields have been touched
    const [touched, setTouched] = useState<TouchedFields>({});
    const [validationErrors, setValidationErrors] = useState<ValidationErrors>({});

    // Mark field as touched
    const markTouched = (field: string) => {
        setTouched(prev => ({ ...prev, [field]: true }));
    };

    // Real-time validation
    useEffect(() => {
        const errors: ValidationErrors = {};

        // Company name validation
        if (touched.name) {
            if (!data.name) {
                errors.name = 'Company name is required';
            } else if (data.name.length < 2) {
                errors.name = 'Company name must be at least 2 characters';
            }
        }

        // Discount level validation
        if (touched.sale_price_list && !data.sale_price_list) {
            errors.sale_price_list = 'Please select a discount level';
        }

        // Payment terms validation
        if (touched.customer_payment_term && !data.customer_payment_term) {
            errors.customer_payment_term = 'Please select payment terms';
        }

        // Shipping terms validation
        if (touched.shipping_terms_category_id && !data.shipping_terms_category_id) {
            errors.shipping_terms_category_id = 'Please select shipping terms';
        }

        // Shelf life requirement validation (30-365 days)
        if (touched.shelf_life_requirement) {
            if (!data.shelf_life_requirement) {
                errors.shelf_life_requirement = 'Shelf life requirement is required';
            } else {
                const days = parseInt(data.shelf_life_requirement);
                if (isNaN(days) || days < 30) {
                    errors.shelf_life_requirement = 'Must be at least 30 days';
                } else if (days > 365) {
                    errors.shelf_life_requirement = 'Cannot exceed 365 days';
                }
            }
        }

        // Vendor guide URL validation (optional, but must be valid URL if provided)
        if (touched.vendor_guide && data.vendor_guide && !isValidUrl(data.vendor_guide)) {
            errors.vendor_guide = 'Must be a valid URL (https://...)';
        }

        // Buyer validation
        data.buyers.forEach((buyer, idx) => {
            if (touched[`buyers.${idx}.name`]) {
                if (!buyer.name) {
                    errors[`buyers.${idx}.name`] = 'Name is required';
                } else if (buyer.name.length < 2) {
                    errors[`buyers.${idx}.name`] = 'Name must be at least 2 characters';
                }
            }
            if (touched[`buyers.${idx}.email`]) {
                if (!buyer.email) {
                    errors[`buyers.${idx}.email`] = 'Email is required';
                } else if (!isValidEmail(buyer.email)) {
                    errors[`buyers.${idx}.email`] = 'Must be a valid email';
                }
            }
        });

        // AP validation
        data.accounts_payable.forEach((ap, idx) => {
            if (touched[`accounts_payable.${idx}.name`]) {
                if (!ap.name) {
                    errors[`accounts_payable.${idx}.name`] = 'Name is required';
                } else if (ap.name.length < 2) {
                    errors[`accounts_payable.${idx}.name`] = 'Name must be at least 2 characters';
                }
            }
            if (touched[`accounts_payable.${idx}.value`]) {
                if (!ap.value) {
                    errors[`accounts_payable.${idx}.value`] = 'Email or URL is required';
                } else if (!isValidEmail(ap.value) && !isValidUrl(ap.value)) {
                    errors[`accounts_payable.${idx}.value`] = 'Must be a valid email or URL';
                }
            }
        });

        // Logistics validation
        data.logistics.forEach((logistics, idx) => {
            if (touched[`logistics.${idx}.name`]) {
                if (!logistics.name) {
                    errors[`logistics.${idx}.name`] = 'Name is required';
                } else if (logistics.name.length < 2) {
                    errors[`logistics.${idx}.name`] = 'Name must be at least 2 characters';
                }
            }
            if (touched[`logistics.${idx}.email`]) {
                if (!logistics.email) {
                    errors[`logistics.${idx}.email`] = 'Email is required';
                } else if (!isValidEmail(logistics.email)) {
                    errors[`logistics.${idx}.email`] = 'Must be a valid email';
                }
            }
        });

        setValidationErrors(errors);
    }, [data, touched]);

    // Get input class based on validation state
    const getInputClass = (field: string, value: string, isValid?: boolean) => {
        const baseClass = 'mt-1 block w-full rounded-md shadow-sm focus:ring-2';

        if (!touched[field]) {
            return `${baseClass} border-gray-300 focus:border-indigo-500 focus:ring-indigo-500`;
        }

        const hasError = validationErrors[field] || serverErrors[field as keyof typeof serverErrors];
        const fieldIsValid = isValid !== undefined ? isValid : (value && !hasError);

        if (hasError) {
            return `${baseClass} border-red-300 focus:border-red-500 focus:ring-red-500`;
        }
        if (fieldIsValid) {
            return `${baseClass} border-green-300 focus:border-green-500 focus:ring-green-500`;
        }
        return `${baseClass} border-gray-300 focus:border-indigo-500 focus:ring-indigo-500`;
    };

    // Get select class based on validation state
    const getSelectClass = (field: string, value: string) => {
        const baseClass = 'mt-1 block w-full rounded-md shadow-sm focus:ring-2';

        if (!touched[field]) {
            return `${baseClass} border-gray-300 focus:border-indigo-500 focus:ring-indigo-500`;
        }

        const hasError = validationErrors[field] || serverErrors[field as keyof typeof serverErrors];

        if (hasError) {
            return `${baseClass} border-red-300 focus:border-red-500 focus:ring-red-500`;
        }
        if (value) {
            return `${baseClass} border-green-300 focus:border-green-500 focus:ring-green-500`;
        }
        return `${baseClass} border-gray-300 focus:border-indigo-500 focus:ring-indigo-500`;
    };

    const addBuyer = () => {
        setData('buyers', [...data.buyers, { name: '', email: '' }]);
    };

    const removeBuyer = (index: number) => {
        if (data.buyers.length > 1) {
            setData('buyers', data.buyers.filter((_, i) => i !== index));
            // Clean up touched state for removed buyer
            setTouched(prev => {
                const newTouched = { ...prev };
                delete newTouched[`buyers.${index}.name`];
                delete newTouched[`buyers.${index}.email`];
                return newTouched;
            });
        }
    };

    const updateBuyer = (index: number, field: 'name' | 'email', value: string) => {
        const updated = [...data.buyers];
        updated[index][field] = value;
        setData('buyers', updated);
        markTouched(`buyers.${index}.${field}`);
    };

    const addAP = () => {
        setData('accounts_payable', [...data.accounts_payable, { name: '', value: '' }]);
    };

    const removeAP = (index: number) => {
        setData('accounts_payable', data.accounts_payable.filter((_, i) => i !== index));
        setTouched(prev => {
            const newTouched = { ...prev };
            delete newTouched[`accounts_payable.${index}.name`];
            delete newTouched[`accounts_payable.${index}.value`];
            return newTouched;
        });
    };

    const updateAP = (index: number, field: 'name' | 'value', value: string) => {
        const updated = [...data.accounts_payable];
        updated[index][field] = value;
        setData('accounts_payable', updated);
        markTouched(`accounts_payable.${index}.${field}`);
    };

    const addLogistics = () => {
        setData('logistics', [...data.logistics, { name: '', email: '' }]);
    };

    const removeLogistics = (index: number) => {
        setData('logistics', data.logistics.filter((_, i) => i !== index));
        setTouched(prev => {
            const newTouched = { ...prev };
            delete newTouched[`logistics.${index}.name`];
            delete newTouched[`logistics.${index}.email`];
            return newTouched;
        });
    };

    const updateLogistics = (index: number, field: 'name' | 'email', value: string) => {
        const updated = [...data.logistics];
        updated[index][field] = value;
        setData('logistics', updated);
        markTouched(`logistics.${index}.${field}`);
    };

    // Check if form is valid for submission
    const isFormValid = () => {
        // Required fields must be filled
        if (!data.name || data.name.length < 2) return false;
        if (!data.sale_price_list) return false;
        if (!data.customer_payment_term) return false;
        if (!data.shipping_terms_category_id) return false;

        // Shelf life: 30-365 days
        const shelfLife = parseInt(data.shelf_life_requirement);
        if (!data.shelf_life_requirement || isNaN(shelfLife) || shelfLife < 30 || shelfLife > 365) return false;

        // Vendor guide: optional but must be valid URL if provided
        if (data.vendor_guide && !isValidUrl(data.vendor_guide)) return false;

        // At least one valid buyer
        if (data.buyers.length === 0) return false;
        const hasInvalidBuyer = data.buyers.some(b => !b.name || b.name.length < 2 || !isValidEmail(b.email));
        if (hasInvalidBuyer) return false;

        // AP contacts: if any exist, they must be valid
        const hasInvalidAP = data.accounts_payable.some(ap =>
            !ap.name || ap.name.length < 2 || (!isValidEmail(ap.value) && !isValidUrl(ap.value))
        );
        if (hasInvalidAP) return false;

        // Logistics: if any exist, they must be valid
        const hasInvalidLogistics = data.logistics.some(l =>
            !l.name || l.name.length < 2 || !isValidEmail(l.email)
        );
        if (hasInvalidLogistics) return false;

        return true;
    };

    const submit: FormEventHandler = (e) => {
        e.preventDefault();

        // Mark all fields as touched on submit attempt
        const allTouched: TouchedFields = {
            name: true,
            sale_price_list: true,
            customer_payment_term: true,
            shipping_terms_category_id: true,
            shelf_life_requirement: true,
            vendor_guide: true,
        };
        data.buyers.forEach((_, idx) => {
            allTouched[`buyers.${idx}.name`] = true;
            allTouched[`buyers.${idx}.email`] = true;
        });
        data.accounts_payable.forEach((_, idx) => {
            allTouched[`accounts_payable.${idx}.name`] = true;
            allTouched[`accounts_payable.${idx}.value`] = true;
        });
        data.logistics.forEach((_, idx) => {
            allTouched[`logistics.${idx}.name`] = true;
            allTouched[`logistics.${idx}.email`] = true;
        });
        setTouched(allTouched);

        if (isFormValid()) {
            post(route('customers.store'));
        }
    };

    const getError = (field: string) => {
        return validationErrors[field] || serverErrors[field as keyof typeof serverErrors];
    };

    return (
        <AuthenticatedLayout
            header={
                <div className="flex items-center gap-4">
                    <Link
                        href={route('customers.index')}
                        className="text-gray-500 hover:text-gray-700"
                    >
                        <svg className="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M15 19l-7-7 7-7" />
                        </svg>
                    </Link>
                    <h2 className="text-xl font-semibold leading-tight text-gray-800">
                        Add New Customer
                    </h2>
                </div>
            }
        >
            <Head title="Add New Customer" />

            <div className="py-12">
                <div className="mx-auto max-w-3xl sm:px-6 lg:px-8">
                    <form onSubmit={submit}>
                        {/* Company Information */}
                        <div className="overflow-hidden bg-white shadow-sm sm:rounded-lg">
                            <div className="p-6">
                                <h3 className="mb-4 text-lg font-medium text-gray-900">Company Information</h3>

                                <div className="space-y-4">
                                    <div>
                                        <label htmlFor="name" className="block text-sm font-medium text-gray-700">
                                            Company Name <span className="text-red-500">*</span>
                                        </label>
                                        <input
                                            type="text"
                                            id="name"
                                            value={data.name}
                                            onChange={(e) => {
                                                setData('name', e.target.value);
                                                markTouched('name');
                                            }}
                                            onBlur={() => markTouched('name')}
                                            className={getInputClass('name', data.name)}
                                            placeholder="Enter company name"
                                        />
                                        {getError('name') && <p className="mt-1 text-sm text-red-600">{getError('name')}</p>}
                                    </div>
                                </div>
                            </div>
                        </div>

                        {/* Commercial Terms */}
                        <div className="mt-6 overflow-hidden bg-white shadow-sm sm:rounded-lg">
                            <div className="p-6">
                                <h3 className="mb-4 text-lg font-medium text-gray-900">Commercial Terms</h3>

                                <div className="grid grid-cols-1 gap-4 sm:grid-cols-3">
                                    <div>
                                        <label htmlFor="sale_price_list" className="block text-sm font-medium text-gray-700">
                                            Discount Level <span className="text-red-500">*</span>
                                        </label>
                                        <select
                                            id="sale_price_list"
                                            value={data.sale_price_list}
                                            onChange={(e) => {
                                                setData('sale_price_list', e.target.value);
                                                markTouched('sale_price_list');
                                            }}
                                            onBlur={() => markTouched('sale_price_list')}
                                            className={getSelectClass('sale_price_list', data.sale_price_list)}
                                        >
                                            <option value="">Select...</option>
                                            {priceLists.map((pl) => (
                                                <option key={pl.id} value={pl.id}>
                                                    {pl.discount_percent}% Discount
                                                </option>
                                            ))}
                                        </select>
                                        {getError('sale_price_list') && <p className="mt-1 text-sm text-red-600">{getError('sale_price_list')}</p>}
                                    </div>

                                    <div>
                                        <label htmlFor="customer_payment_term" className="block text-sm font-medium text-gray-700">
                                            Payment Terms <span className="text-red-500">*</span>
                                        </label>
                                        <select
                                            id="customer_payment_term"
                                            value={data.customer_payment_term}
                                            onChange={(e) => {
                                                setData('customer_payment_term', e.target.value);
                                                markTouched('customer_payment_term');
                                            }}
                                            onBlur={() => markTouched('customer_payment_term')}
                                            className={getSelectClass('customer_payment_term', data.customer_payment_term)}
                                        >
                                            <option value="">Select...</option>
                                            {paymentTerms.map((pt) => (
                                                <option key={pt.id} value={pt.id}>
                                                    {pt.name}
                                                </option>
                                            ))}
                                        </select>
                                        {getError('customer_payment_term') && <p className="mt-1 text-sm text-red-600">{getError('customer_payment_term')}</p>}
                                    </div>

                                    <div>
                                        <label htmlFor="shipping_terms_category_id" className="block text-sm font-medium text-gray-700">
                                            Shipping Terms <span className="text-red-500">*</span>
                                        </label>
                                        <select
                                            id="shipping_terms_category_id"
                                            value={data.shipping_terms_category_id}
                                            onChange={(e) => {
                                                setData('shipping_terms_category_id', e.target.value);
                                                markTouched('shipping_terms_category_id');
                                            }}
                                            onBlur={() => markTouched('shipping_terms_category_id')}
                                            className={getSelectClass('shipping_terms_category_id', data.shipping_terms_category_id)}
                                        >
                                            <option value="">Select...</option>
                                            {shippingTerms.map((st) => (
                                                <option key={st.id} value={st.id}>
                                                    {st.name}
                                                </option>
                                            ))}
                                        </select>
                                        {getError('shipping_terms_category_id') && <p className="mt-1 text-sm text-red-600">{getError('shipping_terms_category_id')}</p>}
                                    </div>
                                </div>
                            </div>
                        </div>

                        {/* Requirements */}
                        <div className="mt-6 overflow-hidden bg-white shadow-sm sm:rounded-lg">
                            <div className="p-6">
                                <h3 className="mb-4 text-lg font-medium text-gray-900">Requirements</h3>

                                <div className="grid grid-cols-1 gap-4 sm:grid-cols-2">
                                    <div>
                                        <label htmlFor="shelf_life_requirement" className="block text-sm font-medium text-gray-700">
                                            Shelf Life Requirement (days) <span className="text-red-500">*</span>
                                        </label>
                                        <input
                                            type="number"
                                            id="shelf_life_requirement"
                                            value={data.shelf_life_requirement}
                                            onChange={(e) => {
                                                setData('shelf_life_requirement', e.target.value);
                                                markTouched('shelf_life_requirement');
                                            }}
                                            onBlur={() => markTouched('shelf_life_requirement')}
                                            className={getInputClass('shelf_life_requirement', data.shelf_life_requirement)}
                                            placeholder="30-365"
                                            min="30"
                                            max="365"
                                        />
                                        <p className="mt-1 text-xs text-gray-500">Must be between 30 and 365 days</p>
                                        {getError('shelf_life_requirement') && <p className="mt-1 text-sm text-red-600">{getError('shelf_life_requirement')}</p>}
                                    </div>

                                    <div>
                                        <label htmlFor="vendor_guide" className="block text-sm font-medium text-gray-700">
                                            Vendor Guide URL
                                        </label>
                                        <input
                                            type="url"
                                            id="vendor_guide"
                                            value={data.vendor_guide}
                                            onChange={(e) => {
                                                setData('vendor_guide', e.target.value);
                                                markTouched('vendor_guide');
                                            }}
                                            onBlur={() => markTouched('vendor_guide')}
                                            className={getInputClass('vendor_guide', data.vendor_guide, data.vendor_guide === '' || isValidUrl(data.vendor_guide))}
                                            placeholder="https://..."
                                        />
                                        {getError('vendor_guide') && <p className="mt-1 text-sm text-red-600">{getError('vendor_guide')}</p>}
                                    </div>
                                </div>
                            </div>
                        </div>

                        {/* Buyer Contacts */}
                        <div className="mt-6 overflow-hidden bg-white shadow-sm sm:rounded-lg">
                            <div className="p-6">
                                <div className="mb-4 flex items-center justify-between">
                                    <h3 className="text-lg font-medium text-gray-900">
                                        Buyer Contacts <span className="text-red-500">*</span>
                                    </h3>
                                    <button
                                        type="button"
                                        onClick={addBuyer}
                                        className="text-sm text-indigo-600 hover:text-indigo-800"
                                    >
                                        + Add Another
                                    </button>
                                </div>

                                <div className="space-y-4">
                                    {data.buyers.map((buyer, index) => (
                                        <div key={index} className="flex gap-4">
                                            <div className="flex-1">
                                                <input
                                                    type="text"
                                                    value={buyer.name}
                                                    onChange={(e) => updateBuyer(index, 'name', e.target.value)}
                                                    onBlur={() => markTouched(`buyers.${index}.name`)}
                                                    className={getInputClass(`buyers.${index}.name`, buyer.name)}
                                                    placeholder="Contact name"
                                                />
                                                {getError(`buyers.${index}.name`) && (
                                                    <p className="mt-1 text-sm text-red-600">{getError(`buyers.${index}.name`)}</p>
                                                )}
                                            </div>
                                            <div className="flex-1">
                                                <input
                                                    type="email"
                                                    value={buyer.email}
                                                    onChange={(e) => updateBuyer(index, 'email', e.target.value)}
                                                    onBlur={() => markTouched(`buyers.${index}.email`)}
                                                    className={getInputClass(`buyers.${index}.email`, buyer.email, isValidEmail(buyer.email))}
                                                    placeholder="Email address"
                                                />
                                                {getError(`buyers.${index}.email`) && (
                                                    <p className="mt-1 text-sm text-red-600">{getError(`buyers.${index}.email`)}</p>
                                                )}
                                            </div>
                                            {data.buyers.length > 1 && (
                                                <button
                                                    type="button"
                                                    onClick={() => removeBuyer(index)}
                                                    className="text-red-500 hover:text-red-700"
                                                >
                                                    <svg className="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                        <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M6 18L18 6M6 6l12 12" />
                                                    </svg>
                                                </button>
                                            )}
                                        </div>
                                    ))}
                                </div>
                                {serverErrors.buyers && <p className="mt-2 text-sm text-red-600">{serverErrors.buyers}</p>}
                            </div>
                        </div>

                        {/* Accounts Payable Contacts */}
                        <div className="mt-6 overflow-hidden bg-white shadow-sm sm:rounded-lg">
                            <div className="p-6">
                                <div className="mb-4 flex items-center justify-between">
                                    <h3 className="text-lg font-medium text-gray-900">Accounts Payable Contacts</h3>
                                    <button
                                        type="button"
                                        onClick={addAP}
                                        className="text-sm text-indigo-600 hover:text-indigo-800"
                                    >
                                        + Add Contact
                                    </button>
                                </div>

                                {data.accounts_payable.length === 0 ? (
                                    <p className="text-sm text-gray-500">No AP contacts added. Click "Add Contact" to add one.</p>
                                ) : (
                                    <div className="space-y-4">
                                        {data.accounts_payable.map((ap, index) => (
                                            <div key={index} className="flex gap-4">
                                                <div className="flex-1">
                                                    <input
                                                        type="text"
                                                        value={ap.name}
                                                        onChange={(e) => updateAP(index, 'name', e.target.value)}
                                                        onBlur={() => markTouched(`accounts_payable.${index}.name`)}
                                                        className={getInputClass(`accounts_payable.${index}.name`, ap.name)}
                                                        placeholder="Contact name"
                                                    />
                                                    {getError(`accounts_payable.${index}.name`) && (
                                                        <p className="mt-1 text-sm text-red-600">{getError(`accounts_payable.${index}.name`)}</p>
                                                    )}
                                                </div>
                                                <div className="flex-1">
                                                    <input
                                                        type="text"
                                                        value={ap.value}
                                                        onChange={(e) => updateAP(index, 'value', e.target.value)}
                                                        onBlur={() => markTouched(`accounts_payable.${index}.value`)}
                                                        className={getInputClass(`accounts_payable.${index}.value`, ap.value, isValidEmail(ap.value) || isValidUrl(ap.value))}
                                                        placeholder="Email or portal URL"
                                                    />
                                                    {getError(`accounts_payable.${index}.value`) && (
                                                        <p className="mt-1 text-sm text-red-600">{getError(`accounts_payable.${index}.value`)}</p>
                                                    )}
                                                </div>
                                                <button
                                                    type="button"
                                                    onClick={() => removeAP(index)}
                                                    className="text-red-500 hover:text-red-700"
                                                >
                                                    <svg className="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                        <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M6 18L18 6M6 6l12 12" />
                                                    </svg>
                                                </button>
                                            </div>
                                        ))}
                                    </div>
                                )}
                            </div>
                        </div>

                        {/* Logistics Contacts */}
                        <div className="mt-6 overflow-hidden bg-white shadow-sm sm:rounded-lg">
                            <div className="p-6">
                                <div className="mb-4 flex items-center justify-between">
                                    <h3 className="text-lg font-medium text-gray-900">Logistics Contacts</h3>
                                    <button
                                        type="button"
                                        onClick={addLogistics}
                                        className="text-sm text-indigo-600 hover:text-indigo-800"
                                    >
                                        + Add Contact
                                    </button>
                                </div>

                                {data.logistics.length === 0 ? (
                                    <p className="text-sm text-gray-500">No logistics contacts added. Click "Add Contact" to add one.</p>
                                ) : (
                                    <div className="space-y-4">
                                        {data.logistics.map((logistics, index) => (
                                            <div key={index} className="flex gap-4">
                                                <div className="flex-1">
                                                    <input
                                                        type="text"
                                                        value={logistics.name}
                                                        onChange={(e) => updateLogistics(index, 'name', e.target.value)}
                                                        onBlur={() => markTouched(`logistics.${index}.name`)}
                                                        className={getInputClass(`logistics.${index}.name`, logistics.name)}
                                                        placeholder="Contact name"
                                                    />
                                                    {getError(`logistics.${index}.name`) && (
                                                        <p className="mt-1 text-sm text-red-600">{getError(`logistics.${index}.name`)}</p>
                                                    )}
                                                </div>
                                                <div className="flex-1">
                                                    <input
                                                        type="email"
                                                        value={logistics.email}
                                                        onChange={(e) => updateLogistics(index, 'email', e.target.value)}
                                                        onBlur={() => markTouched(`logistics.${index}.email`)}
                                                        className={getInputClass(`logistics.${index}.email`, logistics.email, isValidEmail(logistics.email))}
                                                        placeholder="Email address"
                                                    />
                                                    {getError(`logistics.${index}.email`) && (
                                                        <p className="mt-1 text-sm text-red-600">{getError(`logistics.${index}.email`)}</p>
                                                    )}
                                                </div>
                                                <button
                                                    type="button"
                                                    onClick={() => removeLogistics(index)}
                                                    className="text-red-500 hover:text-red-700"
                                                >
                                                    <svg className="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                        <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M6 18L18 6M6 6l12 12" />
                                                    </svg>
                                                </button>
                                            </div>
                                        ))}
                                    </div>
                                )}
                            </div>
                        </div>

                        {/* Submit */}
                        <div className="mt-6 flex justify-end gap-4">
                            <Link
                                href={route('customers.index')}
                                className="rounded-md border border-gray-300 bg-white px-4 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50"
                            >
                                Cancel
                            </Link>
                            <button
                                type="submit"
                                disabled={processing || !isFormValid()}
                                className="rounded-md bg-indigo-600 px-4 py-2 text-sm font-medium text-white hover:bg-indigo-700 disabled:opacity-50"
                            >
                                {processing ? 'Creating...' : 'Create Customer'}
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </AuthenticatedLayout>
    );
}
