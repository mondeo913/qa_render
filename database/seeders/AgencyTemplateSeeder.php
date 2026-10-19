<?php

namespace Database\Seeders;

use App\Models\ContractingAgency;
use Illuminate\Database\Seeder;

class AgencyTemplateSeeder extends Seeder
{
    public function run(): void
    {
        foreach ([
            ['SEGOB','Secretaría de Gobernación','Secretaría de Gobernación'],
            ['SENER','Secretaría de Energía','Secretaría de Energía'],
            ['BIENESTAR','Secretaría de Bienestar','Secretaría de Bienestar'],
            ['BANOBRAS','BANOBRAS','Banco Nacional de Obras y Servicios Públicos'],
            ['SEMAR','Secretaría de Marina','Secretaría de Marina'],
            ['IMSS','IMSS','Instituto Mexicano del Seguro Social'],
            ['IPAB','IPAB','Instituto para la Protección al Ahorro Bancario'],
        ] as [$code,$name,$legal]) {
            // Conservar registros y configuración existentes, incluso si usan otro código.
            $agency = ContractingAgency::query()->whereRaw('LOWER(code) = ?', [strtolower($code)])
                ->orWhereRaw('LOWER(name) = ?', [mb_strtolower($name)])
                ->orWhereRaw('LOWER(name) = ?', [mb_strtolower($legal)])
                ->orWhereRaw('LOWER(legal_name) = ?', [mb_strtolower($legal)])
                ->first();
            if (!$agency) {
                $normalize = static fn ($value) => preg_replace('/[^a-z0-9]/', '', \Illuminate\Support\Str::ascii(preg_replace('/\b(de|del|la|el)\b/u', '', mb_strtolower((string) $value))));
                $aliases = [$code, $name, $legal, str_replace('Secretaría', 'Sria.', $name), str_replace('Secretaría', 'Sría.', $name)];
                $keys = array_map($normalize, $aliases);
                $agency = ContractingAgency::all()->first(fn ($item) => in_array($normalize($item->code), $keys, true)
                    || in_array($normalize($item->name), $keys, true) || in_array($normalize($item->legal_name), $keys, true));
            }
            $agency ??= ContractingAgency::query()->firstOrCreate(
                ['code'=>$code],
                ['name'=>$name,'legal_name'=>$legal,'active'=>true]
            );

            app(\App\Services\AgencyWorkflowService::class)->provision($agency);
        }
    }
}
