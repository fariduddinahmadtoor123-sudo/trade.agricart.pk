<?php

namespace Modules\Trading\Policies;

use App\Models\User;
use Modules\Trading\Models\PropAccount;

class PropAccountPolicy
{
    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(User $user, PropAccount $propAccount): bool
    {
        return true;
    }

    public function create(User $user): bool
    {
        return true;
    }

    public function update(User $user, PropAccount $propAccount): bool
    {
        return true;
    }

    public function delete(User $user, PropAccount $propAccount): bool
    {
        return true;
    }

    public function deleteAny(User $user): bool
    {
        return true;
    }
}
