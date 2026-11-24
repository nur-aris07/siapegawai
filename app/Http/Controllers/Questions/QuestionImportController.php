<?php
namespace App\Http\Controllers\Questions;

use App\Http\Controllers\Controller;
use App\Models\Question;
use App\Models\QuestionImportBatch;
use App\Models\Subject;
use App\Services\QuestionCsvImportService;
use App\Services\QuestionEditorialService;
use App\Services\QuestionZipImportService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\Response;
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

    public function upload(Request $request, QuestionCsvImportService $service, QuestionZipImportService $zip): RedirectResponse
    {
        $this->authorized();
        if (!$request->hasFile('file')) {
            throw ValidationException::withMessages(['file'=>'CSV tidak diterima. Periksa pemilihan file dan batas upload_max_filesize/post_max_size.']);
        }
        $mode=$request->input('mode','csv');
        if(!in_array($mode,['csv','zip'],true)) throw ValidationException::withMessages(['mode'=>'Pilih mode CSV atau ZIP bergambar.']);
        $validator = Validator::make($request->all(), ['file'=>['required','file']]);
        $validator->validate();
        $batch = $mode==='zip' ? $zip->upload($request->file('file'),$request->user()) : $service->upload($request->file('file'), $request->user());
        return redirect()->route('questions.import.show', $batch->id)
            ->with('success', $batch->status === 'ready' ? 'Preview valid. Belum ada soal disimpan.' : 'Preview berisi kesalahan. Tidak dapat dikonfirmasi.');
    }

    public function show(string $batch, Request $request, QuestionCsvImportService $service, QuestionZipImportService $zip)
    {
        $this->authorized();
        $item = $service->owned($batch, $request->user());
        abort_if($item->status === 'discarded', 404);
        $isZip=$zip->isZip($item);
        $preview = $isZip ? $zip->preview($item) : $service->preview($item);
        return view('questions.admin.import-show', [
            'batch'=>$item, 'preview'=>$preview, 'isZip'=>$isZip,'canConfirm'=>$item->status === 'ready' && !$item->expires_at->isPast()
                && $preview['invalid'] === 0,
        ]);
    }

    public function confirm(string $batch, Request $request, QuestionCsvImportService $service, QuestionEditorialService $editor, QuestionZipImportService $zip): RedirectResponse
    {
        $this->authorized();
        $request->validate(['confirmed_import'=>['required','accepted']]);
        $item = $service->owned($batch,$request->user());
        $result = $zip->isZip($item) ? $zip->confirm($item,$request->user()) : $service->confirm($item,$request->user(),$editor);
        return redirect()->route('questions.import.show',$item->id)
            ->with('success', 'Batch berhasil diproses: '.($result['count'] ?? 0).' soal draft. Tidak ada publikasi/assignment.');
    }

    public function zipTemplate(): Response
    {
        $this->authorized();
        abort_unless(class_exists(\ZipArchive::class) && function_exists('imagepng'), 503);
        $file=tempnam(sys_get_temp_dir(),'sgtmpl');
        $zip=new \ZipArchive();
        try {
            if($zip->open($file,\ZipArchive::CREATE|\ZipArchive::OVERWRITE)!==true) abort(503);
            $csv=fopen('php://temp','w+');fputcsv($csv,QuestionCsvImportService::HEADER,',','"','');
            $subject=Subject::query()->where('status','active')->whereHas('topics',fn($q)=>$q->where('status','active'))->with(['topics'=>fn($q)=>$q->where('status','active')])->first();
            fputcsv($csv,['DEMO-CONTENT-01',$subject?->code ?? 'TWK',$subject?->topics->first()?->slug ?? 'ganti-topik',
                '', 'Pembahasan sintetis; bukan soal ujian nyata.', 'Opsi A','Opsi B','Opsi C','Opsi D','','A'],',','"','');
            rewind($csv);$zip->addFromString('questions.csv',stream_get_contents($csv));fclose($csv);
            $manifest=fopen('php://temp','w+');fputcsv($manifest,QuestionZipImportService::MEDIA_HEADER,',','"','');
            fputcsv($manifest,['DEMO-CONTENT-01','stem',1,'media/demo-stem.png',
                'Ilustrasi sintetik untuk template','Contoh gambar','Dibuat untuk demo','Perlu review hak media'],',','"','');
            rewind($manifest);$zip->addFromString('media.csv',stream_get_contents($manifest));fclose($manifest);
            $image=imagecreatetruecolor(32,32);$bg=imagecolorallocate($image,235,242,250);imagefill($image,0,0,$bg);
            $fg=imagecolorallocate($image,14,42,76);imagerectangle($image,5,5,26,26,$fg);
            ob_start();imagepng($image);$bytes=ob_get_clean();imagedestroy($image);
            $zip->addFromString('media/demo-stem.png',$bytes);$zip->close();
            $out=file_get_contents($file);
        } finally { if(is_file($file))@unlink($file); }
        return response($out,200,['Content-Type'=>'application/zip','X-Content-Type-Options'=>'nosniff',
            'Cache-Control'=>'private, no-store','Content-Disposition'=>'attachment; filename="siapegawai-content-studio-example.zip"']);
    }

    public function image(string $batch,int $index,Request $request,QuestionCsvImportService $csv,QuestionZipImportService $zip): Response
    {
        $this->authorized();
        $item=$csv->owned($batch,$request->user());
        abort_unless($zip->isZip($item),404);
        $image=$zip->previewImage($item,$index);
        return response($image['bytes'],200,['Content-Type'=>$image['mime'],
            'Content-Disposition'=>'inline; filename="private-preview"',
            'Cache-Control'=>'private, no-store, max-age=0','Pragma'=>'no-cache',
            'X-Content-Type-Options'=>'nosniff','X-Robots-Tag'=>'noindex, nofollow, noarchive']);
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
