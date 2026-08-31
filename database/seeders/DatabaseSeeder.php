<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    public function run(): void
    {
        User::factory()->create([
            'name'  => 'Operator',
            'email' => 'operator@dpay.pl',
            'is_operator' => true,
        ]);

        $this->call(BankingSeeder::class);
    }
}
