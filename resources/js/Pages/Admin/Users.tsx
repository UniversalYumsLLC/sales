import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head, router, useForm, usePage } from '@inertiajs/react';
import { FormEventHandler, useState } from 'react';

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
        router.patch(
            route('admin.users.role', userId),
            { role: newRole },
            {
                preserveScroll: true,
            },
        );
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
                    <h2 className="text-xl font-semibold leading-tight text-gray-800">User Management</h2>
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
                <div className="max-w-7xl space-y-6 sm:px-6 lg:px-8 mx-auto">
                    {/* Invite Form */}
                    {showInviteForm && (
                        <div className="bg-white shadow-sm sm:rounded-lg overflow-hidden">
                            <div className="p-6">
                                <h3 className="mb-4 text-lg font-medium text-gray-900">Invite New User</h3>
                                <form onSubmit={handleInvite} className="gap-4 flex flex-wrap items-end">
                                    <div className="flex-1">
                                        <label htmlFor="email" className="text-sm font-medium text-gray-700 block">
                                            Email Address
                                        </label>
                                        <input
                                            type="email"
                                            id="email"
                                            value={data.email}
                                            onChange={(e) => setData('email', e.target.value)}
                                            className="mt-1 rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 block w-full"
                                            placeholder="user@example.com"
                                        />
                                        {errors.email && <p className="mt-1 text-sm text-red-600">{errors.email}</p>}
                                    </div>
                                    <div>
                                        <label htmlFor="role" className="text-sm font-medium text-gray-700 block">
                                            Role
                                        </label>
                                        <select
                                            id="role"
                                            value={data.role}
                                            onChange={(e) => setData('role', e.target.value as 'admin' | 'user' | 'salesperson')}
                                            className="mt-1 rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 block"
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
                        <div className="bg-white shadow-sm sm:rounded-lg overflow-hidden">
                            <div className="p-6">
                                <h3 className="mb-4 text-lg font-medium text-gray-900">Pending Invites</h3>
                                <div className="overflow-x-auto">
                                    <table className="divide-gray-200 min-w-full divide-y">
                                        <thead className="bg-gray-50">
                                            <tr>
                                                <th className="px-6 py-3 text-xs font-medium tracking-wider text-gray-500 text-left uppercase">
                                                    Email
                                                </th>
                                                <th className="px-6 py-3 text-xs font-medium tracking-wider text-gray-500 text-left uppercase">
                                                    Role
                                                </th>
                                                <th className="px-6 py-3 text-xs font-medium tracking-wider text-gray-500 text-left uppercase">
                                                    Invited By
                                                </th>
                                                <th className="px-6 py-3 text-xs font-medium tracking-wider text-gray-500 text-left uppercase">
                                                    Date
                                                </th>
                                                <th className="px-6 py-3 text-xs font-medium tracking-wider text-gray-500 text-right uppercase">
                                                    Actions
                                                </th>
                                            </tr>
                                        </thead>
                                        <tbody className="divide-gray-200 bg-white divide-y">
                                            {invites.map((invite) => (
                                                <tr key={invite.id}>
                                                    <td className="px-6 py-4 text-sm text-gray-900 whitespace-nowrap">{invite.email}</td>
                                                    <td className="px-6 py-4 whitespace-nowrap">
                                                        <span className="bg-yellow-100 px-2 py-1 text-xs font-semibold text-yellow-800 inline-flex rounded-full">
                                                            {invite.role}
                                                        </span>
                                                    </td>
                                                    <td className="px-6 py-4 text-sm text-gray-500 whitespace-nowrap">{invite.invited_by}</td>
                                                    <td className="px-6 py-4 text-sm text-gray-500 whitespace-nowrap">
                                                        {formatDate(invite.created_at)}
                                                    </td>
                                                    <td className="px-6 py-4 text-sm text-right whitespace-nowrap">
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
                    <div className="bg-white shadow-sm sm:rounded-lg overflow-hidden">
                        <div className="p-6">
                            <h3 className="mb-4 text-lg font-medium text-gray-900">Active Users</h3>
                            <div className="overflow-x-auto">
                                <table className="divide-gray-200 min-w-full divide-y">
                                    <thead className="bg-gray-50">
                                        <tr>
                                            <th className="px-6 py-3 text-xs font-medium tracking-wider text-gray-500 text-left uppercase">User</th>
                                            <th className="px-6 py-3 text-xs font-medium tracking-wider text-gray-500 text-left uppercase">Role</th>
                                            <th className="px-6 py-3 text-xs font-medium tracking-wider text-gray-500 text-left uppercase">Joined</th>
                                            <th className="px-6 py-3 text-xs font-medium tracking-wider text-gray-500 text-right uppercase">
                                                Actions
                                            </th>
                                        </tr>
                                    </thead>
                                    <tbody className="divide-gray-200 bg-white divide-y">
                                        {users.map((user) => (
                                            <tr key={user.id}>
                                                <td className="px-6 py-4 whitespace-nowrap">
                                                    <div className="flex items-center">
                                                        {user.avatar ? (
                                                            <img src={user.avatar} alt="" className="h-8 w-8 rounded-full" />
                                                        ) : (
                                                            <div className="h-8 w-8 bg-gray-300 flex items-center justify-center rounded-full">
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
                                                <td className="px-6 py-4 whitespace-nowrap">
                                                    {user.id === auth.user.id ? (
                                                        <span className="bg-indigo-100 px-2 py-1 text-xs font-semibold text-indigo-800 inline-flex rounded-full">
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
                                                <td className="px-6 py-4 text-sm text-gray-500 whitespace-nowrap">{formatDate(user.created_at)}</td>
                                                <td className="px-6 py-4 text-sm text-right whitespace-nowrap">
                                                    {user.id !== auth.user.id && (
                                                        <>
                                                            {confirmingDelete === user.id ? (
                                                                <div className="gap-2 flex items-center justify-end">
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

                            {users.length === 0 && <div className="py-8 text-gray-500 text-center">No users found</div>}
                        </div>
                    </div>
                </div>
            </div>
        </AuthenticatedLayout>
    );
}
