<?php

namespace Database\Seeders;

use App\Models\RssFeed;
use Illuminate\Database\Seeder;

class RssFeedSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        RssFeed::factory()->count(5)->create();
    }
}
