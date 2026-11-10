<?php
namespace App\Policies;

use App\Models\ClassPractice;
use App\Models\User;

class ClassPracticePolicy
{
    private function admin(User $user): bool { return $user->status==='active' && in_array($user->role,['operational_admin','superadmin'],true); }
    public function viewAny(User $user): bool { return $this->admin($user); }
    public function view(User $user, ClassPractice $practice): bool { return $this->admin($user); }
    public function create(User $user): bool { return $this->admin($user); }
    public function update(User $user, ClassPractice $practice): bool { return $this->admin($user) && $practice->status==='draft'; }
    public function publish(User $user, ClassPractice $practice): bool { return $this->update($user,$practice); }
    public function archive(User $user, ClassPractice $practice): bool { return $this->admin($user) && $practice->status==='published'; }
}
