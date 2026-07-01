<?php

namespace App\Policies;

use App\Enums\UserRole;
use App\Models\Lead;
use App\Models\User;

class LeadPolicy
{
    /**
     * Determine whether the user can view any leads.
     *
     * Any authenticated user (manager or rep) can list leads.
     * The controller/query scope will filter results by role.
     */
    public function viewAny(User $user): bool
    {
        return true;
    }

    /**
     * Determine whether the user can view the lead.
     *
     * Managers see all leads. Reps only see leads assigned to them.
     */
    public function view(User $user, Lead $lead): bool
    {
        return $user->role === UserRole::Manager
            || $lead->assigned_to === $user->id;
    }

    /**
     * Determine whether the user can create leads.
     *
     * Any authenticated user can create a lead.
     */
    public function create(User $user): bool
    {
        return true;
    }

    /**
     * Determine whether the user can update the lead.
     *
     * Same visibility rule as view — managers can update any lead,
     * reps can only update leads assigned to them.
     */
    public function update(User $user, Lead $lead): bool
    {
        return $user->role === UserRole::Manager
            || $lead->assigned_to === $user->id;
    }

    /**
     * Determine whether the user can assign a lead to a rep.
     *
     * Only managers can assign/reassign leads.
     */
    public function assign(User $user, Lead $lead): bool
    {
        return $user->role === UserRole::Manager;
    }
}
