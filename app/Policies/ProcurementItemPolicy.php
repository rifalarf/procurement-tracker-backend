<?php

namespace App\Policies;

use App\Models\ProcurementItem;
use App\Models\User;

class ProcurementItemPolicy
{
    /**
     * Determine if user can view the item
     */
    public function view(User $user, ProcurementItem $item): bool
    {
        // Admin, AVP, Staff, and Buyer can view all items
        // Buyers can view items from any department (cross-department visibility)
        // This enables view-only mode for items they cannot edit
        return in_array($user->role, ['admin', 'avp', 'staff', 'buyer']);
    }

    /**
     * Determine if user can create items
     */
    public function create(User $user): bool
    {
        // Admin and AVP can create items
        return in_array($user->role, ['admin', 'avp']);
    }

    /**
     * Determine if user can update the item
     * Note: Field-level restrictions for AVP are handled in the controller
     */
    public function update(User $user, ProcurementItem $item): bool
    {
        // Admin, AVP, and Staff can update items
        if (in_array($user->role, ['admin', 'avp', 'staff'])) {
            return true;
        }

        // Buyer can update unassigned items or their own assigned items
        if ($item->buyer_id === null) {
            return $user->role === 'buyer';
        }

        // Check via buyer relationship -> user_id for assigned items
        $buyer = $item->buyer;
        return $buyer && $buyer->user_id === $user->id;
    }

    /**
     * Determine if user can delete the item
     */
    public function delete(User $user, ProcurementItem $item): bool
    {
        // Admin and AVP can delete
        return in_array($user->role, ['admin', 'avp']);
    }

    /**
     * Determine if user can rebid the item
     * More restrictive than general update — staff cannot rebid
     */
    public function rebid(User $user, ProcurementItem $item): bool
    {
        if (in_array($user->role, ['admin', 'avp'])) {
            return true;
        }

        // Buyer can rebid unassigned items or their own assigned items
        if ($user->role === 'buyer') {
            if ($item->buyer_id === null) {
                return true;
            }
            $buyer = $item->buyer;
            return $buyer && $buyer->user_id === $user->id;
        }

        return false;
    }

    /**
     * Determine if user can cancel the item
     * More restrictive than general update — staff cannot cancel
     */
    public function cancel(User $user, ProcurementItem $item): bool
    {
        if (in_array($user->role, ['admin', 'avp'])) {
            return true;
        }

        // Buyer can cancel unassigned items or their own assigned items
        if ($user->role === 'buyer') {
            if ($item->buyer_id === null) {
                return true;
            }
            $buyer = $item->buyer;
            return $buyer && $buyer->user_id === $user->id;
        }

        return false;
    }

    /**
     * Determine if user can assign/change buyer of the item
     */
    public function assignBuyer(User $user, ProcurementItem $item): bool
    {
        // Admin, AVP, Staff, and Buyer can assign
        return in_array($user->role, ['admin', 'avp', 'staff', 'buyer']);
    }
}
