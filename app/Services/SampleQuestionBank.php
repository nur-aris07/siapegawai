<?php

namespace App\Services;

class SampleQuestionBank
{
    public function all(): array
    {
        return json_decode(file_get_contents(resource_path('data/sample-questions.json')), true, 512, JSON_THROW_ON_ERROR);
    }

    public function exam(): array { return array_slice($this->all(), 0, 9); }
}
