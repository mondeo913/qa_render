<?php
// Acceptance fixtures are intentionally unavailable in any real deployment database.
require __DIR__.'/../../vendor/autoload.php';
$app = require __DIR__.'/../../bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();
if (getenv('SIGET_CI_ISOLATED') !== '1' || config('database.connections.pgsql.database') !== 'siget_ci_production') {
    fwrite(STDERR, "Estas operaciones sólo están habilitadas en la base aislada de CI.\n"); exit(1);
}
$action = $argv[1] ?? '';
if ($action === 'clean') {
    if (\App\Models\User::count() !== 0 || \App\Models\ScheduledLoad::count() !== 0) throw new RuntimeException('La instalación inicial contiene datos de prueba.');
    $role = \Illuminate\Support\Facades\DB::selectOne('select rolsuper, rolcreatedb, rolcreaterole from pg_roles where rolname = current_user');
    if ($role->rolsuper || $role->rolcreatedb || $role->rolcreaterole) throw new RuntimeException('La cuenta de aplicación tiene privilegios elevados.');
    echo "PRODUCTION CLEAN INSTALL + LEAST PRIVILEGE PASS\n";
} elseif ($action === 'make-pauta') {
    $book = new \PhpOffice\PhpSpreadsheet\Spreadsheet;
    $book->getActiveSheet()->fromArray([['Nombre del Archivo para bitácora'],
        ['01_XHSPRAG_'.now()->format('y.m.07').'.TESTIGO.CI_23.25.28.mp4'],
        ['02_XHSPRAG_'.now()->format('y.m.15').'.TESTIGO.CI_23.25.28.mp4']]);
    (new \PhpOffice\PhpSpreadsheet\Writer\Xlsx($book))->save('/tmp/siget-ci-pauta.xlsx');
    echo now()->year;
} elseif (in_array($action, ['open', 'expire'], true)) {
    $load = \App\Models\ScheduledLoad::findOrFail((int) ($argv[2] ?? 0));
    if ($action === 'open') $load->update(['effective_open_at' => now()->subHour(), 'effective_close_at' => now()->addHour(), 'status' => 'ABIERTA', 'is_blocked' => false]);
    else $load->update(['effective_close_at' => now()->subMinute()]);
} elseif ($action === 'assert-restored') {
    $target = $argv[2] ?? '';
    if (!str_starts_with($target, '/backups/.restore-tests/')) throw new RuntimeException('Destino de prueba no permitido.');
    foreach (\App\Models\EvidenceFile::all() as $file) {
        $path = $target.'/'.$file->storage_path;
        if (!is_file($path) || !hash_equals($file->sha256, hash_file('sha256', $path))) throw new RuntimeException('El archivo restaurado difiere de su hash registrado.');
    }
    echo "POSTGRES RESTORE + EVIDENCE SHA256 PASS\n";
} else {
    throw new RuntimeException('Acción de aceptación desconocida.');
}
