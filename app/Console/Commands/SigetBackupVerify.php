<?php
namespace App\Console\Commands;
use App\Models\BackupExecution;
use Illuminate\Console\Command;

final class SigetBackupVerify extends Command {
 protected $signature='siget:backup-verify {backupId}';protected $description='Verifica existencia y SHA-256 de un respaldo';
 public function handle():int {
  $backup=BackupExecution::findOrFail($this->argument('backupId'));
  if ($backup->metadata['manifest'] ?? null) {
   try { app(\App\Services\MonthlyBackupService::class)->verify(dirname($backup->metadata['manifest'])); }
   catch (\Throwable $e) { return $this->failedVerification($backup, 'El respaldo no supera la verificación de integridad.'); }
  }
  foreach(['database_file'=>'database_sha256','storage_file'=>'storage_sha256'] as $fileField=>$hashField){
   $path=$backup->{$fileField};if(!$path||!is_file($path)){return $this->failedVerification($backup, "Falta $path");}
   if(!$backup->{$hashField} || hash_file('sha256',$path)!==$backup->{$hashField}){return $this->failedVerification($backup, "Hash ausente o inválido: $path");}
  }
  $restored = !($backup->metadata['manifest'] ?? null) || !empty($backup->metadata['restore_tested_at']);
  $backup->update(['verified_at'=>$restored ? now() : null,'status'=>$restored ? 'VERIFIED' : 'HASH_VERIFIED']);
  $this->info($restored ? 'Respaldo verificado' : 'Integridad verificada; restauración de prueba pendiente.');return self::SUCCESS;
 }
 private function failedVerification(BackupExecution $backup, string $message): int {
  $backup->update(['status'=>'VERIFICATION_FAILED','verified_at'=>null,'error_message'=>'Falló la comprobación de integridad.',
   'metadata'=>\Illuminate\Support\Arr::except($backup->metadata ?? [], ['restore_tested_at','restore_database'])]);
  if ($backup->backup_type === 'MONTHLY_FULL') app(\App\Services\MonthlyBackupService::class)->incident('El respaldo mensual no supera la comprobación de integridad.');
  $this->error($message);return self::FAILURE;
 }
}
