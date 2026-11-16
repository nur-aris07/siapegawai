<?php
namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\BulkQuestionAssignmentService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class BulkQuestionAssignmentController extends Controller
{
    public function search(Request $request,string $domain,int $target,BulkQuestionAssignmentService $service): JsonResponse
    {
        $data=$request->validate(['q'=>['nullable','string','max:100'],'subject'=>['nullable','string','max:12'],
            'page'=>['nullable','integer','between:1,1000']]);
        return response()->json($service->search($domain,$target,trim($data['q']??''),trim($data['subject']??''),(int)($data['page']??1)));
    }

    public function preview(Request $request,string $domain,int $target,BulkQuestionAssignmentService $service): JsonResponse
    {
        $data=$request->validate(['version_ids'=>['required','array','min:1','max:100'],
            'version_ids.*'=>['required','integer','min:1']]);
        return response()->json($service->preview($domain,$target,$request->user(),$data['version_ids']));
    }

    public function confirm(Request $request,string $domain,int $target,BulkQuestionAssignmentService $service): JsonResponse
    {
        $data=$request->validate(['token'=>['required','string','max:12000']]);
        return response()->json($service->confirm($domain,$target,$request->user(),$data['token']));
    }
}
