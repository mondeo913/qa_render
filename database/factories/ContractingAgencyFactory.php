<?php
namespace Database\Factories;
use App\Models\ContractingAgency;
use Illuminate\Database\Eloquent\Factories\Factory;
class ContractingAgencyFactory extends Factory {
    protected $model=ContractingAgency::class;
    public function definition(): array {
        return ['code'=>$this->faker->unique()->lexify('AG???'),'name'=>$this->faker->company(),'active'=>true];
    }
}
