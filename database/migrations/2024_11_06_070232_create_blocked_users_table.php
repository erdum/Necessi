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
        Schema::create('blocked_users', function (Blueprint $table) {
            $table->id();
            $table->foreignId('blocker_id')->references('id')->on('users')
                ->onDelete('cascade');
            $table->foreignId('blocked_id')->references('id')->on('users')
                ->onDelete('cascade');
            $table->enum('reason_type', [
                'harassment',
                'spam',
                'scam',
                'privacy violation',
                'inappropriate content',
                'unwanted content',
                'uncomfortable interaction',
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
        Schema::dropIfExists('blocked_users');
    }
};
