<?php 

declare(strict_types=1);

namespace Tests\Support\Models;

use CodeIgniter\Model;
use Faker\Generator;

final class UserModel extends Model
{
    protected $table = 'users';
    protected $allowedFields = ['name'];

    public function fake(Generator &$faker)
    {
        return [
            'name' => $faker->name()
        ];
    }
}
