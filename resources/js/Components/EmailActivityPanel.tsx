import { useEffect, useState } from 'react';
import EmailDetailModal from './EmailDetailModal';

interface Email {
    id: number;
    gmail_message_id: string;
    gmail_thread_id: string | null;
    direction: 'inbound' | 'outbound';
    from_email: string;
    from_name: string | null;
    to_emails: string[];
    cc_emails: string[] | null;
    subject: string | null;
    snippet: string;
    email_date: string;
    has_attachments: boolean;
    contact_name: string | null;
    distributor_customer_id: number | null;
    distributor_customer_name: string | null;
}

interface Pagination {
    current_page: number;
    last_page: number;
    per_page: number;
    total: number;
}

interface Props {
    entityType: 'prospect' | 'customer';
    entityId: number;
}

// Icon components
function InboxArrowDownIcon({ className = 'h-4 w-4' }: { className?: string }) {
    return (
        <svg className={className} fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path
                strokeLinecap="round"
                strokeLinejoin="round"
                strokeWidth={2}
                d="M9 3.75H6.912a2.25 2.25 0 00-2.15 1.588L2.35 13.177a2.25 2.25 0 00-.1.661V18a2.25 2.25 0 002.25 2.25h15A2.25 2.25 0 0021.75 18v-4.162c0-.224-.034-.447-.1-.661L19.24 5.338a2.25 2.25 0 00-2.15-1.588H15M2.25 13.5h6.293a1 1 0 01.707.293l1.414 1.414a1 1 0 00.707.293h1.257a1 1 0 00.707-.293l1.414-1.414a1 1 0 01.707-.293H21.75M12 3v8.25m0 0l-3-3m3 3l3-3"
            />
        </svg>
    );
}

function PaperAirplaneIcon({ className = 'h-4 w-4' }: { className?: string }) {
    return (
        <svg className={className} fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path
                strokeLinecap="round"
                strokeLinejoin="round"
                strokeWidth={2}
                d="M6 12L3.269 3.126A59.768 59.768 0 0121.485 12 59.77 59.77 0 013.27 20.876L5.999 12zm0 0h7.5"
            />
        </svg>
    );
}

function PaperClipIcon({ className = 'h-4 w-4' }: { className?: string }) {
    return (
        <svg className={className} fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path
                strokeLinecap="round"
                strokeLinejoin="round"
                strokeWidth={2}
                d="M18.375 12.739l-7.693 7.693a4.5 4.5 0 01-6.364-6.364l10.94-10.94A3 3 0 1119.5 7.372L8.552 18.32m.009-.01l-.01.01m5.699-9.941l-7.81 7.81a1.5 1.5 0 002.112 2.13"
            />
        </svg>
    );
}

function ChevronDownIcon({ className = 'h-4 w-4' }: { className?: string }) {
    return (
        <svg className={className} fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M19 9l-7 7-7-7" />
        </svg>
    );
}

function ChevronUpIcon({ className = 'h-4 w-4' }: { className?: string }) {
    return (
        <svg className={className} fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M5 15l7-7 7 7" />
        </svg>
    );
}

function EnvelopeIcon({ className = 'h-5 w-5' }: { className?: string }) {
    return (
        <svg className={className} fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path
                strokeLinecap="round"
                strokeLinejoin="round"
                strokeWidth={2}
                d="M21.75 6.75v10.5a2.25 2.25 0 01-2.25 2.25h-15a2.25 2.25 0 01-2.25-2.25V6.75m19.5 0A2.25 2.25 0 0019.5 4.5h-15a2.25 2.25 0 00-2.25 2.25m19.5 0v.243a2.25 2.25 0 01-1.07 1.916l-7.5 4.615a2.25 2.25 0 01-2.36 0L3.32 8.91a2.25 2.25 0 01-1.07-1.916V6.75"
            />
        </svg>
    );
}

function formatRelativeDate(dateStr: string): string {
    const date = new Date(dateStr);
    const now = new Date();
    const diffMs = now.getTime() - date.getTime();
    const diffDays = Math.floor(diffMs / (1000 * 60 * 60 * 24));

    if (diffDays === 0) {
        // Show time for today
        return date.toLocaleTimeString('en-US', { hour: 'numeric', minute: '2-digit' });
    }
    if (diffDays === 1) return 'Yesterday';
    if (diffDays < 7) return `${diffDays} days ago`;

    // Show date for older emails
    return date.toLocaleDateString('en-US', { month: 'short', day: 'numeric' });
}

