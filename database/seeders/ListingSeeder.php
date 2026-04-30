<?php

namespace Database\Seeders;

use App\Models\Application;
use App\Models\Listing;
use App\Models\ListingUser;
use App\Models\TargetProfile;
use App\Models\User;
use App\Relevance;
use Illuminate\Database\Seeder;

class ListingSeeder extends Seeder
{
    public function run(): void
    {
        $user = User::first() ?? User::factory()->create();
        $target = $user->targetProfiles()->first()
            ?? TargetProfile::factory()->for($user)->create();

        // Unscored listings (freshly scraped)
        $unscoredListings = Listing::factory(10)->create();
        foreach ($unscoredListings as $listing) {
            ListingUser::create([
                'listing_id' => $listing->id,
                'user_id' => $user->id,
                'target_profile_id' => $target->id,
            ]);
        }

        // Scored listings across relevance tiers
        $this->createScoredListings($user, $target, 8, Relevance::Relevant);
        $this->createScoredListings($user, $target, 10, Relevance::Maybe);
        $this->createScoredListings($user, $target, 20, Relevance::Irrelevant);

        // Relevant listings that have been read
        $this->createScoredListings($user, $target, 5, Relevance::Relevant, ['read_at' => now()]);

        // Starred listings (mix of relevant and maybe)
        $this->createScoredListings($user, $target, 3, Relevance::Relevant, ['read_at' => now(), 'starred_at' => now()]);
        $this->createScoredListings($user, $target, 2, Relevance::Maybe, ['read_at' => now(), 'starred_at' => now()]);

        // Shortlisted listings (reviewed and ready to apply)
        $this->createScoredListings($user, $target, 4, Relevance::Relevant, ['read_at' => now(), 'shortlisted_at' => now()]);

        // Applied listings with ready applications
        $appliedListings = Listing::factory(5)->create();
        foreach ($appliedListings as $listing) {
            ListingUser::create([
                'listing_id' => $listing->id,
                'user_id' => $user->id,
                'target_profile_id' => $target->id,
                'relevance' => Relevance::Relevant,
                'score_data' => $this->scoreData(),
                'scored_at' => now(),
                'read_at' => now(),
                'shortlisted_at' => now(),
            ]);
            Application::factory()->ready()->create([
                'listing_id' => $listing->id,
                'user_id' => $user->id,
                'target_profile_id' => $target->id,
            ]);
        }
    }

    /**
     * @param  array<string, mixed>  $pivotExtras
     */
    private function createScoredListings(User $user, TargetProfile $target, int $count, Relevance $relevance, array $pivotExtras = []): void
    {
        $listings = Listing::factory($count)->create();
        foreach ($listings as $listing) {
            ListingUser::create(array_merge([
                'listing_id' => $listing->id,
                'user_id' => $user->id,
                'target_profile_id' => $target->id,
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
            'posting_quality_signals' => ['salary listed'],
        ];
    }
}
