<?php
namespace App\Policies;

use App\Models\Subject;
use App\Models\User;

class SubjectPolicy
{
    private function operator(User $user): bool { return $user->status === 'active' && in_array($user->role, ['operational_admin', 'superadmin'], true); }
    public function viewAny(User $user): bool { return $this->operator($user); }
    public function view(User $user, Subject $subject): bool { return $this->operator($user); }
    public function create(User $user): bool { return $this->operator($user); }
    public function update(User $user, Subject $subject): bool { return $this->operator($user); }
    public function delete(User $user, Subject $subject): bool { return $this->operator($user); }
}
