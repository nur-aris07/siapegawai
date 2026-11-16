<?php
namespace App\Http\Controllers\Questions;

use App\Http\Controllers\Controller;
use App\Models\Question;
use App\Models\QuestionImportBatch;
use App\Models\Subject;
use App\Services\QuestionCsvImportService;
use App\Services\QuestionEditorialService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\StreamedResponse;

class QuestionImportController extends Controller
{
    private function authorized(): void { Gate::authorize('create', Question::class); }

    public function index(Request $request, QuestionCsvImportService $service)
    {
        $this->authorized();
        return view('questions.admin.import', [
            'maxRecords'=>$service->maxRecords(),'maxBytes'=>$service->maxBytes(),
            'batches'=>QuestionImportBatch::query()->where('actor_id',$request->user()->id)
                ->where('status','!=','discarded')->latest()->limit(10)->get(),
        ]);
    }

    public function upload(Request $request, QuestionCsvImportService $service): RedirectResponse
    {
        $this->authorized();
        if (!$request->hasFile('file')) {
            throw ValidationException::withMessages(['file'=>'CSV tidak diterima. Periksa pemilihan file dan batas upload_max_filesize/post_max_size.']);
        }
        $validator = Validator::make($request->all(), ['file'=>['required','file']]);
        $validator->validate();
        $batch = $service->upload($request->file('file'), $request->user());
        return redirect()->route('questions.import.show', $batch->id)
            ->with('success', $batch->status === 'ready' ? 'Preview valid. Belum ada soal disimpan.' : 'Preview berisi kesalahan. Tidak dapat dikonfirmasi.');
    }

    public function show(string $batch, Request $request, QuestionCsvImportService $service)
    {
        $this->authorized();
        $item = $service->owned($batch, $request->user());
        abort_if($item->status === 'discarded', 404);
        $preview = $service->preview($item);
        return view('questions.admin.import-show', [
            'batch'=>$item, 'preview'=>$preview,'canConfirm'=>$item->status === 'ready' && !$item->expires_at->isPast()
                && $preview['invalid'] === 0,
        ]);
    }

    public function confirm(string $batch, Request $request, QuestionCsvImportService $service, QuestionEditorialService $editor): RedirectResponse
    {
        $this->authorized();
        $request->validate(['confirmed_import'=>['required','accepted']]);
        $item = $service->owned($batch,$request->user());
        $result = $service->confirm($item,$request->user(),$editor);
        return redirect()->route('questions.import.show',$item->id)
            ->with('success', 'Batch berhasil diproses: '.($result['count'] ?? 0).' soal draft. Tidak ada publikasi/assignment.');
    }

    public function discard(string $batch, Request $request, QuestionCsvImportService $service): RedirectResponse
    {
        $this->authorized();
        $request->validate(['confirmed_discard'=>['required','accepted']]);
        $service->discard($service->owned($batch, $request->user()), $request->user());
        return redirect()->route('questions.import.index')
            ->with('success','Batch belum committed telah dibuang. Data soal dan histori tidak berubah.');
    }

    private function export(string $filename, array $header, iterable $rows): StreamedResponse
    {
        return response()->streamDownload(function () use ($header,$rows) {
            $out = fopen('php://output','wb');
            fwrite($out, "\xEF\xBB\xBF");
            fputcsv($out,$header,',','"','');
            foreach ($rows as $row) fputcsv($out,array_map(fn($v)=>QuestionCsvImportService::csvCell((string)$v),$row),',','"','');
            fclose($out);
        },$filename,['Content-Type'=>'text/csv; charset=UTF-8', 'X-Content-Type-Options'=>'nosniff', 'Cache-Control'=>'no-store']);
    }

    public function template(QuestionCsvImportService $service): StreamedResponse
    {
        $this->authorized();
        // One empty input row, with an active reference pair if available. No sample question is created.
        $subject = Subject::query()->where('status','active')
            ->whereHas('topics', fn ($q) => $q->where('status','active'))
            ->with(['topics' => fn ($q) => $q->where('status','active')->orderBy('slug')])
            ->orderBy('code')->first();
        $row = array_fill(0, count(QuestionCsvImportService::HEADER), '');
        if ($subject && $subject->topics->isNotEmpty()) {
            $row[1] = $subject->code;
            $row[2] = $subject->topics->first()->slug;
        }
        return $this->export('siapegawai-template-impor-soal.csv', QuestionCsvImportService::HEADER, [$row]);
    }

    public function references(): StreamedResponse
    {
        $this->authorized();
        $rows = [];
        foreach (Subject::query()->where('status','active')->with(['topics'=>fn($q)=>$q->where('status','active')->orderBy('slug')])->orderBy('code')->get() as $subject) {
            foreach ($subject->topics as $topic) $rows[] = [$subject->code,$subject->name,$topic->slug,$topic->name];
        }
        return $this->export('siapegawai-referensi-topik-aktif.csv', ['subject_code','subject_name','topic_slug','topic_name'],$rows);
    }

    public function errors(string $batch, Request $request, QuestionCsvImportService $service): StreamedResponse
    {
        $this->authorized();
        $item = $service->owned($batch, $request->user());
        abort_if($item->status === 'discarded', 404);
        $rows = [];
        foreach ($item->errors ?? [] as $error) {
            foreach ($error['messages'] as $message) {
                $rows[] = [$error['record'],$error['line'],$error['code'],$message];
            }
        }
        return $this->export('siapegawai-kesalahan-'.$item->id.'.csv', ['record','source_line','code','error'],$rows);
    }
}
