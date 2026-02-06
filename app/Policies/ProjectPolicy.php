<?php

namespace App\Policies;

use App\Models\Project;
use App\Models\User;

class ProjectPolicy
{
    // Determine if a user can view a project
    public function view(User $user, Project $project): bool
    {
        // Creator can view
        if ($project->created_by === $user->id) {
            return true;
        }

        // Assigned admin/manager can view
        if ($project->assigned_admin_id === $user->id || $project->assigned_manager_id === $user->id) {
            return true;
        }

        // Allow users from the same company as the project's creator to view
        if ($user->company && $project->creator && $project->creator->company) {
            if ($user->company->id === $project->creator->company->id) {
                return true;
            }
        }

        // Allow users in the same organization (by invitation chain) to view
        // Resolve super admin id for user
        $userSuperAdminId = $this->resolveUserSuperAdmin($user);
        $creatorSuperAdminId = $this->resolveUserSuperAdmin($project->creator);

        if ($userSuperAdminId && $creatorSuperAdminId && $userSuperAdminId === $creatorSuperAdminId) {
            return true;
        }

        // Otherwise deny
        return false;
    }

    // Resolve super admin id for a user via invitation chain
    private function resolveUserSuperAdmin(?User $user)
    {
        if (!$user || !$user->role) return null;

        if ($user->hasRole('super_admin')) return $user->id;

        if ($user->hasRole('admin')) {
            return \App\Models\Invitation::where('email', $user->email)
                ->where('role', 'admin')
                ->value('invited_by');
        }

        if ($user->hasRole('manager')) {
            $inv = \App\Models\Invitation::where('email', $user->email)->where('role', 'manager')->first();
            if (!$inv) return null;
            $inviter = \App\Models\User::find($inv->invited_by);
            if (!$inviter) return null;
            if ($inviter->hasRole('super_admin')) return $inv->invited_by;
            if ($inviter->hasRole('admin')) {
                return \App\Models\Invitation::where('email', $inviter->email)->where('role', 'admin')->value('invited_by');
            }
            return null;
        }

        // other roles
        $inv = \App\Models\Invitation::where('email', $user->email)->first();
        if (!$inv) return null;
        $inviter = \App\Models\User::find($inv->invited_by);
        if (!$inviter) return null;
        if ($inviter->hasRole('super_admin')) return $inv->invited_by;
        if ($inviter->hasRole('admin')) return \App\Models\Invitation::where('email', $inviter->email)->where('role', 'admin')->value('invited_by');

        return null;
    }

    // Determine if a user can create a project
    public function create(User $user): bool
    {
        // Allow based on permission or role
        if ($user->hasPermission('create_project')) {
            return true;
        }

        return $user->hasRole('super_admin') || $user->hasRole('admin');
    }

    // Determine if a user can update a project
    public function update(User $user, Project $project): bool
    {
        // Global permission allows update
        if ($user->hasPermission('edit_project')) {
            return true;
        }

        // Creator or super admin (owner) can update
        if ($project->created_by === $user->id) {
            return true;
        }

        // Admins from same company can update
        if ($user->hasRole('admin') && $user->company && $project->creator && $project->creator->company && $user->company->id === $project->creator->company->id) {
            return true;
        }

        // Managers are NOT allowed to update projects (policy enforced)

        return false;
    }

    // Determine if a user can delete a project (same rules as update)
    public function delete(User $user, Project $project): bool
    {
        // Global permission allows delete
        if ($user->hasPermission('delete_project')) {
            return true;
        }

        return $this->update($user, $project);
    }

}
