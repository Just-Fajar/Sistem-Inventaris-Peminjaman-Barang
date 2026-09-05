<?php

namespace App\Policies;

use App\Models\Borrowing;
use App\Models\User;

class BorrowingPolicy
{
    /**
     * Determine whether the user can view any borrowings.
     */
    public function viewAny(User $user): bool
    {
        return true;
    }

    /**
     * Determine whether the user can view the borrowing.
     */
    public function view(User $user, Borrowing $borrowing): bool
    {
        return $user->isAdmin() || $user->id === $borrowing->user_id;
    }

    /**
     * Determine whether the user can create borrowings.
     */
    public function create(User $user): bool
    {
        return true;
    }

    /**
     * Determine whether the user can update the borrowing.
     */
    public function update(User $user, Borrowing $borrowing): bool
    {
        return $user->isAdmin() || $user->id === $borrowing->user_id;
    }

    /**
     * Determine whether the user can delete the borrowing.
     */
    public function delete(User $user, Borrowing $borrowing): bool
    {
        return $user->isAdmin();
    }

    /**
     * Determine whether the user can approve the borrowing.
     */
    public function approve(User $user, Borrowing $borrowing): bool
    {
        return $user->isAdmin();
    }

    /**
     * Determine whether the user can reject the borrowing.
     */
    public function reject(User $user, Borrowing $borrowing): bool
    {
        return $user->isAdmin();
    }

    /**
     * Determine whether the user can return the borrowed item.
     */
    public function return(User $user, Borrowing $borrowing): bool
    {
        return $user->isAdmin() || $user->id === $borrowing->user_id;
    }

    /**
     * Determine whether the user can extend the borrowing.
     */
    public function extend(User $user, Borrowing $borrowing): bool
    {
        return $user->isAdmin() || $user->id === $borrowing->user_id;
    }
}
