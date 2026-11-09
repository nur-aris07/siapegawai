<?php
namespace Database\Seeders;

use App\Models\Subject;
use App\Models\Topic;
use Illuminate\Database\Seeder;

class SiapegawaiSubjectsSeeder extends Seeder
{
    public function run(): void
    {
        $names = ['TWK'=>'Tes Wawasan Kebangsaan', 'TIU'=>'Tes Intelegensia Umum', 'TKP'=>'Tes Karakteristik Pribadi', 'SKB'=>'Seleksi Kompetensi Bidang'];
        foreach ($names as $code => $name) {
            $subject = Subject::query()->firstOrCreate(['code'=>$code], ['name'=>$name, 'status'=>'active', 'sort_order'=>array_search($code, array_keys($names), true)+1]);
            Topic::query()->firstOrCreate(['subject_id'=>$subject->id, 'slug'=>'umum'], ['name'=>'Umum', 'status'=>'active', 'sort_order'=>1]);
        }
    }
}
