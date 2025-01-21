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
        Schema::create('reports', function (Blueprint $table) {
            $table->id();
            $table->foreignId('reporter_id')->constrained('users')
                ->onDelete('cascade');
            $table->morphs('reportable');
            $table->enum('reason_type', [
                'inappropriate behavior',
                'fraudulent activity',
                'harassment or abuse',
                'spam or scamming',
                'violation of platform rules',
                'inappropriate content',
                'misleading or fraudulent',
                'prohibited items or services',
                'spam or irrelevance',
                'harassment or harmful behavior',
                'offensive language',
                'harassment or bullying',
                'misleading or false information',
                'violation of community guidelines',
                'other',
            ]);
            $table->string('other_reason')->nullable();
            $table->timestamps();

            $table->unique(
                ['reporter_id', 'reportable_id', 'reportable_type'],
                'unique_user_report'
            );
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('reports');
    }
};
