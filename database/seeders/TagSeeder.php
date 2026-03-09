<?php

namespace Database\Seeders;

use App\Models\Tag;
use Illuminate\Database\Seeder;

class TagSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $missing = max(0, 20 - Tag::query()->count());

        if ($missing === 0) {
            return;
        }

        Tag::factory()->count($missing)->create();
    }
}
