<?php

namespace Database\Seeders;

use App\Models\Application;
use App\Models\Listing;
use App\Relevance;
use Illuminate\Database\Seeder;

class ListingSeeder extends Seeder
{
    public function run(): void
    {
        // Unscored listings (freshly scraped)
        Listing::factory(10)->create();

        // Scored listings across relevance tiers
        Listing::factory(15)->scored(Relevance::Relevant)->create();
        Listing::factory(10)->scored(Relevance::Maybe)->create();
        Listing::factory(20)->scored(Relevance::Irrelevant)->create();

        // A few relevant listings with applications
        Listing::factory(5)
            ->scored(Relevance::Relevant)
            ->has(Application::factory()->ready())
            ->create();
    }
}
