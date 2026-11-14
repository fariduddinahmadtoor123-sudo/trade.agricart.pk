<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\PageInfo;
use Illuminate\Auth\Access\HandlesAuthorization;
use Illuminate\Foundation\Auth\User as AuthUser;

class PageInfoPolicy
{
    use HandlesAuthorization;

    public function viewAny(AuthUser $authUser): bool
    {
        return $authUser->can('ViewAny:PageInfo');
    }

    public function view(AuthUser $authUser, PageInfo $pageInfo): bool
    {
        return $authUser->can('View:PageInfo');
    }

    public function create(AuthUser $authUser): bool
    {
        return $authUser->can('Create:PageInfo');
    }

    public function update(AuthUser $authUser, PageInfo $pageInfo): bool
    {
        return $authUser->can('Update:PageInfo');
    }

    public function delete(AuthUser $authUser, PageInfo $pageInfo): bool
    {
        return $authUser->can('Delete:PageInfo');
    }

    public function deleteAny(AuthUser $authUser): bool
    {
        return $authUser->can('DeleteAny:PageInfo');
    }
}
