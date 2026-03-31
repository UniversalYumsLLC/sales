import { useHttp } from '@inertiajs/react';
import DOMPurify from 'dompurify';
import { useState, useEffect } from 'react';
import Modal from './Modal';

interface EmailDetail {
    id: number;
    gmail_message_id: string;
    gmail_thread_id: string | null;
    direction: 'inbound' | 'outbound';
    from_email: string;
    from_name: string | null;
    to_emails: string[];
    cc_emails: string[] | null;
    subject: string | null;
    body_html: string | null;
    body_text: string | null;
    email_date: string;
    has_attachments: boolean;
    attachment_info: Array<{ name: string; size?: number; mime_type?: string }> | null;
    contact_name: string | null;
}

interface Props {
    entityType: 'prospect' | 'customer';
    entityId: number;
    emailId: number;
    onClose: () => void;
}

function XMarkIcon({ className = "h-5 w-5" }: { className?: string }) {
    return (
        <svg className={className} fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M6 18L18 6M6 6l12 12" />
        </svg>
    );
}

function InboxArrowDownIcon({ className = "h-4 w-4" }: { className?: string }) {
    return (
        <svg className={className} fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M9 3.75H6.912a2.25 2.25 0 00-2.15 1.588L2.35 13.177a2.25 2.25 0 00-.1.661V18a2.25 2.25 0 002.25 2.25h15A2.25 2.25 0 0021.75 18v-4.162c0-.224-.034-.447-.1-.661L19.24 5.338a2.25 2.25 0 00-2.15-1.588H15M2.25 13.5h6.293a1 1 0 01.707.293l1.414 1.414a1 1 0 00.707.293h1.257a1 1 0 00.707-.293l1.414-1.414a1 1 0 01.707-.293H21.75M12 3v8.25m0 0l-3-3m3 3l3-3" />
        </svg>
    );
}

function PaperAirplaneIcon({ className = "h-4 w-4" }: { className?: string }) {
    return (
        <svg className={className} fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M6 12L3.269 3.126A59.768 59.768 0 0121.485 12 59.77 59.77 0 013.27 20.876L5.999 12zm0 0h7.5" />
        </svg>
    );
}

function PaperClipIcon({ className = "h-4 w-4" }: { className?: string }) {
    return (
        <svg className={className} fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M18.375 12.739l-7.693 7.693a4.5 4.5 0 01-6.364-6.364l10.94-10.94A3 3 0 1119.5 7.372L8.552 18.32m.009-.01l-.01.01m5.699-9.941l-7.81 7.81a1.5 1.5 0 002.112 2.13" />
        </svg>
    );
}

function ArrowTopRightOnSquareIcon({ className = "h-4 w-4" }: { className?: string }) {
    return (
        <svg className={className} fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M13.5 6H5.25A2.25 2.25 0 003 8.25v10.5A2.25 2.25 0 005.25 21h10.5A2.25 2.25 0 0018 18.75V10.5m-10.5 6L21 3m0 0h-5.25M21 3v5.25" />
        </svg>
    );
}

function formatDateTime(dateStr: string): string {
    const date = new Date(dateStr);
    return date.toLocaleString('en-US', {
        weekday: 'short',
        month: 'short',
        day: 'numeric',
        year: 'numeric',
        hour: 'numeric',
        minute: '2-digit',
    });
}

function formatFileSize(bytes?: number): string {
    if (!bytes) return '';
    if (bytes < 1024) return `${bytes} B`;
    if (bytes < 1024 * 1024) return `${(bytes / 1024).toFixed(1)} KB`;
    return `${(bytes / (1024 * 1024)).toFixed(1)} MB`;
}

