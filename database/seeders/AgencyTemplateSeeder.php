<?php

namespace Database\Seeders;

use App\Models\ContractingAgency;
use App\Models\EvidenceTemplate;
use App\Models\OrganizationalUnit;
use App\Models\TemplateRequirement;
use Illuminate\Database\Seeder;

class AgencyTemplateSeeder extends Seeder
{
    public function run(): void
    {
        foreach ([
            ['IMSS','IMSS','Instituto Mexicano del Seguro Social'],
            ['IPAB','IPAB','Instituto para la Protección al Ahorro Bancario'],
        ] as [$code,$name,$legal]) {
            $agency = ContractingAgency::query()->updateOrCreate(
                ['code'=>$code],
                ['name'=>$name,'legal_name'=>$legal,'active'=>true]
            );

            $directionA = OrganizationalUnit::query()->updateOrCreate(
                ['contracting_agency_id'=>$agency->id,'code'=>'DIR_A'],
                ['name'=>'Dirección de Transmisión','unit_type'=>'DIRECTION','active'=>true]
            );
            $directionB = OrganizationalUnit::query()->updateOrCreate(
                ['contracting_agency_id'=>$agency->id,'code'=>'DIR_B'],
                ['name'=>'Dirección de Programación y Continuidad','unit_type'=>'DIRECTION','active'=>true]
            );

            $template = EvidenceTemplate::query()->updateOrCreate(
                ['contracting_agency_id'=>$agency->id,'code'=>'PAUTA_MENSUAL','version'=>1],
                [
                    'name'=>'Pauta mensual de evidencias',
                    'description'=>'Plantilla configurable para las dos direcciones y cierre institucional.',
                    'active'=>true,
                    'requires_director_signature'=>true,
                    'allowed_signed_extensions'=>['pdf','pdfa','jpg','jpeg','png','tif','tiff'],
                ]
            );

            TemplateRequirement::query()->updateOrCreate(
                ['template_id'=>$template->id,'code'=>'BITACORA_EXCEL'],
                [
                    'name'=>'Bitácora Excel de la Dirección de Transmisión',
                    'responsible_unit_id'=>$directionA->id,
                    'responsible_role_code'=>'OPERADOR_TRANSMISION',
                    'required'=>true,
                    'min_files'=>1,
                    'max_files'=>3,
                    'allowed_extensions'=>['xlsx','xls','pdf'],
                    'sort_order'=>10,
                ]
            );

            TemplateRequirement::query()->updateOrCreate(
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
        }
    }
}
