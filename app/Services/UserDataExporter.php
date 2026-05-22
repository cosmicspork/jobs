<?php

namespace App\Services;

use App\Models\AiUsage;
use App\Models\Application;
use App\Models\ApplicationQuestion;
use App\Models\ApplicationQuestionSet;
use App\Models\Listing;
use App\Models\ListingUser;
use App\Models\TargetProfile;
use App\Models\User;

class UserDataExporter
{
    public const SCHEMA_VERSION = '1';

    /**
     * Build the full "everything you own" manifest for the user.
     *
     * @return array<string, mixed>
     */
    public function export(User $user): array
    {
        return [
            'schema_version' => self::SCHEMA_VERSION,
            'generated_at' => now()->toIso8601String(),
            'user' => $this->userFields($user),
            'target_profiles' => $this->targetProfiles($user),
            'applications' => $this->applications($user),
            'application_question_sets' => $this->questionSets($user),
            'listing_interactions' => $this->listingInteractions($user),
            'ai_usages' => $this->aiUsages($user),
            'board_subscriptions' => $user->subscribedBoardKeys(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    protected function userFields(User $user): array
    {
        return [
            'id' => $user->id,
            'name' => $user->name,
            'email' => $user->email,
            'summary' => $user->summary,
            'skills' => $user->skills ?? [],
            'experience' => $user->experience ?? [],
            'education' => $user->education ?? [],
            'experience_years' => $user->experience_years,
            'preferences' => $user->preferences ?? [],
            'prompts' => $user->prompts ?? [],
            'timezone' => $user->timezone,
            'digest_enabled' => $user->digest_enabled,
            'digest_time' => $user->digest_time,
            'created_at' => $user->created_at?->toIso8601String(),
        ];
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    protected function targetProfiles(User $user): array
    {
        return $user->targetProfiles()->get()
            ->map(fn (TargetProfile $t): array => [
                'id' => $t->id,
                'name' => $t->name,
                'positioning' => $t->positioning,
                'target_titles' => $t->target_titles ?? [],
                'criteria' => $t->criteria ?? [],
                'is_active' => $t->is_active,
                'sort_order' => $t->sort_order,
                'created_at' => $t->created_at?->toIso8601String(),
            ])
            ->all();
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    protected function applications(User $user): array
    {
        return $user->applications()
            ->with('listing:id,title,company,url')
            ->get()
            ->map(fn (Application $a): array => [
                'id' => $a->id,
                'listing_id' => $a->listing_id,
                'listing' => $this->listingSnapshot($a->listing),
                'target_profile_id' => $a->target_profile_id,
                'status' => $a->status?->value,
                'resume_content' => $a->resume_content,
                'cover_letter_content' => $a->cover_letter_content,
                'applied_at' => $a->applied_at?->toIso8601String(),
                'created_at' => $a->created_at?->toIso8601String(),
            ])
            ->all();
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    protected function questionSets(User $user): array
    {
        return $user->questionSets()
            ->with(['questions', 'listing:id,title,company,url'])
            ->get()
            ->map(fn (ApplicationQuestionSet $set): array => [
                'id' => $set->id,
                'listing_id' => $set->listing_id,
                'listing' => $this->listingSnapshot($set->listing),
                'target_profile_id' => $set->target_profile_id,
                'status' => $set->status?->value,
                'questions' => $set->questions
                    ->map(fn (ApplicationQuestion $q): array => [
                        'question' => $q->question,
                        'answer' => $q->answer,
                        'feedback' => $q->feedback,
                        'grammar_corrections' => $q->grammar_corrections,
                        'suggested_answer' => $q->suggested_answer,
                        'final_answer' => $q->final_answer,
                    ])
                    ->all(),
            ])
            ->all();
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    protected function listingInteractions(User $user): array
    {
        return ListingUser::query()
            ->where('user_id', $user->id)
            ->with('listing:id,title,company,url')
            ->get()
            ->map(fn (ListingUser $pivot): array => [
                'listing_id' => $pivot->listing_id,
                'listing' => $this->listingSnapshot($pivot->listing),
                'target_profile_id' => $pivot->target_profile_id,
                'relevance' => $pivot->relevance?->value,
                'scored_at' => $pivot->scored_at?->toIso8601String(),
                'read_at' => $pivot->read_at?->toIso8601String(),
                'starred_at' => $pivot->starred_at?->toIso8601String(),
                'shortlisted_at' => $pivot->shortlisted_at?->toIso8601String(),
                'dismissed_at' => $pivot->dismissed_at?->toIso8601String(),
            ])
            ->all();
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    protected function aiUsages(User $user): array
    {
        return $user->aiUsages()->get()
            ->map(fn (AiUsage $u): array => [
                'agent' => $u->agent,
                'provider' => $u->provider,
                'model' => $u->model,
                'prompt_tokens' => $u->prompt_tokens,
                'completion_tokens' => $u->completion_tokens,
                'cache_write_tokens' => $u->cache_write_tokens,
                'cache_read_tokens' => $u->cache_read_tokens,
                'cost' => (float) $u->cost,
                'created_at' => $u->created_at?->toIso8601String(),
            ])
            ->all();
    }

    /**
     * @return array{title: string|null, company: string|null, url: string|null}|null
     */
    protected function listingSnapshot(?Listing $listing): ?array
    {
        if ($listing === null) {
            return null;
        }

        return [
            'title' => $listing->title,
            'company' => $listing->company,
            'url' => $listing->url,
        ];
    }
}
