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
        Schema::create('reported_users', function (Blueprint $table) {
            $table->id();
            $table->foreignId('reporter_id')->references('id')->on('users')
                ->onDelete('cascade');
            $table->foreignId('reported_id')->references('id')->on('users')
                ->onDelete('cascade');
            $table->enum('reason_type', [
                'inappropriate behavior',
                'fraudulent activity',
                'harassment or abuse',
                'spam or scamming',
                'violation of platform rules',
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
        Schema::dropIfExists('reported_users');
    }
};
