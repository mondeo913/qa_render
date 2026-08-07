<?php
namespace App\Services\Operations;
use App\Models\OperationalIncident;

final class SlaService {
 public function summary(\DateTimeInterface $from,\DateTimeInterface $to):array {
  $incidents=OperationalIncident::whereBetween('opened_at',[$from,$to])->get();
  $downtime=$incidents->whereIn('severity',['CRITICAL','HIGH'])->sum(fn($i)=>$i->resolved_at? $i->opened_at->diffInSeconds($i->resolved_at):$i->opened_at->diffInSeconds(now()));
  $period=max(1,$to->getTimestamp()-$from->getTimestamp());
  return [
   'availability_percent'=>round(max(0,100-(($downtime/$period)*100)),4),
   'incident_count'=>$incidents->count(),
   'mttr_minutes'=>round($incidents->whereNotNull('resolved_at')->avg(fn($i)=>$i->opened_at->diffInMinutes($i->resolved_at))??0,2),
  ];
 }
}
