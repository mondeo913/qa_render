<?php
namespace Database\Factories;
use App\Models\ContractingAgency;
use App\Models\EvidenceTemplate;
use Illuminate\Database\Eloquent\Factories\Factory;
class EvidenceTemplateFactory extends Factory {
    protected $model=EvidenceTemplate::class;
    public function definition(): array {
        return [
            'contracting_agency_id'=>ContractingAgency::factory(),
            'code'=>$this->faker->unique()->lexify('TPL???'),
            'name'=>$this->faker->sentence(3),
            'version'=>1,'active'=>true,'requires_director_signature'=>true,
            'allowed_signed_extensions'=>['pdf']
        ];
    }
}
