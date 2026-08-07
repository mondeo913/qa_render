<?php
namespace App\Services;
use App\Models\RepositoryFolder;
use App\Models\ScheduledLoad;
use Illuminate\Support\Str;

final class RepositoryService
{
    public function createLoadTree(ScheduledLoad $load, int $userId): RepositoryFolder
    {
        $rootKey = implode('/', [
            Str::slug((string) $load->contracting_agency_id),
            $load->effective_open_at->format('Y'),
            Str::slug($load->period_label ?: $load->effective_open_at->format('Y-m')),
            'carga-'.$load->id,
        ]);

        $root = RepositoryFolder::query()->firstOrCreate(
            ['path_key'=>$rootKey],
            [
                'contracting_agency_id'=>$load->contracting_agency_id,
                'scheduled_load_id'=>$load->id,
                'folder_type'=>'SCHEDULED_LOAD',
                'name'=>$load->title,
                'created_by'=>$userId,
            ]
        );

        foreach ($load->deliverables()->with('organizationalUnit')->get() as $deliverable) {
            RepositoryFolder::query()->firstOrCreate(
                ['path_key'=>$rootKey.'/direcciones/'.$deliverable->organizational_unit_id],
                [
                    'parent_id'=>$root->id,
                    'contracting_agency_id'=>$load->contracting_agency_id,
                    'organizational_unit_id'=>$deliverable->organizational_unit_id,
                    'scheduled_load_id'=>$load->id,
                    'folder_type'=>'OPERATIONAL_UNIT',
                    'name'=>$deliverable->organizationalUnit?->name ?? 'Dirección',
                    'created_by'=>$userId,
                ]
            );
        }

        foreach ([
            'revision-institucional'=>'03 Revisión Institucional',
            'documentos-firmados'=>'04 Documentos Firmados',
            'cierre'=>'05 Cierre',
        ] as $suffix=>$name) {
            RepositoryFolder::query()->firstOrCreate(
                ['path_key'=>$rootKey.'/'.$suffix],
                [
                    'parent_id'=>$root->id,
                    'contracting_agency_id'=>$load->contracting_agency_id,
                    'scheduled_load_id'=>$load->id,
                    'folder_type'=>strtoupper(str_replace('-','_',$suffix)),
                    'name'=>$name,
                    'created_by'=>$userId,
                ]
            );
        }

        return $root;
    }
}
