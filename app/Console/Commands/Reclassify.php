<?php

namespace App\Console\Commands;

use App\Models\ListingUser;
use App\Models\TargetProfile;
use App\Relevance;
use App\Services\ListingFilter;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Artisan;

#[Signature('jobs:reclassify {--user= : Restrict to one user id} {--target= : Restrict to one target_profile id} {--rescore : Pass C — re-queue surviving relevant pivots through the LLM} {--limit=50 : Max survivors to re-score in Pass C} {--dry-run : Report counts only, change nothing}')]
#[Description('Re-gate already-scored relevant/maybe pivots against current criteria using stored data only (Pass A/B, free), then optionally re-score a bounded set of survivors with the current prompt (Pass C).')]
class Reclassify extends Command
{
    public function handle(ListingFilter $filter): int
    {
        $dry = (bool) $this->option('dry-run');

        $counts = [
            'scanned' => 0,
            'demotedFilter' => 0,
            'demotedHeuristic' => 0,
            'survivors' => 0,
            'rescored' => 0,
        ];

        /** @var array<int, string> $survivorIds */
        $survivorIds = [];

        $query = ListingUser::query()
            ->whereNotNull('scored_at')
            ->whereIn('relevance', [Relevance::Relevant->value, Relevance::Maybe->value])
            ->whereHas('targetProfile', fn ($q) => $q->where('is_active', true))
            ->with(['listing', 'targetProfile']);

        if (filled($user = $this->option('user'))) {
            $query->where('user_id', (int) $user);
        }

        if (filled($target = $this->option('target'))) {
            $query->where('target_profile_id', (string) $target);
        }

        $query->chunkById(200, function ($pivots) use ($filter, $dry, &$counts, &$survivorIds): void {
            foreach ($pivots as $pivot) {
                $listing = $pivot->listing;
                $target = $pivot->targetProfile;

                if ($listing === null || $target === null) {
                    continue;
                }

                $counts['scanned']++;

                // PASS A — deterministic re-gate against current criteria.
                if ($reason = $filter->reasonToSkip($listing, $target)) {
                    $counts['demotedFilter']++;

                    if (! $dry) {
                        $pivot->update([
                            'relevance' => Relevance::Irrelevant,
                            'score_data' => ['filtered' => true, 'filter_reason' => $reason->value],
                        ]);
                    }

                    continue;
                }

                // PASS B — stored-data heuristic: demote when none of the
                // target's core skills appear in the prior matched_skills.
                $core = $this->coreKeywords($target);
                $matched = $this->matchedSkillsHaystack($pivot);

                if ($core !== [] && ! $this->anyKeywordMatches($core, $matched)) {
                    $counts['demotedHeuristic']++;

                    if (! $dry) {
                        $demoted = $pivot->relevance === Relevance::Relevant
                            ? Relevance::Maybe
                            : Relevance::Irrelevant;

                        $pivot->update([
                            'relevance' => $demoted,
                            'score_data' => array_merge((array) $pivot->score_data, [
                                'reclassified' => 'no_core_skill',
                            ]),
                        ]);
                    }

                    continue;
                }

                // SURVIVOR — only relevant pivots are eligible for re-scoring,
                // to bound LLM cost.
                if ($pivot->relevance === Relevance::Relevant) {
                    $counts['survivors']++;
                    $survivorIds[] = $pivot->id;
                }
            }
        });

        // PASS C — bounded LLM re-score via the canonical pipeline. Nulling
        // scored_at lets jobs:score pick them up, reusing its enrichment,
        // profile, inactive-target and monthly-cap checks verbatim.
        if ($this->option('rescore') && ! $dry && $survivorIds !== []) {
            $limit = max(0, (int) $this->option('limit'));
            $toRescore = array_slice($survivorIds, 0, $limit);

            if ($toRescore !== []) {
                ListingUser::query()->whereIn('id', $toRescore)->update(['scored_at' => null]);
                $counts['rescored'] = count($toRescore);

                $this->info("Re-scoring {$counts['rescored']} survivor(s) via jobs:score…");
                Artisan::call('jobs:score', [], $this->getOutput());
            }
        }

        $this->info(sprintf(
            'Reclassify%s | scanned %d | demoted (filter) %d | demoted (heuristic) %d | survivors %d | re-scored %d',
            $dry ? ' [DRY-RUN]' : '',
            $counts['scanned'],
            $counts['demotedFilter'],
            $counts['demotedHeuristic'],
            $counts['survivors'],
            $counts['rescored'],
        ));

        if ($survivorIds !== [] && ! $this->option('rescore')) {
            $this->line('Run again with --rescore --limit=N to re-score survivors through the LLM.');
        }

        return self::SUCCESS;
    }

    /**
     * The target's "core" skill keywords (lower-cased): its must_have_keywords,
     * falling back to the global config list. Empty makes Pass B a no-op.
     *
     * @return array<int, string>
     */
    protected function coreKeywords(TargetProfile $target): array
    {
        $keywords = (array) $target->criterion('must_have_keywords', []);

        if ($keywords === []) {
            $keywords = (array) config('scoring.core_keywords', []);
        }

        return array_values(array_filter(array_map(
            fn ($k): string => mb_strtolower(trim((string) $k)),
            $keywords,
        )));
    }

    /**
     * Lower-cased blob of the pivot's previously-matched skills, used as the
     * haystack for the core-keyword check. Guards old/filter rows that have no
     * matched_skills key.
     */
    protected function matchedSkillsHaystack(ListingUser $pivot): string
    {
        $matched = (array) (($pivot->score_data['matched_skills'] ?? []));

        return mb_strtolower(implode("\n", array_map('strval', $matched)));
    }

    /**
     * @param  array<int, string>  $needles  already lower-cased
     */
    protected function anyKeywordMatches(array $needles, string $haystack): bool
    {
        foreach ($needles as $needle) {
            if ($needle !== '' && str_contains($haystack, $needle)) {
                return true;
            }
        }

        return false;
    }
}
