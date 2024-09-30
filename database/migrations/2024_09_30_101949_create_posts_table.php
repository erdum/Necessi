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
        Schema::create('posts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->enum('type', ['item', 'service']);
            $table->string('title');
            $table->text('description');
            $table->string('location');
            $table->decimal('lat', 7, 4)->nullable();
            $table->decimal('long', 7, 4)->nullable();
            $table->integer('budget');
            $table->timestamp('start_date');
            $table->timestamp('end_date');
            $table->boolean('delivery_requested')->default(false);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('posts');
    }
};
