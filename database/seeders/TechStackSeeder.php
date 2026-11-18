<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\TechStack;

class TechStackSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $stacks = [
            ['name' => 'LARAVEL', 'sort_order' => 1],
            ['name' => 'FILAMENT PHP', 'sort_order' => 2],
            ['name' => 'BLADE', 'sort_order' => 3],
            ['name' => 'LIVEWIRE', 'sort_order' => 4],
            ['name' => 'TAILWIND CSS', 'sort_order' => 5],
            ['name' => 'ALPINE.JS', 'sort_order' => 6],
            ['name' => 'GSAP', 'sort_order' => 7],
            ['name' => 'VITE', 'sort_order' => 8],
            ['name' => 'MYSQL', 'sort_order' => 9],
        ];

        foreach ($stacks as $stack) {
            TechStack::create($stack);
        }
    }
}
