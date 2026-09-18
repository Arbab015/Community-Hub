<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('block_payment_plans', function (Blueprint $table) {
            $table->id();
            $table->foreignId('block_id')->constrained('blocks')->cascadeOnDelete();
            $table->boolean('is_commercial');
            $table->foreignId('payment_template_id')->nullable()->constrained('payment_templates')->nullOnDelete();
            $table->json('installment_template_ids')->nullable();
            $table->integer('min_size')->nullable();
            $table->integer('max_size')->nullable();
            $table->enum('size_unit', ['marla', 'square_yard'])->default('marla');
            $table->boolean('apply_to_units')->default(false);
            $table->timestamps();

            $table->unique(['block_id', 'is_commercial']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('block_payment_plans');
    }
};
