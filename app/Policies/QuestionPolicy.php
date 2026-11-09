<?php
namespace App\Policies;

use App\Models\Question;
use App\Models\User;

class QuestionPolicy
{
    public function viewAny(User $user): bool { return $this->isOperator($user); }
    public function view(User $user, Question $question): bool { return $this->isOperator($user); }
    public function create(User $user): bool { return $this->isOperator($user); }
    public function update(User $user, Question $question): bool { return $this->isOperator($user) && $question->status !== 'archived'; }
    public function publish(User $user, Question $question): bool { return $this->update($user, $question); }
    public function discardDraft(User $user, Question $question): bool { return $this->update($user, $question); }
    public function archive(User $user, Question $question): bool { return $this->isOperator($user) && $question->status !== 'archived'; }
    private function isOperator(User $user): bool { return $user->status === 'active' && in_array($user->role, ['operational_admin', 'superadmin'], true); }
}
