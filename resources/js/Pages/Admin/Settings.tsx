import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head, router } from '@inertiajs/react';
import { useState } from 'react';

interface Props {
    settings: {
        ar_test_mode: boolean;
    };
    testModeInfo: {
        allowedDomain: string;
        fulfilEnvironment: string;
    };
}

export default function Settings({ settings, testModeInfo }: Props) {
    const [testMode, setTestMode] = useState(settings.ar_test_mode);
    const [saving, setSaving] = useState(false);
    const [notification, setNotification] = useState<{ type: 'success' | 'error'; message: string } | null>(null);

    const handleTestModeToggle = async () => {
        const newValue = !testMode;
        setSaving(true);
        setNotification(null);

        try {
            const response = await fetch(route('admin.settings.update'), {
                method: 'PUT',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '',
                },
                body: JSON.stringify({ ar_test_mode: newValue }),
            });

            const data = await response.json();

            if (response.ok) {
                setTestMode(data.settings.ar_test_mode);
                setNotification({
                    type: 'success',
                    message: `Test Mode ${data.settings.ar_test_mode ? 'enabled' : 'disabled'} successfully`,
                });
            } else {
                setNotification({
                    type: 'error',
                    message: data.message || 'Failed to update settings',
                });
            }
        } catch (error) {
            setNotification({
                type: 'error',
                message: 'An error occurred while updating settings',
            });
        } finally {
            setSaving(false);
        }
    };

    return (
        <AuthenticatedLayout
            header={
                <h2 className="text-xl font-semibold leading-tight text-gray-800">
                    Admin Settings
                </h2>
            }
        >
            <Head title="Admin Settings" />

            <div className="py-12">
                <div className="mx-auto max-w-7xl space-y-6 sm:px-6 lg:px-8">
                    {/* Notification */}
                    {notification && (
                        <div className={`rounded-md p-4 ${notification.type === 'success' ? 'bg-green-50' : 'bg-red-50'}`}>
                            <div className="flex">
                                <div className="flex-shrink-0">
                                    {notification.type === 'success' ? (
                                        <svg className="h-5 w-5 text-green-400" viewBox="0 0 20 20" fill="currentColor">
                                            <path fillRule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clipRule="evenodd" />
                                        </svg>
                                    ) : (
                                        <svg className="h-5 w-5 text-red-400" viewBox="0 0 20 20" fill="currentColor">
                                            <path fillRule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zM8.707 7.293a1 1 0 00-1.414 1.414L8.586 10l-1.293 1.293a1 1 0 101.414 1.414L10 11.414l1.293 1.293a1 1 0 001.414-1.414L11.414 10l1.293-1.293a1 1 0 00-1.414-1.414L10 8.586 8.707 7.293z" clipRule="evenodd" />
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
                                        className={`inline-flex rounded-md p-1.5 ${notification.type === 'success' ? 'text-green-500 hover:bg-green-100' : 'text-red-500 hover:bg-red-100'}`}
                                    >
                                        <span className="sr-only">Dismiss</span>
                                        <svg className="h-5 w-5" viewBox="0 0 20 20" fill="currentColor">
                                            <path fillRule="evenodd" d="M4.293 4.293a1 1 0 011.414 0L10 8.586l4.293-4.293a1 1 0 111.414 1.414L11.414 10l4.293 4.293a1 1 0 01-1.414 1.414L10 11.414l-4.293 4.293a1 1 0 01-1.414-1.414L8.586 10 4.293 5.707a1 1 0 010-1.414z" clipRule="evenodd" />
                                        </svg>
                                    </button>
                                </div>
                            </div>
                        </div>
                    )}

                    {/* AR Automation Settings */}
                    <div className="overflow-hidden bg-white shadow-sm sm:rounded-lg">
                        <div className="border-b border-gray-200 bg-white px-6 py-4">
                            <h3 className="text-lg font-medium text-gray-900">AR Automation Settings</h3>
                            <p className="mt-1 text-sm text-gray-500">
                                Configure settings for the Accounts Receivable automation system.
                            </p>
                        </div>

                        <div className="p-6">
                            {/* Test Mode Warning Banner */}
                            {testMode && (
                                <div className="mb-6 rounded-md bg-yellow-50 border border-yellow-200 p-4">
                                    <div className="flex">
                                        <div className="flex-shrink-0">
                                            <svg className="h-5 w-5 text-yellow-400" viewBox="0 0 20 20" fill="currentColor">
                                                <path fillRule="evenodd" d="M8.257 3.099c.765-1.36 2.722-1.36 3.486 0l5.58 9.92c.75 1.334-.213 2.98-1.742 2.98H4.42c-1.53 0-2.493-1.646-1.743-2.98l5.58-9.92zM11 13a1 1 0 11-2 0 1 1 0 012 0zm-1-8a1 1 0 00-1 1v3a1 1 0 002 0V6a1 1 0 00-1-1z" clipRule="evenodd" />
                                            </svg>
                                        </div>
                                        <div className="ml-3">
                                            <h3 className="text-sm font-medium text-yellow-800">Test Mode is Active</h3>
                                            <div className="mt-2 text-sm text-yellow-700">
                                                <ul className="list-disc pl-5 space-y-1">
                                                    <li>Emails will only be sent to <strong>{testModeInfo.allowedDomain}</strong> addresses</li>
                                                    <li>Fulfil API is using <strong>{testModeInfo.fulfilEnvironment}</strong> environment</li>
                                                    <li>Customer emails will be blocked or redirected</li>
                                                </ul>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            )}

                            {/* Test Mode Toggle */}
                            <div className="flex items-center justify-between py-4">
                                <div>
                                    <h4 className="text-base font-medium text-gray-900">Enable Test Mode</h4>
                                    <p className="text-sm text-gray-500 mt-1">
                                        When enabled, AR automation will use the Fulfil sandbox environment and
                                        only send emails to @universalyums.com addresses. Use this for testing
                                        without affecting production data or sending emails to customers.
                                    </p>
                                </div>
                                <button
                                    type="button"
                                    onClick={handleTestModeToggle}
                                    disabled={saving}
                                    className={`relative inline-flex h-6 w-11 flex-shrink-0 cursor-pointer rounded-full border-2 border-transparent transition-colors duration-200 ease-in-out focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2 ${
                                        testMode ? 'bg-indigo-600' : 'bg-gray-200'
                                    } ${saving ? 'opacity-50 cursor-not-allowed' : ''}`}
                                    role="switch"
                                    aria-checked={testMode}
                                >
                                    <span className="sr-only">Enable test mode</span>
                                    <span
                                        aria-hidden="true"
                                        className={`pointer-events-none inline-block h-5 w-5 transform rounded-full bg-white shadow ring-0 transition duration-200 ease-in-out ${
                                            testMode ? 'translate-x-5' : 'translate-x-0'
                                        }`}
                                    />
                                </button>
                            </div>
                        </div>
                    </div>

                    {/* Current Environment Info */}
                    <div className="overflow-hidden bg-white shadow-sm sm:rounded-lg">
                        <div className="border-b border-gray-200 bg-white px-6 py-4">
                            <h3 className="text-lg font-medium text-gray-900">Environment Information</h3>
                        </div>
                        <div className="p-6">
                            <dl className="grid grid-cols-1 gap-x-4 gap-y-6 sm:grid-cols-2">
                                <div>
                                    <dt className="text-sm font-medium text-gray-500">Fulfil Environment</dt>
                                    <dd className="mt-1 text-sm text-gray-900">
                                        <span className={`inline-flex items-center rounded-full px-2.5 py-0.5 text-xs font-medium ${
                                            testModeInfo.fulfilEnvironment === 'sandbox'
                                                ? 'bg-yellow-100 text-yellow-800'
                                                : 'bg-green-100 text-green-800'
                                        }`}>
                                            {testModeInfo.fulfilEnvironment}
                                        </span>
                                    </dd>
                                </div>
                                <div>
                                    <dt className="text-sm font-medium text-gray-500">Email Restriction</dt>
                                    <dd className="mt-1 text-sm text-gray-900">
                                        {testMode ? (
                                            <span className="inline-flex items-center rounded-full bg-yellow-100 px-2.5 py-0.5 text-xs font-medium text-yellow-800">
                                                Only {testModeInfo.allowedDomain}
                                            </span>
                                        ) : (
                                            <span className="inline-flex items-center rounded-full bg-green-100 px-2.5 py-0.5 text-xs font-medium text-green-800">
                                                No restriction
                                            </span>
                                        )}
                                    </dd>
                                </div>
                            </dl>
                        </div>
                    </div>
                </div>
            </div>
        </AuthenticatedLayout>
    );
}
