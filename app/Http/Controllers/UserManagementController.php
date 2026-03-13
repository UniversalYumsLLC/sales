<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Models\UserInvite;
use Illuminate\Http\Request;
use Inertia\Inertia;

class UserManagementController extends Controller
{
    /**
     * Display the user management page
     */
    public function index()
    {
        $users = User::orderBy('name')
            ->get()
            ->map(function ($user) {
                return [
                    'id' => $user->id,
                    'name' => $user->name,
                    'email' => $user->email,
                    'avatar' => $user->avatar,
                    'role' => $user->role,
                    'created_at' => $user->created_at->toIso8601String(),
                    'last_login' => $user->updated_at->toIso8601String(),
                ];
            });

        $invites = UserInvite::with('inviter')
            ->orderBy('created_at', 'desc')
            ->get()
            ->map(function ($invite) {
                return [
                    'id' => $invite->id,
                    'email' => $invite->email,
                    'role' => $invite->role,
                    'invited_by' => $invite->inviter->name ?? 'Unknown',
                    'created_at' => $invite->created_at->toIso8601String(),
                ];
            });

        return Inertia::render('Admin/Users', [
            'users' => $users,
            'invites' => $invites,
        ]);
    }

    /**
     * Invite a new user by email
     */
    public function invite(Request $request)
    {
        $validated = $request->validate([
            'email' => ['required', 'email', 'max:255'],
            'role' => ['required', 'in:admin,salesperson,user'],
        ]);

        $email = strtolower($validated['email']);

        // Check if user already exists
        if (User::where('email', $email)->exists()) {
            return back()->withErrors(['email' => 'A user with this email already exists.']);
        }

        // Check if invite already exists
        if (UserInvite::where('email', $email)->exists()) {
            return back()->withErrors(['email' => 'An invite for this email already exists.']);
        }

        UserInvite::create([
            'email' => $email,
            'role' => $validated['role'],
            'invited_by' => $request->user()->id,
        ]);

        return back()->with('success', 'Invite sent successfully.');
    }

    /**
     * Cancel a pending invite
     */
    public function cancelInvite(UserInvite $invite)
    {
        $invite->delete();

        return back()->with('success', 'Invite cancelled.');
    }

    /**
     * Update a user's role
     */
    public function updateRole(Request $request, User $user)
    {
        $validated = $request->validate([
            'role' => ['required', 'in:admin,salesperson,user'],
        ]);

        // Prevent users from changing their own role
        if ($user->id === $request->user()->id) {
            return back()->withErrors(['role' => 'You cannot change your own role.']);
        }

        $user->update(['role' => $validated['role']]);

        return back()->with('success', 'User role updated successfully.');
    }

    /**
     * Remove a user
     */
    public function destroy(Request $request, User $user)
    {
        // Prevent users from deleting themselves
        if ($user->id === $request->user()->id) {
            return back()->withErrors(['user' => 'You cannot delete your own account.']);
        }

        $user->delete();

        return back()->with('success', 'User removed successfully.');
    }
}
