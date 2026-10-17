<?php

require __DIR__.'/../../vendor/autoload.php';
$app = require __DIR__.'/../../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
if (! app()->environment('testing')) {
    throw new RuntimeException('Browser fixtures are restricted to the testing environment.');
}
$users = App\Models\User::with('role')->where('status', 'ACTIVE')->get()->map(fn ($user) => [
    'id' => $user->id, 'email' => $user->email, 'role' => $user->role->code,
]);
$directory = storage_path('framework/testing');
if (! is_dir($directory)) mkdir($directory, 0777, true);
file_put_contents($directory.'/ui-users.json', $users->toJson(JSON_PRETTY_PRINT));
echo $users->count()." browser QA accounts exported.\n";
