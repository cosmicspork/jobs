<?php

namespace App\Filament\Resources\Applications\Pages;

use App\Filament\Resources\Applications\ApplicationResource;
use Filament\Resources\Pages\ListRecords;

class ListApplications extends ListRecords
{
    protected static string $resource = ApplicationResource::class;

    protected function getHeaderActions(): array
    {
        // Applications are created from a listing's "Start application"
        // action, not from this index. Keeping the index header clean keeps
        // that as the only entry point.
        return [];
    }
}
