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
        // Admin can view all
        if ($user->role === 'admin') {
            return true;
        }
        
        // Buyer can view their assigned items or unassigned items
        // Check via buyer relationship -> user_id
        if ($item->buyer_id === null) {
            return true;
        }
        
        $buyer = $item->buyer;
        return $buyer && $buyer->user_id === $user->id;
    }

    /**
     * Determine if user can update the item
     */
    public function update(User $user, ProcurementItem $item): bool
    {
        // Admin can update all
        if ($user->role === 'admin') {
            return true;
        }
        
        // Buyer can update unassigned items or their own assigned items
        // Unassigned items (buyer_id is null) can be updated by any buyer
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
        // Only admin can delete
        return $user->role === 'admin';
    }

    /**
     * Determine if user can assign/change buyer of the item
     * Buyers can assign items to any buyer (including reassigning), allowing them to:
     * - Pick up unassigned items for themselves
     * - Reassign items if they made a mistake
     * - Redistribute work to other buyers
     */
    public function assignBuyer(User $user, ProcurementItem $item): bool
    {
        // Admin can always assign
        if ($user->role === 'admin') {
            return true;
        }
        
        // Buyers can assign/reassign items to any buyer
        return $user->role === 'buyer';
    }
}

