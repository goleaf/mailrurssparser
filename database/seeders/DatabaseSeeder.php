<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        User::query()->firstOrCreate(
            ['email' => 'admin@example.com'],
            [
                'name' => 'Admin User',
                'password' => Hash::make('password'),
                'email_verified_at' => now(),
            ],
        );

        User::query()->firstOrCreate(
            ['email' => 'editor@example.com'],
            [
                'name' => 'Editor User',
                'password' => Hash::make('password'),
                'email_verified_at' => now(),
            ],
        );

        $this->call([
            ShieldSeeder::class,
            CategorySeeder::class,
            SubCategorySeeder::class,
            TagSeeder::class,
            RssFeedSeeder::class,
            ArticleSeeder::class,
            NewsletterSubscriberSeeder::class,
            RssParseLogSeeder::class,
            ArticleViewSeeder::class,
            BookmarkSeeder::class,
            MetricSeeder::class,
        ]);
    }
}
