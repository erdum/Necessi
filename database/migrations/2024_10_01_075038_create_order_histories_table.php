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
        Schema::create('order_histories', function (Blueprint $table) {
            $table->id();
            $table->foreignId('bid_id')->constrained('post_bids')
                ->onDelete('cascade');
            $table->foreignUuid('transaction_id')->nullable()->constrained()
                ->onDelete('cascade');
            $table->timestamp('received_by_borrower')->nullable();
            $table->timestamp('received_by_lender')->nullable();
            $table->timestamps();
            $table->unique('bid_id');
            $table->unique('transaction_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('order_histories');
    }
};
