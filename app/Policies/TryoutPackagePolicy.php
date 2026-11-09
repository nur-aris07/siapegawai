<?php
namespace App\Policies;

use App\Models\TryoutPackage;
use App\Models\User;

class TryoutPackagePolicy
{
    private function operator(User $user): bool { return $user->status === 'active' && in_array($user->role, ['operational_admin', 'superadmin'], true); }
    public function viewAny(User $user): bool { return $this->operator($user); }
    public function view(User $user, TryoutPackage $package): bool { return $this->operator($user); }
    public function create(User $user): bool { return $this->operator($user); }
    public function update(User $user, TryoutPackage $package): bool { return $this->operator($user) && $package->status === 'draft'; }
    public function publish(User $user, TryoutPackage $package): bool { return $this->update($user, $package); }
    public function archive(User $user, TryoutPackage $package): bool { return $this->operator($user) && $package->status === 'published'; }
}
