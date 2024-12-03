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
        Schema::create('reported_comments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('reporter_id')->references('id')->on('users')
                ->onDelete('cascade');
            $table->foreignId('reported_id')->references('id')->on('post_comments')
                ->onDelete('cascade'); 
            $table->enum('reason_type', [
                'offensive language',
                'harassment or bullying',
                'spam or irrelevance',
                'misleading or false information',
                'violation of community guidelines',
                'other',
            ]);
            $table->string('other_reason')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('reported_comments');
    }
};
