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
   catch (\Throwable $e) { $this->error('El respaldo no supera la verificación de integridad.'); return self::FAILURE; }
  }
  foreach(['database_file'=>'database_sha256','storage_file'=>'storage_sha256'] as $fileField=>$hashField){
   $path=$backup->{$fileField};if(!$path||!is_file($path)){$this->error("Falta $path");return self::FAILURE;}
   if(!$backup->{$hashField} || hash_file('sha256',$path)!==$backup->{$hashField}){$this->error("Hash ausente o inválido: $path");return self::FAILURE;}
  }
  $backup->update(['verified_at'=>now(),'status'=>'VERIFIED']);$this->info('Respaldo verificado');return self::SUCCESS;
 }
}
