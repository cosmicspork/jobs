<?php

namespace App\Ai\Tools;

use App\Models\Listing;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Tools\Request;
use Stringable;

class GetJobPosting implements Tool
{
    public function description(): Stringable|string
    {
        return 'Returns the full details of a specific job posting by its ID.';
    }

    public function handle(Request $request): Stringable|string
    {
        $listing = Listing::findOrFail($request['listing_id']);

        return json_encode([
            'id' => $listing->id,
            'title' => $listing->title,
            'company' => $listing->company,
            'description' => $listing->description,
            'salary_min' => $listing->salary_min,
            'salary_max' => $listing->salary_max,
            'remote' => $listing->remote,
            'url' => $listing->url,
        ], JSON_THROW_ON_ERROR | JSON_PRETTY_PRINT);
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'listing_id' => $schema->string()->required(),
        ];
    }
}
