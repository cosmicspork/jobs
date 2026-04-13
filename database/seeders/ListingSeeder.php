<?php

namespace Database\Seeders;

use App\Models\Application;
use App\Models\Listing;
use App\Models\ListingUser;
use App\Models\User;
use App\Relevance;
use Illuminate\Database\Seeder;

class ListingSeeder extends Seeder
{
    public function run(): void
    {
        $user = User::first() ?? User::factory()->create();

        // Unscored listings (freshly scraped)
        $unscoredListings = Listing::factory(10)->create();
        foreach ($unscoredListings as $listing) {
            ListingUser::create([
                'listing_id' => $listing->id,
                'user_id' => $user->id,
            ]);
        }

        // Scored listings across relevance tiers
        $this->createScoredListings($user, 8, Relevance::Relevant);
        $this->createScoredListings($user, 10, Relevance::Maybe);
        $this->createScoredListings($user, 20, Relevance::Irrelevant);

        // Relevant listings that have been read
        $this->createScoredListings($user, 5, Relevance::Relevant, ['read_at' => now()]);

        // Starred listings (mix of relevant and maybe)
        $this->createScoredListings($user, 3, Relevance::Relevant, ['read_at' => now(), 'starred_at' => now()]);
        $this->createScoredListings($user, 2, Relevance::Maybe, ['read_at' => now(), 'starred_at' => now()]);

        // Shortlisted listings (reviewed and ready to apply)
        $this->createScoredListings($user, 4, Relevance::Relevant, ['read_at' => now(), 'shortlisted_at' => now()]);

        // Applied listings with ready applications
        $appliedListings = Listing::factory(5)->create();
        foreach ($appliedListings as $listing) {
            ListingUser::create([
                'listing_id' => $listing->id,
                'user_id' => $user->id,
                'relevance' => Relevance::Relevant,
                'score_data' => $this->scoreData(),
                'scored_at' => now(),
                'read_at' => now(),
                'shortlisted_at' => now(),
            ]);
            Application::factory()->ready()->create([
                'listing_id' => $listing->id,
                'user_id' => $user->id,
            ]);
        }
    }

    /**
     * @param  array<string, mixed>  $pivotExtras
     */
    private function createScoredListings(User $user, int $count, Relevance $relevance, array $pivotExtras = []): void
    {
        $listings = Listing::factory($count)->create();
        foreach ($listings as $listing) {
            ListingUser::create(array_merge([
                'listing_id' => $listing->id,
                'user_id' => $user->id,
                'relevance' => $relevance,
                'score_data' => $this->scoreData(),
                'scored_at' => now(),
            ], $pivotExtras));
        }
    }

    /**
     * @return array<string, mixed>
     */
    private function scoreData(): array
    {
        return [
            'matched_skills' => ['PHP', 'Laravel'],
            'gaps' => ['Go'],
            'reasoning' => 'Good match for a Laravel developer.',
            'role_type' => fake()->randomElement(['em', 'ic', 'hybrid']),
            'posting_quality_signals' => ['salary listed'],
        ];
    }
}
