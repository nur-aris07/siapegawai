<?php
namespace App\Policies;

use App\Models\QuickQuiz;
use App\Models\User;

class QuickQuizPolicy
{
    private function operator(User $user): bool { return $user->status === 'active' && in_array($user->role, ['operational_admin','superadmin'], true); }
    public function viewAny(User $user): bool { return $this->operator($user); }
    public function create(User $user): bool { return $this->operator($user); }
    public function view(User $user, QuickQuiz $quiz): bool { return $this->operator($user); }
    public function update(User $user, QuickQuiz $quiz): bool { return $this->operator($user) && $quiz->status !== 'archived'; }
    public function publish(User $user, QuickQuiz $quiz): bool { return $this->update($user, $quiz); }
    public function archive(User $user, QuickQuiz $quiz): bool { return $this->operator($user) && $quiz->status === 'published'; }
}
