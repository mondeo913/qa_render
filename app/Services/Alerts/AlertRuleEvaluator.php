<?php
namespace App\Services\Alerts;
use App\Models\AlertRule;
use App\Models\OperationalIncident;
use App\Models\OperationalMetric;

final class AlertRuleEvaluator {
 public function evaluate(AlertRule $rule):?OperationalIncident {
  if(!$rule->enabled)return null;
  $from=now()->subMinutes($rule->evaluation_window_minutes);
  $value=(float)OperationalMetric::where('metric_key',$rule->metric_key)->where('collected_at','>=',$from)->avg('metric_value');
  $triggered=match($rule->operator){'>','gt'=>$value>(float)$rule->threshold,'>=','gte'=>$value>=(float)$rule->threshold,'<','lt'=>$value<(float)$rule->threshold,'<=','lte'=>$value<=(float)$rule->threshold,'=','eq'=>$value==(float)$rule->threshold,default=>false};
  if(!$triggered)return null;
  $recent=OperationalIncident::where('source',$rule->code)->where('opened_at','>=',now()->subMinutes($rule->cooldown_minutes))->whereIn('status',['OPEN','ACKNOWLEDGED'])->first();
  if($recent)return $recent;
  return OperationalIncident::create([
   'code'=>$rule->code.'-'.now()->format('YmdHis'),'title'=>$rule->name,'severity'=>$rule->severity,'status'=>'OPEN',
   'source'=>$rule->code,'description'=>"{$rule->metric_key}={$value}; threshold={$rule->threshold}",
   'opened_at'=>now(),'metadata'=>['value'=>$value,'threshold'=>$rule->threshold,'channels'=>$rule->channels]
  ]);
 }
}
