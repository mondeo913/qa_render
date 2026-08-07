<?php
namespace App\Http\Controllers;
use App\Models\AlertRule;
use App\Models\NotificationRecord;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
class AlertController extends Controller {
    public function index(Request $request) {
        abort_unless($request->user()->hasPermission('alerts.view'),403);
        $filter=$request->string('filter','all')->toString();
        $query=NotificationRecord::query()->where('user_id',$request->user()->id);
        match($filter){
            'unread'=>$query->whereNull('read_at'),
            'alerts'=>$query->where(fn($q)=>$q->whereIn('status',['FAILED','WARNING'])->orWhereRaw("LOWER(subject) LIKE '%venc%' OR LOWER(subject) LIKE '%observ%' OR LOWER(subject) LIKE '%alert%'")),
            'errors'=>$query->where(fn($q)=>$q->where('status','FAILED')->orWhereNotNull('failure_message')),
            'updates'=>$query->where(fn($q)=>$q->whereRaw("LOWER(subject) LIKE '%actualiz%' OR LOWER(subject) LIKE '%valid%' OR LOWER(subject) LIKE '%cerr%'")),
            default=>null,
        };
        $counts=['all'=>NotificationRecord::where('user_id',$request->user()->id)->count(),'unread'=>NotificationRecord::where('user_id',$request->user()->id)->whereNull('read_at')->count(),'alerts'=>NotificationRecord::where('user_id',$request->user()->id)->where(fn($q)=>$q->whereIn('status',['FAILED','WARNING'])->orWhereRaw("LOWER(subject) LIKE '%venc%' OR LOWER(subject) LIKE '%observ%' OR LOWER(subject) LIKE '%alert%'"))->count(),'errors'=>NotificationRecord::where('user_id',$request->user()->id)->where(fn($q)=>$q->where('status','FAILED')->orWhereNotNull('failure_message'))->count(),'updates'=>NotificationRecord::where('user_id',$request->user()->id)->where(fn($q)=>$q->whereRaw("LOWER(subject) LIKE '%actualiz%' OR LOWER(subject) LIKE '%valid%' OR LOWER(subject) LIKE '%cerr%'"))->count()];
        return view('alerts.index',['notifications'=>$query->latest()->paginate(30)->withQueryString(),'rules'=>$request->user()->role?->code==='ADMINISTRADOR'?AlertRule::query()->orderBy('name')->get():collect(),'filter'=>$filter,'counts'=>$counts]);
    }
    public function read(Request $request,NotificationRecord $notification): RedirectResponse { abort_unless($notification->user_id===$request->user()->id,403);$notification->update(['read_at'=>now()]);return back()->with('success','Notificación marcada como leída.'); }
    public function readAll(Request $request): RedirectResponse { NotificationRecord::query()->where('user_id',$request->user()->id)->whereNull('read_at')->update(['read_at'=>now()]);return back()->with('success','Todas las notificaciones fueron marcadas como leídas.'); }
}