export default function EmailActivityPanel({ entityType, entityId }: Props) {
    const [emails, setEmails] = useState<Email[]>([]);
    const [pagination, setPagination] = useState<Pagination | null>(null);
    const [loading, setLoading] = useState(true);
    const [error, setError] = useState<string | null>(null);
    const [expanded, setExpanded] = useState(true);
    const [selectedEmailId, setSelectedEmailId] = useState<number | null>(null);

    const fetchEmails = async (page = 1) => {
        setLoading(true);
        setError(null);

        try {
            const url =
                entityType === 'prospect'
                    ? route('prospects.emails', { id: entityId, per_page: 10, page })
                    : route('customers.emails', { id: entityId, per_page: 10, page });

            const response = await fetch(url, {
                headers: {
                    Accept: 'application/json',
                    'X-Requested-With': 'XMLHttpRequest',
                },
            });

            if (!response.ok) {
                throw new Error('Failed to fetch emails');
            }

            const data = await response.json();

            if (page === 1) {
                setEmails(data.emails);
            } else {
                setEmails((prev) => [...prev, ...data.emails]);
            }
            setPagination(data.pagination);
        } catch (err) {
            setError('Failed to load emails');
            console.error('Error fetching emails:', err);
        } finally {
            setLoading(false);
        }
    };

    useEffect(() => {
        fetchEmails();
    }, [entityType, entityId]);

    const handleLoadMore = () => {
        if (pagination && pagination.current_page < pagination.last_page) {
            fetchEmails(pagination.current_page + 1);
        }
    };

    const handleEmailClick = (emailId: number) => {
        setSelectedEmailId(emailId);
    };

    const closeModal = () => {
        setSelectedEmailId(null);
    };

    return (
        <div className="overflow-hidden bg-white shadow-xs sm:rounded-lg">
            <div className="p-6">
                {/* Header */}
                <div className="mb-4 flex items-center justify-between">
                    <button
                        onClick={() => setExpanded(!expanded)}
                        className="flex items-center gap-2 text-lg font-medium text-gray-900 hover:text-gray-700"
                    >
                        <EnvelopeIcon className="h-5 w-5 text-gray-400" />
                        Recent Activity
                        {pagination && (
                            <span className="text-sm font-normal text-gray-500">
                                ({pagination.total} email{pagination.total !== 1 ? 's' : ''})
                            </span>
                        )}
                        {expanded ? <ChevronUpIcon className="h-4 w-4 text-gray-400" /> : <ChevronDownIcon className="h-4 w-4 text-gray-400" />}
                    </button>
                </div>

                {/* Content */}
                {expanded && (
                    <>
                        {loading && emails.length === 0 ? (
                            <div className="py-8 text-center text-gray-500">
                                <div className="inline-block h-6 w-6 animate-spin rounded-full border-2 border-gray-300 border-t-indigo-600" />
                                <p className="mt-2 text-sm">Loading emails...</p>
                            </div>
                        ) : error ? (
                            <div className="py-8 text-center text-red-500">
                                <p>{error}</p>
                                <button onClick={() => fetchEmails()} className="mt-2 text-sm text-indigo-600 hover:text-indigo-800">
                                    Try again
                                </button>
                            </div>
                        ) : emails.length === 0 ? (
                            <div className="py-8 text-center text-gray-500">
                                <EnvelopeIcon className="mx-auto h-8 w-8 text-gray-300" />
                                <p className="mt-2 text-sm">No emails found</p>
                                <p className="text-xs text-gray-400">Emails will appear here once synced from Gmail</p>
                            </div>
                        ) : (
                            <>
                                {/* Email list */}
                                <div className="divide-y divide-gray-100">
                                    {emails.map((email) => (
                                        <button
                                            key={email.id}
                                            onClick={() => handleEmailClick(email.id)}
                                            className="-mx-2 w-full rounded px-2 py-3 text-left transition-colors hover:bg-gray-50"
                                        >
                                            <div className="flex items-start gap-3">
                                                {/* Direction icon */}
                                                <div
                                                    className={`mt-0.5 shrink-0 rounded-full p-1.5 ${
                                                        email.direction === 'inbound' ? 'bg-blue-100 text-blue-600' : 'bg-green-100 text-green-600'
                                                    }`}
                                                >
                                                    {email.direction === 'inbound' ? (
                                                        <InboxArrowDownIcon className="h-3.5 w-3.5" />
                                                    ) : (
                                                        <PaperAirplaneIcon className="h-3.5 w-3.5" />
                                                    )}
                                                </div>

                                                {/* Content */}
                                                <div className="min-w-0 flex-1">
                                                    <div className="flex items-center gap-2">
                                                        <span className="truncate text-sm font-medium text-gray-900">
                                                            {email.direction === 'inbound'
                                                                ? email.from_name || email.from_email
                                                                : `To: ${email.to_emails?.[0] || 'Unknown'}`}
                                                        </span>
                                                        {email.distributor_customer_name && (
                                                            <span className="inline-flex shrink-0 items-center rounded-full bg-teal-100 px-2 py-0.5 text-xs text-teal-700">
                                                                {email.distributor_customer_name}
                                                            </span>
                                                        )}
                                                        {email.has_attachments && <PaperClipIcon className="h-3.5 w-3.5 shrink-0 text-gray-400" />}
                                                        <span className="ml-auto shrink-0 text-xs text-gray-500">
                                                            {formatRelativeDate(email.email_date)}
                                                        </span>
                                                    </div>
                                                    <p className="truncate text-sm text-gray-700">{email.subject || '(No subject)'}</p>
                                                    <p className="truncate text-xs text-gray-500">{email.snippet || '(No content)'}</p>
                                                </div>
                                            </div>
                                        </button>
                                    ))}
                                </div>

                                {/* Load more button */}
                                {pagination && pagination.current_page < pagination.last_page && (
                                    <div className="mt-4 text-center">
                                        <button
                                            onClick={handleLoadMore}
                                            disabled={loading}
                                            className="text-sm text-indigo-600 hover:text-indigo-800 disabled:opacity-50"
                                        >
                                            {loading ? 'Loading...' : `Load more (${pagination.total - emails.length} remaining)`}
                                        </button>
                                    </div>
                                )}
                            </>
                        )}
                    </>
                )}
            </div>

            {/* Email detail modal */}
            {selectedEmailId && <EmailDetailModal entityType={entityType} entityId={entityId} emailId={selectedEmailId} onClose={closeModal} />}
        </div>
    );
}