export default function EmailDetailModal({ entityType, entityId, emailId, onClose }: Props) {
    const [email, setEmail] = useState<EmailDetail | null>(null);
    const [thread, setThread] = useState<EmailDetail[]>([]);
    const [loading, setLoading] = useState(true);
    const [error, setError] = useState<string | null>(null);
    const [activeEmailId, setActiveEmailId] = useState<number>(emailId);

    const emailHttp = useHttp<Record<string, never>, { email: EmailDetail; thread: EmailDetail[] }>({});

    useEffect(() => {
        setLoading(true);
        setError(null);

        const url = entityType === 'prospect'
            ? route('prospects.emails.show', { prospectId: entityId, emailId })
            : route('customers.emails.show', { customerId: entityId, emailId });

        emailHttp.get(url, {
            onSuccess: (response) => {
                setEmail(response.email);
                setThread(response.thread || []);
            },
            onError: () => {
                setError('Failed to load email');
            },
            onFinish: () => {
                setLoading(false);
            },
        });
    }, [entityType, entityId, emailId]);

    const activeEmail = thread.find(e => e.id === activeEmailId) || email;

    const gmailUrl = email?.gmail_message_id
        ? `https://mail.google.com/mail/u/0/#inbox/${email.gmail_message_id}`
        : null;

    return (
        <Modal show={true} onClose={onClose} maxWidth="2xl">
            <div className="max-h-[85vh] flex flex-col">
                {/* Header */}
                <div className="flex items-center justify-between border-b border-gray-200 px-6 py-4">
                    <div className="flex items-center gap-3 min-w-0">
                        {activeEmail && (
                            <div className={`flex-shrink-0 rounded-full p-2 ${
                                activeEmail.direction === 'inbound'
                                    ? 'bg-blue-100 text-blue-600'
                                    : 'bg-green-100 text-green-600'
                            }`}>
                                {activeEmail.direction === 'inbound' ? (
                                    <InboxArrowDownIcon className="h-4 w-4" />
                                ) : (
                                    <PaperAirplaneIcon className="h-4 w-4" />
                                )}
                            </div>
                        )}
                        <h3 className="text-lg font-medium text-gray-900 truncate">
                            {activeEmail?.subject || '(No subject)'}
                        </h3>
                    </div>
                    <div className="flex items-center gap-2 flex-shrink-0">
                        {gmailUrl && (
                            <a
                                href={gmailUrl}
                                target="_blank"
                                rel="noopener noreferrer"
                                className="flex items-center gap-1 rounded-md px-3 py-1.5 text-sm text-gray-600 hover:bg-gray-100"
                                title="Open in Gmail"
                            >
                                <ArrowTopRightOnSquareIcon className="h-4 w-4" />
                                <span className="hidden sm:inline">Open in Gmail</span>
                            </a>
                        )}
                        <button
                            onClick={onClose}
                            className="rounded-md p-1.5 text-gray-400 hover:bg-gray-100 hover:text-gray-500"
                        >
                            <XMarkIcon className="h-5 w-5" />
                        </button>
                    </div>
                </div>

                {/* Content */}
                <div className="flex-1 overflow-y-auto">
                    {loading ? (
                        <div className="flex items-center justify-center py-12">
                            <div className="inline-block h-6 w-6 animate-spin rounded-full border-2 border-gray-300 border-t-indigo-600" />
                        </div>
                    ) : error ? (
                        <div className="py-12 text-center text-red-500">
                            <p>{error}</p>
                        </div>
                    ) : activeEmail ? (
                        <div className="p-6">
                            {/* Thread indicator */}
                            {thread.length > 1 && (
                                <div className="mb-4 flex flex-wrap gap-1">
                                    {thread.map((threadEmail, index) => (
                                        <button
                                            key={threadEmail.id}
                                            onClick={() => setActiveEmailId(threadEmail.id)}
                                            className={`px-2 py-1 text-xs rounded ${
                                                threadEmail.id === activeEmailId
                                                    ? 'bg-indigo-100 text-indigo-700 font-medium'
                                                    : 'bg-gray-100 text-gray-600 hover:bg-gray-200'
                                            }`}
                                        >
                                            {index + 1}. {threadEmail.direction === 'inbound' ? 'Received' : 'Sent'}
                                        </button>
                                    ))}
                                </div>
                            )}

                            {/* Email metadata */}
                            <div className="mb-4 space-y-1 text-sm">
                                <div className="flex">
                                    <span className="w-16 flex-shrink-0 font-medium text-gray-500">From:</span>
                                    <span className="text-gray-900">
                                        {activeEmail.from_name ? (
                                            <>
                                                {activeEmail.from_name}{' '}
                                                <span className="text-gray-500">&lt;{activeEmail.from_email}&gt;</span>
                                            </>
                                        ) : (
                                            activeEmail.from_email
                                        )}
                                    </span>
                                </div>
                                <div className="flex">
                                    <span className="w-16 flex-shrink-0 font-medium text-gray-500">To:</span>
                                    <span className="text-gray-900">
                                        {activeEmail.to_emails?.join(', ') || '-'}
                                    </span>
                                </div>
                                {activeEmail.cc_emails && activeEmail.cc_emails.length > 0 && (
                                    <div className="flex">
                                        <span className="w-16 flex-shrink-0 font-medium text-gray-500">Cc:</span>
                                        <span className="text-gray-900">
                                            {activeEmail.cc_emails.join(', ')}
                                        </span>
                                    </div>
                                )}
                                <div className="flex">
                                    <span className="w-16 flex-shrink-0 font-medium text-gray-500">Date:</span>
                                    <span className="text-gray-900">
                                        {formatDateTime(activeEmail.email_date)}
                                    </span>
                                </div>
                            </div>

                            {/* Attachments */}
                            {activeEmail.has_attachments && activeEmail.attachment_info && activeEmail.attachment_info.length > 0 && (
                                <div className="mb-4 rounded-md bg-gray-50 p-3">
                                    <div className="flex items-center gap-2 text-sm font-medium text-gray-700 mb-2">
                                        <PaperClipIcon className="h-4 w-4" />
                                        Attachments ({activeEmail.attachment_info.length})
                                    </div>
                                    <div className="flex flex-wrap gap-2">
                                        {activeEmail.attachment_info.map((attachment, index) => (
                                            <div
                                                key={index}
                                                className="inline-flex items-center gap-1 rounded bg-white px-2 py-1 text-xs text-gray-600 border border-gray-200"
                                            >
                                                <span className="truncate max-w-[150px]">{attachment.name}</span>
                                                {attachment.size && (
                                                    <span className="text-gray-400">
                                                        ({formatFileSize(attachment.size)})
                                                    </span>
                                                )}
                                            </div>
                                        ))}
                                    </div>
                                </div>
                            )}

                            {/* Email body */}
                            <div className="border-t border-gray-200 pt-4">
                                {activeEmail.body_html ? (
                                    <div
                                        className="prose prose-sm max-w-none email-content"
                                        dangerouslySetInnerHTML={{ __html: DOMPurify.sanitize(activeEmail.body_html, { ADD_ATTR: ['target'] }) }}
                                    />
                                ) : activeEmail.body_text ? (
                                    <pre className="whitespace-pre-wrap text-sm text-gray-700 font-sans">
                                        {activeEmail.body_text}
                                    </pre>
                                ) : (
                                    <p className="text-sm text-gray-500 italic">No content</p>
                                )}
                            </div>
                        </div>
                    ) : null}
                </div>
            </div>

            {/* Add some basic styles for email content */}
            <style>{`
                .email-content img {
                    max-width: 100%;
                    height: auto;
                }
                .email-content a {
                    color: #4f46e5;
                    text-decoration: underline;
                }
                .email-content blockquote {
                    border-left: 3px solid #e5e7eb;
                    padding-left: 1rem;
                    margin-left: 0;
                    color: #6b7280;
                }
                .email-content table {
                    border-collapse: collapse;
                }
                .email-content td, .email-content th {
                    border: 1px solid #e5e7eb;
                    padding: 0.5rem;
                }
            `}</style>
        </Modal>
    );
}
