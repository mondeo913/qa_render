<?php
namespace App\Http\Controllers;
use App\Models\EvidenceFile;
use App\Models\ScheduledLoad;
use App\Services\AccessScopeService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;
class RepositoryController extends Controller {
    public function index(Request $request,AccessScopeService $access): View {
        $filters=$request->validate(['q'=>['nullable','string','max:200'],'status'=>['nullable','string','max:60'],'agency_id'=>['nullable','integer'],'from'=>['nullable','date'],'to'=>['nullable','date']]);
        $user=$request->user();$unitIds=$access->accessibleUnitIds($user);
        $base=$access->scopeLoads(ScheduledLoad::query(),$user);
        $accessibleIds=(clone $base)->pluck('id');
        $agencyCounts=(clone $base)->selectRaw('contracting_agency_id, COUNT(*) AS total')->groupBy('contracting_agency_id')->pluck('total','contracting_agency_id');
        $agencies=(clone $base)->with('agency')->get()->pluck('agency')->filter()->unique('id')->sortBy('name')->values();
        $query=ScheduledLoad::query()->with(['agency','deliverables'=>function($d)use($access,$user,$unitIds){if($unitIds!==[])$access->scopeDeliverables($d,$user);$d->with(['organizationalUnit','evidences.files']);}])->whereIn('id',$accessibleIds);
        if(!empty($filters['agency_id']))$query->where('contracting_agency_id',(int)$filters['agency_id']);
        if(!empty($filters['q'])){$term='%'.mb_strtolower($filters['q']).'%';$query->where(fn($b)=>$b->whereRaw('LOWER(title) LIKE ?',[$term])->orWhereRaw('LOWER(period_label) LIKE ?',[$term])->orWhereHas('agency',fn($a)=>$a->whereRaw('LOWER(name) LIKE ?',[$term])));}
        if(!empty($filters['status']))$query->where('status',$filters['status']);
        if(!empty($filters['from']))$query->whereDate('effective_open_at','>=',$filters['from']);
        if(!empty($filters['to']))$query->whereDate('effective_open_at','<=',$filters['to']);
        $loads=$query->orderByDesc('effective_open_at')->paginate(24)->withQueryString();
        $recentFiles=EvidenceFile::query()->with(['evidence.scheduledLoad.agency'])->whereHas('evidence',fn($e)=>$e->whereIn('scheduled_load_id',$accessibleIds))->latest()->limit(12)->get();
        $usedBytes=EvidenceFile::query()->whereHas('evidence',fn($e)=>$e->whereIn('scheduled_load_id',$accessibleIds))->sum('size_bytes');
        return view('repositorio.index',compact('loads','filters','agencies','agencyCounts','recentFiles','usedBytes'));
    }
}
