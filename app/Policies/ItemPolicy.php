<?php

namespace App\Policies;

use App\Models\Item;
use App\Models\User;
use Illuminate\Auth\Access\HandlesAuthorization;

class ItemPolicy
{
    use HandlesAuthorization;

    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(User $user, Item $item): bool
    {
        return true;
    }

    public function create(User $user): bool
    {
        return true;
    }

    public function update(User $user, Item $item): bool
    {
        return $this->owns($user, $item);
    }

    public function delete(User $user, Item $item): bool
    {
        return $this->owns($user, $item);
    }

    private function owns(User $user, Item $item): bool
    {
        if ($user->isStaff()) {
            return true;
        }

        if ($item->lostItem) {
            return $item->lostItem->loser_id === $user->loser?->user_id;
        }

        if ($item->foundItem) {
            return $item->foundItem->finder_id === $user->finder?->user_id;
        }

        return false;
    }
}
