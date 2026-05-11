<?php

namespace App\Filament\Resources\Listings\Pages;

use App\Filament\Resources\Listings\ListingResource;
use App\Jobs\ScoreListing;
use App\Models\Listing;
use App\Models\TargetProfile;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\CreateRecord;
use Filament\Support\Exceptions\Halt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class CreateListing extends CreateRecord
{
    protected static string $resource = ListingResource::class;

    protected function beforeCreate(): void
    {
        if (auth()->user()->activeTargets()->isEmpty()) {
            Notification::make()
                ->title('No active target')
                ->body('Add an active target profile before creating a listing.')
                ->danger()
                ->send();

            throw new Halt;
        }
    }

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        unset($data['relevance']);

        $data['board'] ??= 'manual';
        $data['scraped_at'] ??= now();
        $data['source_url'] = $data['source_url'] ?? $data['url'];

        return $data;
    }

    protected function afterCreate(): void
    {
        /** @var Listing $listing */
        $listing = $this->record;
        $userId = auth()->id();
        $now = now();
        $targets = auth()->user()->activeTargets();

        $rows = $targets
            ->map(fn (TargetProfile $target): array => [
                'id' => (string) Str::ulid(),
                'listing_id' => $listing->id,
                'user_id' => $userId,
                'target_profile_id' => $target->id,
                'created_at' => $now,
                'updated_at' => $now,
            ])
            ->all();

        DB::table('listing_user')->insert($rows);

        foreach ($targets as $target) {
            ScoreListing::dispatch($listing, $target);
        }
    }

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('view', ['record' => $this->getRecord()]);
    }
}
