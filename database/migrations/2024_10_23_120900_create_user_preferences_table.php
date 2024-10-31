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
        Schema::create('user_preferences', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->boolean('general_notifications')->default(true);
            $table->boolean('biding_notifications')->default(true);
            $table->boolean('transaction_notifications')->default(true);
            $table->boolean('activity_notifications')->default(true);
            $table->boolean('messages_notifications')->default(true);
            $table->enum('who_can_see_connections', [
                'public',
                'connections',
                'only_me',
            ]);
            $table->enum('who_can_send_messages', [
                'public',
                'connections',
                'only_me',
            ]);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('user_preferences');
    }
};
