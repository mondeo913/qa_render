<?php
namespace Database\Factories;
use App\Models\Role;
use Illuminate\Database\Eloquent\Factories\Factory;
class RoleFactory extends Factory {
    protected $model=Role::class;
    public function definition(): array {
        return ['code'=>$this->faker->unique()->lexify('ROLE_???'),'name'=>$this->faker->jobTitle(),'active'=>true];
    }
}