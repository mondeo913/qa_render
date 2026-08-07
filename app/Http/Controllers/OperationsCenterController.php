<?php
namespace App\Http\Controllers;
use App\Models\BackupExecution;
use App\Models\OperationalIncident;
use App\Models\OperationalMetric;
use App\Services\Operations\HealthAggregationService;
use App\Services\Operations\SlaService;
use Illuminate\Http\Request;

final class OperationsCenterController extends Controller {
 public function index(HealthAggregationService $health,SlaService $sla){
  $this->authorize('operations.view');
  return view('operations.index',[
   'health'=>$health->collect(),'sla'=>$sla->summary(now()->startOfMonth(),now()),
   'incidents'=>OperationalIncident::latest('opened_at')->limit(10)->get(),
   'backups'=>BackupExecution::latest('started_at')->limit(5)->get(),
  ]);
 }
 public function health(HealthAggregationService $health){$this->authorize('operations.view');return response()->json($health->collect());}
 public function incidents(){ $this->authorize('operations.view');return view('operations.incidents',['incidents'=>OperationalIncident::latest('opened_at')->paginate(30)]);}
 public function backups(){ $this->authorize('operations.view');return view('operations.backups',['backups'=>BackupExecution::latest('started_at')->paginate(30)]);}
}
