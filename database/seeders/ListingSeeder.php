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
        Listing::factory(8)->scored(Relevance::Relevant)->create();
        Listing::factory(10)->scored(Relevance::Maybe)->create();
        Listing::factory(20)->scored(Relevance::Irrelevant)->create();

        // Relevant listings that have been read
        Listing::factory(5)->scored(Relevance::Relevant)->read()->create();

        // Starred listings (mix of relevant and maybe — things to come back to)
        Listing::factory(3)->scored(Relevance::Relevant)->read()->starred()->create();
        Listing::factory(2)->scored(Relevance::Maybe)->read()->starred()->create();

        // Shortlisted listings (reviewed and ready to apply)
        Listing::factory(4)->scored(Relevance::Relevant)->read()->shortlisted()->create();

        // Applied listings with ready applications
        Listing::factory(5)
            ->scored(Relevance::Relevant)
            ->read()
            ->shortlisted()
            ->has(Application::factory()->ready())
            ->create();
    }
}
