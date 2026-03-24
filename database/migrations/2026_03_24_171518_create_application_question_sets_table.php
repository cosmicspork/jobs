<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('application_question_sets', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('listing_id')->nullable()->constrained('listings')->cascadeOnDelete();
            $table->string('status')->default('draft');
            $table->timestamps();
        });

        Schema::create('application_questions', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('question_set_id')->constrained('application_question_sets')->cascadeOnDelete();
            $table->text('question');
            $table->text('answer');
            $table->text('feedback')->nullable();
            $table->text('grammar_corrections')->nullable();
            $table->text('suggested_answer')->nullable();
            $table->text('final_answer')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('application_questions');
        Schema::dropIfExists('application_question_sets');
    }
};
