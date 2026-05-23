<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        DB::transaction(function (): void {
            DB::table('users')
                ->select('id', 'education')
                ->orderBy('id')
                ->each(function (object $user): void {
                    $education = $user->education === null ? [] : json_decode($user->education, true);

                    if (! is_array($education) || $education === []) {
                        return;
                    }

                    $converted = array_map(function ($entry): array {
                        if (is_array($entry) && array_key_exists('qualification', $entry)) {
                            return $entry;
                        }

                        return [
                            'qualification' => (string) $entry,
                            'institution' => null,
                            'field_of_study' => null,
                            'period' => null,
                            'highlights' => [],
                        ];
                    }, $education);

                    DB::table('users')
                        ->where('id', $user->id)
                        ->update(['education' => json_encode($converted)]);
                });

            Schema::table('users', function (Blueprint $table): void {
                $table->dropColumn(['experience_years', 'prompts', 'preferences']);
            });
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->string('experience_years')->nullable()->after('education');
            $table->json('preferences')->nullable()->after('experience_years');
            $table->json('prompts')->nullable()->after('preferences');
        });

        DB::table('users')
            ->select('id', 'education')
            ->orderBy('id')
            ->each(function (object $user): void {
                $education = $user->education === null ? [] : json_decode($user->education, true);

                if (! is_array($education) || $education === []) {
                    return;
                }

                $flattened = array_map(function ($entry) {
                    if (is_string($entry)) {
                        return $entry;
                    }

                    if (! is_array($entry)) {
                        return '';
                    }

                    $qualification = trim((string) ($entry['qualification'] ?? ''));
                    $field = trim((string) ($entry['field_of_study'] ?? ''));
                    $institution = trim((string) ($entry['institution'] ?? ''));

                    $head = $qualification;

                    if ($field !== '') {
                        $head = $head === '' ? $field : "{$head}, {$field}";
                    }

                    if ($institution !== '') {
                        $head = $head === '' ? $institution : "{$head} — {$institution}";
                    }

                    return $head;
                }, $education);

                DB::table('users')
                    ->where('id', $user->id)
                    ->update(['education' => json_encode(array_values(array_filter($flattened, fn ($s) => $s !== '')))]);
            });
    }
};
