<?php
namespace App\Services;

use App\Models\{ContractingAgency, EvidenceTemplate, OrganizationalUnit, TemplateRequirement, User, UserScope};
use Illuminate\Support\Facades\DB;

final class AgencyWorkflowService
{
    public function provision(ContractingAgency $agency): void
    {
        DB::transaction(function () use ($agency) {
            $directionA = OrganizationalUnit::query()->firstOrCreate(
                ['contracting_agency_id'=>$agency->id,'code'=>'DIR_A'],
                ['name'=>'Dirección de Transmisión','unit_type'=>'DIRECTION','active'=>true]
            );
            $directionB = OrganizationalUnit::query()->firstOrCreate(
                ['contracting_agency_id'=>$agency->id,'code'=>'DIR_B'],
                ['name'=>'Dirección de Programación y Continuidad','unit_type'=>'DIRECTION','active'=>true]
            );

            $template = EvidenceTemplate::query()->where('contracting_agency_id', $agency->id)
                ->where('code', 'PAUTA_MENSUAL')->latest('version')->first();
            $newTemplate = $template === null;
            if ($template === null) $template = EvidenceTemplate::query()->firstOrCreate(
                ['contracting_agency_id'=>$agency->id,'code'=>'PAUTA_MENSUAL','version'=>1],
                [
                    'name'=>'Pauta mensual de evidencias',
                    'description'=>'Plantilla configurable para las dos direcciones y cierre institucional.',
                    'active'=>true,
                    'requires_director_signature'=>true,
                    'allowed_signed_extensions'=>['pdf','pdfa','jpg','jpeg','png','tif','tiff'],
                ]
            );

            if ($newTemplate) TemplateRequirement::query()->firstOrCreate(
                ['template_id'=>$template->id,'code'=>'BITACORA_EXCEL'],
                [
                    'name'=>'Bitácora Excel de la Dirección de Transmisión',
                    'responsible_unit_id'=>$directionA->id,
                    'responsible_role_code'=>'OPERADOR_TRANSMISION',
                    'required'=>true,
                    'min_files'=>1,
                    'max_files'=>3,
                    'allowed_extensions'=>['xlsx','xls','pdf','doc','docx'],
                    'sort_order'=>10,
                ]
            );

            if ($newTemplate) TemplateRequirement::query()->firstOrCreate(
                ['template_id'=>$template->id,'code'=>'VIDEO_TEMATICO'],
                [
                    'name'=>'Evidencia de Programación y Continuidad',
                    'responsible_unit_id'=>$directionB->id,
                    'responsible_role_code'=>'OPERADOR_PROGRAMACION_CONTINUIDAD',
                    'required'=>true,
                    'min_files'=>1,
                    'max_files'=>5,
                    'max_size_mb'=>500,
                    'allowed_extensions'=>['mp4','mov','pdf','jpg','png'],
                    'sort_order'=>20,
                ]
            );

            if (!$agency->active) return;
            foreach (User::with(['role', 'organizationalUnit'])->where('status', 'ACTIVE')
                ->whereNotNull('metadata->standard_profile')->get() as $user) {
                $role = $user->role?->code;
                if (!$user->role?->active || ($user->metadata['qa_retired'] ?? false)
                    || ($user->metadata['standard_profile'] ?? null) !== $role) continue;
                $direction = match ($role) {
                    'DIRECTOR_TRANSMISION', 'OPERADOR_TRANSMISION' => 'DIR_A',
                    'DIRECTOR_PROGRAMACION_CONTINUIDAD', 'OPERADOR_PROGRAMACION_CONTINUIDAD' => 'DIR_B',
                    default => null,
                };
                if ($direction && $user->organizationalUnit?->code !== $direction) continue;
                $unit = $direction === 'DIR_A' ? $directionA : ($direction === 'DIR_B' ? $directionB : null);
                if ($unit && !$unit->active) continue;
                UserScope::firstOrCreate(['user_id' => $user->id, 'contracting_agency_id' => $agency->id,
                    'organizational_unit_id' => $unit?->id], ['can_read' => true,
                    'can_write' => in_array($role, ['ADMINISTRADOR', 'ENLACE_INSTITUCIONAL', 'OPERADOR_TRANSMISION', 'OPERADOR_PROGRAMACION_CONTINUIDAD'], true)]);
            }
        });
    }
}
