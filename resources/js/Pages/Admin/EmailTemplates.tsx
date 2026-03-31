import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head } from '@inertiajs/react';
import axios from 'axios';
import DOMPurify from 'dompurify';
import { useState, useRef, useEffect } from 'react';

interface Template {
    key: string;
    name: string;
    description: string;
    recipient: string;
    placeholders: string[];
    subject: string;
    body: string;
}

interface Props {
    templates: Template[];
}

export default function EmailTemplates({ templates }: Props) {
    const [selectedKey, setSelectedKey] = useState<string>(templates[0]?.key || '');
    const [subject, setSubject] = useState('');
    const [body, setBody] = useState('');
    const [saving, setSaving] = useState(false);
    const [notification, setNotification] = useState<{ type: 'success' | 'error'; message: string } | null>(null);
    const [showPreview, setShowPreview] = useState(false);
    const editorRef = useRef<HTMLDivElement>(null);

    const selectedTemplate = templates.find(t => t.key === selectedKey);

    // Load template content when selection changes
    useEffect(() => {
        if (selectedTemplate) {
            setSubject(selectedTemplate.subject);
            setBody(selectedTemplate.body);
        }
    }, [selectedKey]);

    // Update contenteditable when body changes programmatically
    useEffect(() => {
        if (editorRef.current && editorRef.current.innerHTML !== body) {
            editorRef.current.innerHTML = DOMPurify.sanitize(body);
        }
    }, [body]);

    const handleEditorInput = () => {
        if (editorRef.current) {
            setBody(editorRef.current.innerHTML);
        }
    };

    const handleSave = async () => {
        if (!selectedKey) return;

        setSaving(true);
        setNotification(null);

        try {
            await axios.put(route('admin.email-templates.update', { key: selectedKey }), { subject, body });
            setNotification({ type: 'success', message: 'Template saved successfully' });
        } catch (error) {
            if (axios.isAxiosError(error)) {
                const data = error.response?.data;
                if (data?.errors) {
                    const firstError = Object.values(data.errors).flat()[0] as string;
                    setNotification({ type: 'error', message: firstError || 'Validation failed' });
                } else {
                    setNotification({ type: 'error', message: data?.message || 'Failed to save template' });
                }
            } else {
                setNotification({ type: 'error', message: 'An error occurred while saving' });
            }
        } finally {
            setSaving(false);
        }
    };

    const insertPlaceholder = (placeholder: string) => {
        const tag = `{{${placeholder}}}`;
        if (editorRef.current) {
            // Insert at cursor position or at end
            const selection = window.getSelection();
            if (selection && selection.rangeCount > 0) {
                const range = selection.getRangeAt(0);
                if (editorRef.current.contains(range.commonAncestorContainer)) {
                    range.deleteContents();
                    range.insertNode(document.createTextNode(tag));
                    range.collapse(false);
                    selection.removeAllRanges();
                    selection.addRange(range);
                    handleEditorInput();
                    return;
                }
            }
            // Fallback: append to end
            editorRef.current.innerHTML += tag;
            handleEditorInput();
        }
    };

    const execCommand = (command: string, value: string | undefined = undefined) => {
        document.execCommand(command, false, value);
        editorRef.current?.focus();
        handleEditorInput();
    };

    const getRecipientLabel = (recipient: string) => {
        switch (recipient) {
            case 'internal':
                return 'Internal (AR Team)';
            case 'customer_ap':
                return 'Customer AP Contacts';
            default:
                return recipient;
        }
    };

    return (
        <AuthenticatedLayout
            header={
                <h2 className="text-xl font-semibold leading-tight text-gray-800">
                    Email Templates
                </h2>
            }
        >
            <Head title="Email Templates" />

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

                    <div className="overflow-hidden bg-white shadow-sm sm:rounded-lg">
                        <div className="p-6">
                            {/* Template Selector */}
                            <div className="mb-6">
                                <label htmlFor="template-select" className="block text-sm font-medium text-gray-700 mb-2">
                                    Select Template
                                </label>
                                <select
                                    id="template-select"
                                    value={selectedKey}
                                    onChange={(e) => setSelectedKey(e.target.value)}
                                    className="block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                                >
                                    {templates.map((template) => (
                                        <option key={template.key} value={template.key}>
                                            {template.name}
                                        </option>
                                    ))}
                                </select>
                            </div>

                            {selectedTemplate && (
                                <>
                                    {/* Template Info */}
                                    <div className="mb-6 rounded-md bg-gray-50 p-4">
                                        <p className="text-sm text-gray-600">{selectedTemplate.description}</p>
                                        <p className="text-sm text-gray-500 mt-1">
                                            <strong>Recipient:</strong> {getRecipientLabel(selectedTemplate.recipient)}
                                        </p>
                                    </div>

                                    {/* Subject */}
                                    <div className="mb-6">
                                        <label htmlFor="subject" className="block text-sm font-medium text-gray-700 mb-2">
                                            Subject
                                        </label>
                                        <input
                                            type="text"
                                            id="subject"
                                            value={subject}
                                            onChange={(e) => setSubject(e.target.value)}
                                            className="block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                                            placeholder="Email subject line..."
                                        />
                                    </div>

                                    {/* Body Editor */}
                                    <div className="mb-6">
                                        <div className="flex items-center justify-between mb-2">
                                            <label className="block text-sm font-medium text-gray-700">
                                                Body
                                            </label>
                                            <button
                                                type="button"
                                                onClick={() => setShowPreview(!showPreview)}
                                                className="text-sm text-indigo-600 hover:text-indigo-800"
                                            >
                                                {showPreview ? 'Hide Preview' : 'Show Preview'}
                                            </button>
                                        </div>

                                        {/* Formatting Toolbar */}
                                        <div className="flex flex-wrap gap-1 mb-2 p-2 bg-gray-100 rounded-t-md border border-b-0 border-gray-300">
                                            <button
                                                type="button"
                                                onClick={() => execCommand('bold')}
                                                className="px-2 py-1 text-sm font-bold bg-white border border-gray-300 rounded hover:bg-gray-50"
                                                title="Bold"
                                            >
                                                B
                                            </button>
                                            <button
                                                type="button"
                                                onClick={() => execCommand('italic')}
                                                className="px-2 py-1 text-sm italic bg-white border border-gray-300 rounded hover:bg-gray-50"
                                                title="Italic"
                                            >
                                                I
                                            </button>
                                            <button
                                                type="button"
                                                onClick={() => execCommand('underline')}
                                                className="px-2 py-1 text-sm underline bg-white border border-gray-300 rounded hover:bg-gray-50"
                                                title="Underline"
                                            >
                                                U
                                            </button>
                                            <span className="border-l border-gray-300 mx-1"></span>
                                            <button
                                                type="button"
                                                onClick={() => execCommand('insertUnorderedList')}
                                                className="px-2 py-1 text-sm bg-white border border-gray-300 rounded hover:bg-gray-50"
                                                title="Bullet List"
                                            >
                                                <svg className="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                    <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M4 6h16M4 12h16M4 18h16" />
                                                </svg>
                                            </button>
                                            <button
                                                type="button"
                                                onClick={() => {
                                                    const url = prompt('Enter link URL:');
                                                    if (url) execCommand('createLink', url);
                                                }}
                                                className="px-2 py-1 text-sm bg-white border border-gray-300 rounded hover:bg-gray-50"
                                                title="Insert Link"
                                            >
                                                <svg className="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                    <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M13.828 10.172a4 4 0 00-5.656 0l-4 4a4 4 0 105.656 5.656l1.102-1.101m-.758-4.899a4 4 0 005.656 0l4-4a4 4 0 00-5.656-5.656l-1.1 1.1" />
                                                </svg>
                                            </button>
                                        </div>

                                        {/* Content Editable Editor */}
                                        <div
                                            ref={editorRef}
                                            contentEditable
                                            onInput={handleEditorInput}
                                            className="block w-full min-h-[200px] p-3 rounded-b-md border border-gray-300 focus:border-indigo-500 focus:ring-1 focus:ring-indigo-500 focus:outline-none prose prose-sm max-w-none"
                                            style={{ backgroundColor: 'white' }}
                                        />
                                    </div>

                                    {/* Preview Panel */}
                                    {showPreview && (
                                        <div className="mb-6">
                                            <label className="block text-sm font-medium text-gray-700 mb-2">
                                                Preview
                                            </label>
                                            <div className="border border-gray-300 rounded-md p-4 bg-gray-50">
                                                <div className="mb-2 pb-2 border-b border-gray-200">
                                                    <strong>Subject:</strong> {subject}
                                                </div>
                                                <div
                                                    className="prose prose-sm max-w-none"
                                                    dangerouslySetInnerHTML={{ __html: DOMPurify.sanitize(body, { ADD_ATTR: ['target'] }) }}
                                                />
                                            </div>
                                        </div>
                                    )}

                                    {/* Available Placeholders */}
                                    <div className="mb-6">
                                        <label className="block text-sm font-medium text-gray-700 mb-2">
                                            Available Placeholders
                                        </label>
                                        <div className="flex flex-wrap gap-2">
                                            {selectedTemplate.placeholders.map((placeholder) => (
                                                <button
                                                    key={placeholder}
                                                    type="button"
                                                    onClick={() => insertPlaceholder(placeholder)}
                                                    className="inline-flex items-center px-2.5 py-1.5 text-xs font-medium text-indigo-700 bg-indigo-100 rounded hover:bg-indigo-200"
                                                >
                                                    {`{{${placeholder}}}`}
                                                </button>
                                            ))}
                                        </div>
                                        <p className="mt-2 text-xs text-gray-500">
                                            Click a placeholder to insert it at the cursor position in the body.
                                        </p>
                                    </div>

                                    {/* Save Button */}
                                    <div className="flex justify-end">
                                        <button
                                            type="button"
                                            onClick={handleSave}
                                            disabled={saving}
                                            className={`inline-flex items-center px-4 py-2 text-sm font-medium text-white bg-indigo-600 rounded-md hover:bg-indigo-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500 ${
                                                saving ? 'opacity-50 cursor-not-allowed' : ''
                                            }`}
                                        >
                                            {saving ? (
                                                <>
                                                    <svg className="animate-spin -ml-1 mr-2 h-4 w-4 text-white" fill="none" viewBox="0 0 24 24">
                                                        <circle className="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" strokeWidth="4"></circle>
                                                        <path className="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                                                    </svg>
                                                    Saving...
                                                </>
                                            ) : (
                                                'Save Template'
                                            )}
                                        </button>
                                    </div>
                                </>
                            )}
                        </div>
                    </div>
                </div>
            </div>
        </AuthenticatedLayout>
    );
}
