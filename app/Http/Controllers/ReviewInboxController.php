<?php
namespace App\Http\Controllers;
use App\Models\ScheduledLoad;
use App\Services\AccessScopeService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;
final class ReviewInboxController extends Controller {
    public function __invoke(Request $request,AccessScopeService $access): View {
        abort_unless($request->user()->role?->code==='ENLACE_INSTITUCIONAL'||$request->user()->role?->code==='ADMINISTRADOR',403);
        $status=$request->string('status','pending')->toString();
        $query=$access->scopeLoads(ScheduledLoad::query()->with(['agency','deliverables.organizationalUnit','deliverables.evidences.files','institutionalReview']),$request->user());
        match($status){
            'observed'=>$query->where('status','OBSERVADA'),
            'closed'=>$query->whereIn('status',['VALIDADA','VALIDADO_Y_CERRADO']),
            default=>$query->whereIn('status',['ENTREGADA','EN_REVISION_INSTITUCIONAL','LISTA_PARA_FIRMA','PENDIENTE_DOCUMENTO_FIRMADO']),
        };
        return view('revision.inbox',['loads'=>$query->orderBy('effective_close_at')->paginate(20)->withQueryString(),'status'=>$status,'counts'=>[
            'pending'=>$access->scopeLoads(ScheduledLoad::query(),$request->user())->whereIn('status',['ENTREGADA','EN_REVISION_INSTITUCIONAL','LISTA_PARA_FIRMA','PENDIENTE_DOCUMENTO_FIRMADO'])->count(),
            'observed'=>$access->scopeLoads(ScheduledLoad::query(),$request->user())->where('status','OBSERVADA')->count(),
            'closed'=>$access->scopeLoads(ScheduledLoad::query(),$request->user())->whereIn('status',['VALIDADA','VALIDADO_Y_CERRADO'])->count(),
        ]]);
    }
}
