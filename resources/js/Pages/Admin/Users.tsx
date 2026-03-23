import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head, router, usePage, useForm } from '@inertiajs/react';
import { useState, FormEventHandler } from 'react';

interface User {
    id: number;
    name: string;
    email: string;
    avatar: string | null;
    role: 'admin' | 'user' | 'salesperson';
    created_at: string;
    last_login: string;
}

interface Invite {
    id: number;
    email: string;
    role: 'admin' | 'user' | 'salesperson';
    invited_by: string;
    created_at: string;
}

interface Props {
    users: User[];
    invites: Invite[];
}

export default function Users({ users, invites }: Props) {
    const { auth } = usePage().props as { auth: { user: { id: number } } };
    const [confirmingDelete, setConfirmingDelete] = useState<number | null>(null);
    const [showInviteForm, setShowInviteForm] = useState(false);

    const { data, setData, post, processing, errors, reset } = useForm({
        email: '',
        role: 'user' as 'admin' | 'user' | 'salesperson',
    });

    const handleRoleChange = (userId: number, newRole: string) => {
        router.patch(route('admin.users.role', userId), { role: newRole }, {
            preserveScroll: true,
        });
    };

    const handleDelete = (userId: number) => {
        router.delete(route('admin.users.destroy', userId), {
            preserveScroll: true,
            onSuccess: () => setConfirmingDelete(null),
        });
    };

    const handleCancelInvite = (inviteId: number) => {
        router.delete(route('admin.users.invite.cancel', inviteId), {
            preserveScroll: true,
        });
    };

    const handleInvite: FormEventHandler = (e) => {
        e.preventDefault();
        post(route('admin.users.invite'), {
            preserveScroll: true,
            onSuccess: () => {
                reset();
                setShowInviteForm(false);
            },
        });
    };

    const formatDate = (dateStr: string) => {
        return new Date(dateStr).toLocaleDateString();
    };

    return (
        <AuthenticatedLayout
            header={
                <div className="flex items-center justify-between">
                    <h2 className="text-xl font-semibold leading-tight text-gray-800">
                        User Management
                    </h2>
                    <button
                        onClick={() => setShowInviteForm(!showInviteForm)}
                        className="rounded-md bg-indigo-600 px-4 py-2 text-sm text-white hover:bg-indigo-700"
                    >
                        {showInviteForm ? 'Cancel' : 'Invite User'}
                    </button>
                </div>
            }
        >
            <Head title="User Management" />

            <div className="py-12">
                <div className="mx-auto max-w-7xl space-y-6 sm:px-6 lg:px-8">
                    {/* Invite Form */}
                    {showInviteForm && (
                        <div className="overflow-hidden bg-white shadow-sm sm:rounded-lg">
                            <div className="p-6">
                                <h3 className="mb-4 text-lg font-medium text-gray-900">Invite New User</h3>
                                <form onSubmit={handleInvite} className="flex flex-wrap items-end gap-4">
                                    <div className="flex-1">
                                        <label htmlFor="email" className="block text-sm font-medium text-gray-700">
                                            Email Address
                                        </label>
                                        <input
                                            type="email"
                                            id="email"
                                            value={data.email}
                                            onChange={(e) => setData('email', e.target.value)}
                                            className="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                                            placeholder="user@example.com"
                                        />
                                        {errors.email && (
                                            <p className="mt-1 text-sm text-red-600">{errors.email}</p>
                                        )}
                                    </div>
                                    <div>
                                        <label htmlFor="role" className="block text-sm font-medium text-gray-700">
                                            Role
                                        </label>
                                        <select
                                            id="role"
                                            value={data.role}
                                            onChange={(e) => setData('role', e.target.value as 'admin' | 'user' | 'salesperson')}
                                            className="mt-1 block rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                                        >
                                            <option value="user">Accounts Receivable</option>
                                            <option value="salesperson">Salesperson</option>
                                            <option value="admin">Admin</option>
                                        </select>
                                    </div>
                                    <button
                                        type="submit"
                                        disabled={processing}
                                        className="rounded-md bg-indigo-600 px-4 py-2 text-white hover:bg-indigo-700 disabled:opacity-50"
                                    >
                                        {processing ? 'Sending...' : 'Send Invite'}
                                    </button>
                                </form>
                                <p className="mt-3 text-sm text-gray-500">
                                    The invited user will be able to sign in with their Google account using this email address.
                                </p>
                            </div>
                        </div>
                    )}

                    {/* Pending Invites */}
                    {invites.length > 0 && (
                        <div className="overflow-hidden bg-white shadow-sm sm:rounded-lg">
                            <div className="p-6">
                                <h3 className="mb-4 text-lg font-medium text-gray-900">Pending Invites</h3>
                                <div className="overflow-x-auto">
                                    <table className="min-w-full divide-y divide-gray-200">
                                        <thead className="bg-gray-50">
                                            <tr>
                                                <th className="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500">
                                                    Email
                                                </th>
                                                <th className="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500">
                                                    Role
                                                </th>
                                                <th className="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500">
                                                    Invited By
                                                </th>
                                                <th className="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500">
                                                    Date
                                                </th>
                                                <th className="px-6 py-3 text-right text-xs font-medium uppercase tracking-wider text-gray-500">
                                                    Actions
                                                </th>
                                            </tr>
                                        </thead>
                                        <tbody className="divide-y divide-gray-200 bg-white">
                                            {invites.map((invite) => (
                                                <tr key={invite.id}>
                                                    <td className="whitespace-nowrap px-6 py-4 text-sm text-gray-900">
                                                        {invite.email}
                                                    </td>
                                                    <td className="whitespace-nowrap px-6 py-4">
                                                        <span className="inline-flex rounded-full bg-yellow-100 px-2 py-1 text-xs font-semibold text-yellow-800">
                                                            {invite.role}
                                                        </span>
                                                    </td>
                                                    <td className="whitespace-nowrap px-6 py-4 text-sm text-gray-500">
                                                        {invite.invited_by}
                                                    </td>
                                                    <td className="whitespace-nowrap px-6 py-4 text-sm text-gray-500">
                                                        {formatDate(invite.created_at)}
                                                    </td>
                                                    <td className="whitespace-nowrap px-6 py-4 text-right text-sm">
                                                        <button
                                                            onClick={() => handleCancelInvite(invite.id)}
                                                            className="text-red-600 hover:text-red-900"
                                                        >
                                                            Cancel
                                                        </button>
                                                    </td>
                                                </tr>
                                            ))}
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        </div>
                    )}

                    {/* Active Users */}
                    <div className="overflow-hidden bg-white shadow-sm sm:rounded-lg">
                        <div className="p-6">
                            <h3 className="mb-4 text-lg font-medium text-gray-900">Active Users</h3>
                            <div className="overflow-x-auto">
                                <table className="min-w-full divide-y divide-gray-200">
                                    <thead className="bg-gray-50">
                                        <tr>
                                            <th className="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500">
                                                User
                                            </th>
                                            <th className="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500">
                                                Role
                                            </th>
                                            <th className="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500">
                                                Joined
                                            </th>
                                            <th className="px-6 py-3 text-right text-xs font-medium uppercase tracking-wider text-gray-500">
                                                Actions
                                            </th>
                                        </tr>
                                    </thead>
                                    <tbody className="divide-y divide-gray-200 bg-white">
                                        {users.map((user) => (
                                            <tr key={user.id}>
                                                <td className="whitespace-nowrap px-6 py-4">
                                                    <div className="flex items-center">
                                                        {user.avatar ? (
                                                            <img
                                                                src={user.avatar}
                                                                alt=""
                                                                className="h-8 w-8 rounded-full"
                                                            />
                                                        ) : (
                                                            <div className="flex h-8 w-8 items-center justify-center rounded-full bg-gray-300">
                                                                <svg
                                                                    className="h-4 w-4 text-gray-600"
                                                                    xmlns="http://www.w3.org/2000/svg"
                                                                    viewBox="0 0 20 20"
                                                                    fill="currentColor"
                                                                >
                                                                    <path
                                                                        fillRule="evenodd"
                                                                        d="M10 9a3 3 0 100-6 3 3 0 000 6zm-7 9a7 7 0 1114 0H3z"
                                                                        clipRule="evenodd"
                                                                    />
                                                                </svg>
                                                            </div>
                                                        )}
                                                        <div className="ml-3">
                                                            <div className="text-sm font-medium text-gray-900">
                                                                {user.name}
                                                                {user.id === auth.user.id && (
                                                                    <span className="ml-2 text-xs text-gray-500">(you)</span>
                                                                )}
                                                            </div>
                                                            <div className="text-sm text-gray-500">{user.email}</div>
                                                        </div>
                                                    </div>
                                                </td>
                                                <td className="whitespace-nowrap px-6 py-4">
                                                    {user.id === auth.user.id ? (
                                                        <span className="inline-flex rounded-full bg-indigo-100 px-2 py-1 text-xs font-semibold text-indigo-800">
                                                            {user.role}
                                                        </span>
                                                    ) : (
                                                        <select
                                                            value={user.role}
                                                            onChange={(e) => handleRoleChange(user.id, e.target.value)}
                                                            className="rounded-md border-gray-300 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                                                        >
                                                            <option value="user">Accounts Receivable</option>
                                                            <option value="salesperson">Salesperson</option>
                                                            <option value="admin">Admin</option>
                                                        </select>
                                                    )}
                                                </td>
                                                <td className="whitespace-nowrap px-6 py-4 text-sm text-gray-500">
                                                    {formatDate(user.created_at)}
                                                </td>
                                                <td className="whitespace-nowrap px-6 py-4 text-right text-sm">
                                                    {user.id !== auth.user.id && (
                                                        <>
                                                            {confirmingDelete === user.id ? (
                                                                <div className="flex items-center justify-end gap-2">
                                                                    <span className="text-xs text-gray-500">Confirm?</span>
                                                                    <button
                                                                        onClick={() => handleDelete(user.id)}
                                                                        className="text-red-600 hover:text-red-900"
                                                                    >
                                                                        Yes
                                                                    </button>
                                                                    <button
                                                                        onClick={() => setConfirmingDelete(null)}
                                                                        className="text-gray-600 hover:text-gray-900"
                                                                    >
                                                                        No
                                                                    </button>
                                                                </div>
                                                            ) : (
                                                                <button
                                                                    onClick={() => setConfirmingDelete(user.id)}
                                                                    className="text-red-600 hover:text-red-900"
                                                                >
                                                                    Remove
                                                                </button>
                                                            )}
                                                        </>
                                                    )}
                                                </td>
                                            </tr>
                                        ))}
                                    </tbody>
                                </table>
                            </div>

                            {users.length === 0 && (
                                <div className="py-8 text-center text-gray-500">
                                    No users found
                                </div>
                            )}
                        </div>
                    </div>
                </div>
            </div>
        </AuthenticatedLayout>
    );
}
