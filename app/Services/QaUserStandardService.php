<?php

namespace App\Services;

use App\Models\{AuditLog, ContractingAgency, NotificationRecord, ReviewAssignment, Role, ScheduledLoadDeliverable, User, UserScope};
use Illuminate\Support\Facades\DB;

final class QaUserStandardService
{
    public const DEFINITIONS = [
        ['ADMINISTRADOR', 'admin@siget.local', 'Administrador', null],
        ['DIRECTOR_GENERAL', 'director.general@siget.local', 'Director General', null],
        ['DIRECTOR_TRANSMISION', 'director@siget.local', 'Director de Transmisión', 'DIR_A'],
        ['DIRECTOR_PROGRAMACION_CONTINUIDAD', 'director.produccion@siget.local', 'Director de Programación y Continuidad', 'DIR_B'],
        ['ENLACE_INSTITUCIONAL', 'enlace@siget.local', 'Enlace Institucional', null],
        ['OPERADOR_TRANSMISION', 'operador.monitoreo@siget.local', 'Operador de Transmisión', 'DIR_A'],
        ['OPERADOR_PROGRAMACION_CONTINUIDAD', 'operador.produccion@siget.local', 'Operador de Programación y Continuidad', 'DIR_B'],
        ['FISCALIZADOR', 'fiscalizador@siget.local', 'Fiscalizador', null],
    ];

    /** Consolidates managed demo accounts only; historical actors remain intact. */
    public function sync(?string $newPassword = null): array
    {
        return DB::transaction(function () use ($newPassword) {
            $agencies = ContractingAgency::with('units')->where('active', true)->orderBy('id')->get();
            if ($agencies->isEmpty()) throw new \RuntimeException('No existen dependencias configuradas para los alcances QA.');
            $users = [];
            $byRole = [];
            foreach (self::DEFINITIONS as [$code, $email, $name, $unitCode]) {
                $role = Role::where('code', $code)->firstOrFail();
                $root = $unitCode ? $agencies->flatMap->units->where('active', true)->firstWhere('code', $unitCode) : null;
                if ($unitCode && !$root) throw new \RuntimeException("Falta la Dirección {$unitCode}.");
                $user = User::firstOrNew(['email' => $email]);
                if (!$user->exists) {
                    $old = User::where('role_id', $role->id)->where('metadata->qa', true)->first();
                    $user->password = $old?->getRawOriginal('password') ?: env('SIGET_QA_PASSWORD', 'SigetQA_2026_Cambiar!');
                    $user->status = $old?->status ?: 'ACTIVE';
                    $user->email_verified_at = now();
                }
                if ($newPassword !== null) $user->password = $newPassword;
                $user->fill(['role_id' => $role->id, 'name' => $name, 'contracting_agency_id' => null,
                    'organizational_unit_id' => $root?->id,
                    'metadata' => array_merge($user->metadata ?? [], ['qa' => true, 'qa_retired' => false, 'standard_profile' => $code])]);
                $user->save();
                UserScope::where('user_id', $user->id)->delete();
                foreach ($agencies as $agency) {
                    $unit = $unitCode ? $agency->units->where('active', true)->firstWhere('code', $unitCode) : null;
                    if ($unitCode && !$unit) continue;
                    UserScope::create(['user_id' => $user->id, 'contracting_agency_id' => $agency->id,
                        'organizational_unit_id' => $unit?->id, 'can_read' => true,
                        'can_write' => in_array($code, ['ADMINISTRADOR', 'ENLACE_INSTITUCIONAL', 'OPERADOR_TRANSMISION', 'OPERADOR_PROGRAMACION_CONTINUIDAD'], true)]);
                }
                $users[$email] = $user;
                $byRole[$code] = $user;
            }
            foreach (User::with(['role', 'organizationalUnit'])->where('metadata->qa', true)->whereNotIn('id', collect($users)->pluck('id')->all())->get() as $duplicate) {
                if (($duplicate->metadata['qa_retired'] ?? false) === true) continue;
                $code = $duplicate->role?->code;
                if (in_array($code, ['DIRECTOR', 'OPERADOR'], true)) {
                    $code = ($code === 'DIRECTOR' ? 'DIRECTOR_' : 'OPERADOR_').($duplicate->organizationalUnit?->code === 'DIR_B' ? 'PROGRAMACION_CONTINUIDAD' : 'TRANSMISION');
                }
                $replacement = $byRole[$code] ?? null;
                if (!$replacement) continue;
                ScheduledLoadDeliverable::where('responsible_user_id', $duplicate->id)->update(['responsible_user_id' => $replacement->id]);
                foreach (ReviewAssignment::where('fiscalizador_id', $duplicate->id)->where('active', true)->get() as $assignment) {
                    ReviewAssignment::updateOrCreate(['scheduled_load_id' => $assignment->scheduled_load_id, 'fiscalizador_id' => $replacement->id],
                        ['assigned_by' => $assignment->assigned_by, 'active' => true, 'notes' => $assignment->notes]);
                    $assignment->update(['active' => false]);
                }
                NotificationRecord::where('user_id', $duplicate->id)->whereNull('read_at')->update(['user_id' => $replacement->id]);
                UserScope::where('user_id', $duplicate->id)->delete();
                $duplicate->remember_token = null;
                $duplicate->update(['status' => 'INACTIVE',
                    'metadata' => array_merge($duplicate->metadata ?? [], ['qa_retired' => true, 'qa_merged_into' => $replacement->id])]);
                AuditLog::create(['user_id' => $users['admin@siget.local']->id, 'event' => 'QA_USER_CONSOLIDATED',
                    'entity_type' => User::class, 'entity_id' => $duplicate->id,
                    'old_values' => ['user_id' => $duplicate->id], 'new_values' => ['standard_user_id' => $replacement->id, 'status' => 'INACTIVE']]);
            }
            return $users;
        });
    }
}
