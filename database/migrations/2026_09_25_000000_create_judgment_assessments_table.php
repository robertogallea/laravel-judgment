<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('judgment_assessments', function (Blueprint $table) {
            $table->id();
            $table->string('judgment')->index();
            $table->nullableMorphs('subject');
            $table->json('evidence')->nullable();
            $table->char('evidence_fingerprint', 64);
            $table->json('untrusted_paths');
            $table->string('language')->nullable();
            $table->char('questions_fingerprint', 64)->index();
            $table->json('answers');
            $table->string('engine');
            $table->string('model');
            $table->string('request_id')->nullable();
            $table->json('provenance_details');
            $table->string('decision')->nullable();
            $table->string('decision_version')->nullable();
            $table->string('outcome')->nullable();
            $table->timestamps();
            $table->index('created_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('judgment_assessments');
    }
};
