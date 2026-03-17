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
        broker: '',  // "true", "false", or "" for unselected
        broker_company_name: '',
        broker_commission: '',
        broker_contacts: [] as BrokerContact[],
        buyers: [{ name: '', email: '' }] as Contact[],
        ap_method: 'inbox' as 'inbox' | 'portal',  // How AP is managed (required)
        ap_portal_url: '',  // URL when ap_method is 'portal'
        accounts_payable: [{ name: '', value: '' }] as APContact[],  // AP contacts (required - at least 1)
        other: [] as OtherContact[],
        // AR Settings
        ar_edi: false,
        ar_consolidated_invoicing: '' as '' | 'single_invoice' | 'consolidated_invoice',
        ar_requires_customer_skus: false,
        ar_invoice_discount: '',
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

        // Broker validation
        if (touched.broker && !data.broker) {
            errors.broker = 'Please select whether this customer uses a broker';
        }

        // If broker is TRUE, validate required broker fields
        if (data.broker === 'true') {
            if (touched.broker_company_name && !data.broker_company_name) {
                errors.broker_company_name = 'Broker company name is required';
            }
            if (touched.broker_commission) {
                if (!data.broker_commission) {
                    errors.broker_commission = 'Broker commission is required';
                } else {
                    const commission = parseFloat(data.broker_commission);
                    if (isNaN(commission) || commission < 0 || commission > 100) {
                        errors.broker_commission = 'Commission must be between 0 and 100';
                    }
                }
            }
            // At least one broker contact required
            if (data.broker_contacts.length === 0 && touched.broker_contacts) {
                errors.broker_contacts = 'At least one broker contact is required';
            }
            // Validate each broker contact
            data.broker_contacts.forEach((contact, idx) => {
                if (touched[`broker_contacts.${idx}.name`]) {
                    if (!contact.name) {
                        errors[`broker_contacts.${idx}.name`] = 'Name is required';
                    } else if (contact.name.length < 2) {
                        errors[`broker_contacts.${idx}.name`] = 'Name must be at least 2 characters';
                    }
                }
                if (touched[`broker_contacts.${idx}.email`]) {
                    if (!contact.email) {
                        errors[`broker_contacts.${idx}.email`] = 'Email is required';
                    } else if (!isValidEmail(contact.email)) {
                        errors[`broker_contacts.${idx}.email`] = 'Must be a valid email';
                    }
                }
            });
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

        // AP validation - always require at least 1 AP contact
        // Validate portal URL if portal method selected
        if (data.ap_method === 'portal') {
            if (touched.ap_portal_url) {
                if (!data.ap_portal_url) {
                    errors.ap_portal_url = 'Portal URL is required';
                } else if (!isValidUrl(data.ap_portal_url)) {
                    errors.ap_portal_url = 'Must be a valid URL (https://...)';
                }
            }
        }

        // AP contacts are always required (at least 1) regardless of method
        if (data.accounts_payable.length === 0 && touched.accounts_payable) {
            errors.accounts_payable = 'At least one AP contact is required';
        }
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
                    errors[`accounts_payable.${idx}.value`] = 'Email is required';
                } else if (!isValidEmail(ap.value)) {
                    errors[`accounts_payable.${idx}.value`] = 'Must be a valid email';
                }
            }
        });

        // Other contacts validation
        data.other.forEach((other, idx) => {
            if (touched[`other.${idx}.name`]) {
                if (!other.name) {
                    errors[`other.${idx}.name`] = 'Name is required';
                } else if (other.name.length < 2) {
                    errors[`other.${idx}.name`] = 'Name must be at least 2 characters';
                }
            }
            if (touched[`other.${idx}.email`]) {
                if (!other.email) {
                    errors[`other.${idx}.email`] = 'Email is required';
                } else if (!isValidEmail(other.email)) {
                    errors[`other.${idx}.email`] = 'Must be a valid email';
                }
            }
        });

        // AR Settings validation
        if (touched.ar_invoice_discount && data.ar_invoice_discount) {
            const discount = parseFloat(data.ar_invoice_discount);
            if (isNaN(discount) || discount < 0 || discount > 100) {
                errors.ar_invoice_discount = 'Must be between 0 and 100';
            }
        }

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
        // Prevent removing the last AP contact (at least 1 required)
        if (data.accounts_payable.length <= 1) return;

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

    const addOther = () => {
        setData('other', [...data.other, { name: '', email: '', function: '' }]);
    };

    const removeOther = (index: number) => {
        setData('other', data.other.filter((_, i) => i !== index));
        setTouched(prev => {
            const newTouched = { ...prev };
            delete newTouched[`other.${index}.name`];
            delete newTouched[`other.${index}.email`];
            delete newTouched[`other.${index}.function`];
            return newTouched;
        });
    };

    const updateOther = (index: number, field: 'name' | 'email' | 'function', value: string) => {
        const updated = [...data.other];
        updated[index] = { ...updated[index], [field]: value };
        setData('other', updated);
        markTouched(`other.${index}.${field}`);
    };

    const addBrokerContact = () => {
        setData('broker_contacts', [...data.broker_contacts, { name: '', email: '' }]);
    };

    const removeBrokerContact = (index: number) => {
        setData('broker_contacts', data.broker_contacts.filter((_, i) => i !== index));
        setTouched(prev => {
            const newTouched = { ...prev };
            delete newTouched[`broker_contacts.${index}.name`];
            delete newTouched[`broker_contacts.${index}.email`];
            return newTouched;
        });
    };

    const updateBrokerContact = (index: number, field: 'name' | 'email', value: string) => {
        const updated = [...data.broker_contacts];
        updated[index][field] = value;
        setData('broker_contacts', updated);
        markTouched(`broker_contacts.${index}.${field}`);
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

        // Broker is required
        if (!data.broker) return false;

        // If broker is TRUE, validate broker fields
        if (data.broker === 'true') {
            if (!data.broker_company_name) return false;
            const commission = parseFloat(data.broker_commission);
            if (!data.broker_commission || isNaN(commission) || commission < 0 || commission > 100) return false;
            // At least one valid broker contact
            if (data.broker_contacts.length === 0) return false;
            const hasInvalidBrokerContact = data.broker_contacts.some(c =>
                !c.name || c.name.length < 2 || !isValidEmail(c.email)
            );
            if (hasInvalidBrokerContact) return false;
        }

        // At least one valid buyer
        if (data.buyers.length === 0) return false;
        const hasInvalidBuyer = data.buyers.some(b => !b.name || b.name.length < 2 || !isValidEmail(b.email));
        if (hasInvalidBuyer) return false;

        // AP validation - at least 1 valid AP contact is always required
        if (data.accounts_payable.length === 0) return false;
        const hasInvalidAP = data.accounts_payable.some(ap =>
            !ap.name || ap.name.length < 2 || !isValidEmail(ap.value)
        );
        if (hasInvalidAP) return false;

        // If portal method, also require valid portal URL
        if (data.ap_method === 'portal') {
            if (!data.ap_portal_url || !isValidUrl(data.ap_portal_url)) return false;
        }

        // Other: if any exist, they must be valid
        const hasInvalidOther = data.other.some(o =>
            !o.name || o.name.length < 2 || !isValidEmail(o.email)
        );
        if (hasInvalidOther) return false;

        // AR invoice discount: if provided, must be valid
        if (data.ar_invoice_discount) {
            const discount = parseFloat(data.ar_invoice_discount);
            if (isNaN(discount) || discount < 0 || discount > 100) return false;
        }

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
            broker: true,
            broker_company_name: true,
            broker_commission: true,
            broker_contacts: true,
            ap_portal_url: data.ap_method === 'portal',
        };
        data.broker_contacts.forEach((_, idx) => {
            allTouched[`broker_contacts.${idx}.name`] = true;
            allTouched[`broker_contacts.${idx}.email`] = true;
        });
        data.buyers.forEach((_, idx) => {
            allTouched[`buyers.${idx}.name`] = true;
            allTouched[`buyers.${idx}.email`] = true;
        });
        // AP contacts are always required
        allTouched.accounts_payable = true;
        data.accounts_payable.forEach((_, idx) => {
            allTouched[`accounts_payable.${idx}.name`] = true;
            allTouched[`accounts_payable.${idx}.value`] = true;
        });
        data.other.forEach((_, idx) => {
            allTouched[`other.${idx}.name`] = true;
            allTouched[`other.${idx}.email`] = true;
        });
        setTouched(allTouched);

        if (isFormValid()) {
            // If portal method, prepend portal URL to accounts_payable
            if (data.ap_method === 'portal' && data.ap_portal_url) {
                const portalEntry = { name: 'AP Portal', value: data.ap_portal_url };
                setData('accounts_payable', [portalEntry, ...data.accounts_payable]);
                // Need to wait for state update, so use setTimeout
                setTimeout(() => post(route('customers.store')), 0);
            } else {
                post(route('customers.store'));
            }
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

                        {/* Broker Section */}
                        <div className="mt-6 overflow-hidden bg-white shadow-sm sm:rounded-lg">
                            <div className="p-6">
                                <h3 className="mb-4 text-lg font-medium text-gray-900">Broker Information</h3>

                                <div className="space-y-4">
                                    <div className="max-w-xs">
                                        <label htmlFor="broker" className="block text-sm font-medium text-gray-700">
                                            Uses Broker <span className="text-red-500">*</span>
                                        </label>
                                        <select
                                            id="broker"
                                            value={data.broker}
                                            onChange={(e) => {
                                                const newValue = e.target.value;
                                                markTouched('broker');
                                                // If switching to TRUE and no broker contacts, add one in a single update
                                                if (newValue === 'true' && data.broker_contacts.length === 0) {
                                                    setData({
                                                        ...data,
                                                        broker: newValue,
                                                        broker_contacts: [{ name: '', email: '' }],
                                                    });
                                                } else {
                                                    setData('broker', newValue);
                                                }
                                            }}
                                            onBlur={() => markTouched('broker')}
                                            className={getSelectClass('broker', data.broker)}
                                        >
                                            <option value="">Select...</option>
                                            <option value="true">TRUE</option>
                                            <option value="false">FALSE</option>
                                        </select>
                                        {getError('broker') && <p className="mt-1 text-sm text-red-600">{getError('broker')}</p>}
                                    </div>

                                    {/* Conditional broker fields - only shown when broker=TRUE */}
                                    {data.broker === 'true' && (
                                        <>
                                            <div className="mt-4 border-t border-gray-200 pt-4">
                                                <div className="grid grid-cols-1 gap-4 sm:grid-cols-2">
                                                    <div>
                                                        <label htmlFor="broker_company_name" className="block text-sm font-medium text-gray-700">
                                                            Broker Company Name <span className="text-red-500">*</span>
                                                        </label>
                                                        <input
                                                            type="text"
                                                            id="broker_company_name"
                                                            value={data.broker_company_name}
                                                            onChange={(e) => {
                                                                setData('broker_company_name', e.target.value);
                                                                markTouched('broker_company_name');
                                                            }}
                                                            onBlur={() => markTouched('broker_company_name')}
                                                            className={getInputClass('broker_company_name', data.broker_company_name)}
                                                            placeholder="e.g., HRG Brokers"
                                                        />
                                                        {getError('broker_company_name') && <p className="mt-1 text-sm text-red-600">{getError('broker_company_name')}</p>}
                                                    </div>

                                                    <div>
                                                        <label htmlFor="broker_commission" className="block text-sm font-medium text-gray-700">
                                                            Commission (%) <span className="text-red-500">*</span>
                                                        </label>
                                                        <input
                                                            type="number"
                                                            id="broker_commission"
                                                            value={data.broker_commission}
                                                            onChange={(e) => {
                                                                setData('broker_commission', e.target.value);
                                                                markTouched('broker_commission');
                                                            }}
                                                            onBlur={() => markTouched('broker_commission')}
                                                            className={getInputClass('broker_commission', data.broker_commission)}
                                                            placeholder="0-100"
                                                            min="0"
                                                            max="100"
                                                            step="0.1"
                                                        />
                                                        {getError('broker_commission') && <p className="mt-1 text-sm text-red-600">{getError('broker_commission')}</p>}
                                                    </div>
                                                </div>
                                            </div>

                                            <div className="mt-4">
                                                <div className="mb-2 flex items-center justify-between">
                                                    <h4 className="text-sm font-medium text-gray-700">
                                                        Broker Contacts <span className="text-red-500">*</span>
                                                    </h4>
                                                    <button
                                                        type="button"
                                                        onClick={addBrokerContact}
                                                        className="text-sm text-indigo-600 hover:text-indigo-800"
                                                    >
                                                        + Add Another
                                                    </button>
                                                </div>
                                                {getError('broker_contacts') && <p className="mb-2 text-sm text-red-600">{getError('broker_contacts')}</p>}
                                                <div className="space-y-4">
                                                    {data.broker_contacts.map((contact, index) => (
                                                        <div key={index} className="flex gap-4">
                                                            <div className="flex-1">
                                                                <input
                                                                    type="text"
                                                                    value={contact.name}
                                                                    onChange={(e) => updateBrokerContact(index, 'name', e.target.value)}
                                                                    onBlur={() => markTouched(`broker_contacts.${index}.name`)}
                                                                    className={getInputClass(`broker_contacts.${index}.name`, contact.name)}
                                                                    placeholder="Contact name"
                                                                />
                                                                {getError(`broker_contacts.${index}.name`) && (
                                                                    <p className="mt-1 text-sm text-red-600">{getError(`broker_contacts.${index}.name`)}</p>
                                                                )}
                                                            </div>
                                                            <div className="flex-1">
                                                                <input
                                                                    type="email"
                                                                    value={contact.email}
                                                                    onChange={(e) => updateBrokerContact(index, 'email', e.target.value)}
                                                                    onBlur={() => markTouched(`broker_contacts.${index}.email`)}
                                                                    className={getInputClass(`broker_contacts.${index}.email`, contact.email, isValidEmail(contact.email))}
                                                                    placeholder="Email address"
                                                                />
                                                                {getError(`broker_contacts.${index}.email`) && (
                                                                    <p className="mt-1 text-sm text-red-600">{getError(`broker_contacts.${index}.email`)}</p>
                                                                )}
                                                            </div>
                                                            {data.broker_contacts.length > 1 && (
                                                                <button
                                                                    type="button"
                                                                    onClick={() => removeBrokerContact(index)}
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
                                            </div>
                                        </>
                                    )}
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

                        {/* Accounts Payable */}
                        <div className="mt-6 overflow-hidden bg-white shadow-sm sm:rounded-lg">
                            <div className="p-6">
                                <h3 className="text-lg font-medium text-gray-900 mb-4">
                                    Accounts Payable <span className="text-red-500">*</span>
                                </h3>

                                {/* AP Method Selection */}
                                <div className="mb-4">
                                    <label className="block text-sm font-medium text-gray-700 mb-2">Does customer use an AP portal?</label>
                                    <div className="flex gap-4">
                                        <label className="flex items-center">
                                            <input
                                                type="radio"
                                                name="ap_method"
                                                value="inbox"
                                                checked={data.ap_method === 'inbox'}
                                                onChange={() => setData('ap_method', 'inbox')}
                                                className="h-4 w-4 text-indigo-600 focus:ring-indigo-500 border-gray-300"
                                            />
                                            <span className="ml-2 text-sm text-gray-700">No - Email only</span>
                                        </label>
                                        <label className="flex items-center">
                                            <input
                                                type="radio"
                                                name="ap_method"
                                                value="portal"
                                                checked={data.ap_method === 'portal'}
                                                onChange={() => setData('ap_method', 'portal')}
                                                className="h-4 w-4 text-indigo-600 focus:ring-indigo-500 border-gray-300"
                                            />
                                            <span className="ml-2 text-sm text-gray-700">Yes - Uses web portal</span>
                                        </label>
                                    </div>
                                </div>

                                {/* Portal URL input (when portal is selected) */}
                                {data.ap_method === 'portal' && (
                                    <div className="mb-4 p-4 bg-gray-50 rounded-md">
                                        <label className="block text-sm font-medium text-gray-700 mb-1">
                                            Portal URL <span className="text-red-500">*</span>
                                        </label>
                                        <input
                                            type="url"
                                            value={data.ap_portal_url}
                                            onChange={(e) => setData('ap_portal_url', e.target.value)}
                                            onBlur={() => markTouched('ap_portal_url')}
                                            className={getInputClass('ap_portal_url', data.ap_portal_url, isValidUrl(data.ap_portal_url))}
                                            placeholder="https://vendor-portal.example.com"
                                        />
                                        {getError('ap_portal_url') && (
                                            <p className="mt-1 text-sm text-red-600">{getError('ap_portal_url')}</p>
                                        )}
                                    </div>
                                )}

                                {/* AP Contacts (always required) */}
                                <div className="mt-4">
                                    <div className="flex items-center justify-between mb-2">
                                        <label className="block text-sm font-medium text-gray-700">
                                            AP Contacts <span className="text-red-500">*</span>
                                        </label>
                                        <button
                                            type="button"
                                            onClick={addAP}
                                            className="text-sm text-indigo-600 hover:text-indigo-800"
                                        >
                                            + Add Contact
                                        </button>
                                    </div>
                                    <p className="text-xs text-gray-500 mb-3">
                                        At least one AP contact is required{data.ap_method === 'portal' ? ' (in addition to the portal URL)' : ''}.
                                    </p>
                                    {getError('accounts_payable') && (
                                        <p className="mb-2 text-sm text-red-600">{getError('accounts_payable')}</p>
                                    )}

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
                                                        type="email"
                                                        value={ap.value}
                                                        onChange={(e) => updateAP(index, 'value', e.target.value)}
                                                        onBlur={() => markTouched(`accounts_payable.${index}.value`)}
                                                        className={getInputClass(`accounts_payable.${index}.value`, ap.value, isValidEmail(ap.value))}
                                                        placeholder="Email address"
                                                    />
                                                    {getError(`accounts_payable.${index}.value`) && (
                                                        <p className="mt-1 text-sm text-red-600">{getError(`accounts_payable.${index}.value`)}</p>
                                                    )}
                                                </div>
                                                {data.accounts_payable.length > 1 && (
                                                    <button
                                                        type="button"
                                                        onClick={() => removeAP(index)}
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
                                </div>
                            </div>
                        </div>

                        {/* Other Contacts */}
                        <div className="mt-6 overflow-hidden bg-white shadow-sm sm:rounded-lg">
                            <div className="p-6">
                                <div className="mb-4 flex items-center justify-between">
                                    <h3 className="text-lg font-medium text-gray-900">Other Contacts</h3>
                                    <button
                                        type="button"
                                        onClick={addOther}
                                        className="text-sm text-indigo-600 hover:text-indigo-800"
                                    >
                                        + Add Contact
                                    </button>
                                </div>

                                {data.other.length === 0 ? (
                                    <p className="text-sm text-gray-500">No other contacts added. Click "Add Contact" to add one.</p>
                                ) : (
                                    <div className="space-y-4">
                                        {data.other.map((other, index) => (
                                            <div key={index} className="flex gap-4">
                                                <div className="flex-1">
                                                    <input
                                                        type="text"
                                                        value={other.name}
                                                        onChange={(e) => updateOther(index, 'name', e.target.value)}
                                                        onBlur={() => markTouched(`other.${index}.name`)}
                                                        className={getInputClass(`other.${index}.name`, other.name)}
                                                        placeholder="Contact name"
                                                    />
                                                    {getError(`other.${index}.name`) && (
                                                        <p className="mt-1 text-sm text-red-600">{getError(`other.${index}.name`)}</p>
                                                    )}
                                                </div>
                                                <div className="w-40">
                                                    <input
                                                        type="text"
                                                        value={other.function || ''}
                                                        onChange={(e) => updateOther(index, 'function', e.target.value)}
                                                        className="mt-1 block w-full rounded-md shadow-sm focus:ring-2 border-gray-300 focus:border-indigo-500 focus:ring-indigo-500"
                                                        placeholder="Function (e.g. Logistics)"
                                                    />
                                                </div>
                                                <div className="flex-1">
                                                    <input
                                                        type="email"
                                                        value={other.email}
                                                        onChange={(e) => updateOther(index, 'email', e.target.value)}
                                                        onBlur={() => markTouched(`other.${index}.email`)}
                                                        className={getInputClass(`other.${index}.email`, other.email, isValidEmail(other.email))}
                                                        placeholder="Email address"
                                                    />
                                                    {getError(`other.${index}.email`) && (
                                                        <p className="mt-1 text-sm text-red-600">{getError(`other.${index}.email`)}</p>
                                                    )}
                                                </div>
                                                <button
                                                    type="button"
                                                    onClick={() => removeOther(index)}
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

                        {/* Invoicing Preferences (AR Settings) */}
                        <div className="mt-6 overflow-hidden bg-white shadow-sm sm:rounded-lg">
                            <div className="p-6">
                                <h3 className="text-lg font-medium text-gray-900 mb-4">Invoicing Preferences</h3>
                                <p className="text-sm text-gray-500 mb-4">Configure AR automation settings for this customer (optional)</p>

                                <div className="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-4">
                                    <div>
                                        <label className="flex items-center">
                                            <input
                                                type="checkbox"
                                                checked={data.ar_edi}
                                                onChange={(e) => setData('ar_edi', e.target.checked)}
                                                className="h-4 w-4 rounded border-gray-300 text-indigo-600 focus:ring-indigo-500"
                                            />
                                            <span className="ml-2 text-sm text-gray-700">EDI Enabled</span>
                                        </label>
                                        <p className="mt-1 text-xs text-gray-500">Customer uses EDI for orders</p>
                                    </div>

                                    <div>
                                        <label className="block text-sm font-medium text-gray-700 mb-1">Invoice Consolidation</label>
                                        <select
                                            value={data.ar_consolidated_invoicing}
                                            onChange={(e) => setData('ar_consolidated_invoicing', e.target.value as '' | 'single_invoice' | 'consolidated_invoice')}
                                            className="block w-full rounded-md border-gray-300 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                                        >
                                            <option value="">One per shipment (default)</option>
                                            <option value="single_invoice">One per shipment</option>
                                            <option value="consolidated_invoice">Consolidate same-day shipments</option>
                                        </select>
                                    </div>

                                    <div>
                                        <label className="flex items-center">
                                            <input
                                                type="checkbox"
                                                checked={data.ar_requires_customer_skus}
                                                onChange={(e) => setData('ar_requires_customer_skus', e.target.checked)}
                                                className="h-4 w-4 rounded border-gray-300 text-indigo-600 focus:ring-indigo-500"
                                            />
                                            <span className="ml-2 text-sm text-gray-700">Requires Customer SKUs</span>
                                        </label>
                                        <p className="mt-1 text-xs text-gray-500">Show customer SKUs on invoice</p>
                                    </div>

                                    <div>
                                        <label className="block text-sm font-medium text-gray-700 mb-1">Invoice Discount (%)</label>
                                        <input
                                            type="number"
                                            value={data.ar_invoice_discount}
                                            onChange={(e) => setData('ar_invoice_discount', e.target.value)}
                                            onBlur={() => markTouched('ar_invoice_discount')}
                                            placeholder="0"
                                            min="0"
                                            max="100"
                                            step="0.01"
                                            className={getInputClass('ar_invoice_discount', data.ar_invoice_discount)}
                                        />
                                        {getError('ar_invoice_discount') && (
                                            <p className="mt-1 text-sm text-red-600">{getError('ar_invoice_discount')}</p>
                                        )}
                                    </div>
                                </div>
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
