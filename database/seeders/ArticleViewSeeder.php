<?php

namespace Database\Seeders;

use App\Models\ArticleView;
use Illuminate\Database\Seeder;

class ArticleViewSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        ArticleView::factory()->count(20)->create();
    }
}
