<?php
namespace Database\Factories;
use App\Models\Role;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Hash;
class UserFactory extends Factory {
    protected $model=User::class;
    protected static ?string $password;
    public function definition(): array {
        return [
            'role_id'=>Role::factory(),
            'name'=>$this->faker->name(),
            'email'=>$this->faker->unique()->safeEmail(),
            'password'=>static::$password ??= Hash::make('password'),
            'status'=>'ACTIVE',
        ];
    }
}