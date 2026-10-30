<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('payment_late_fee_rules', function (Blueprint $table) {
            $table->id();

            $table->foreignId('society_id')
                ->constrained('societies')
                ->cascadeOnDelete();
            $table->unsignedInteger('grace_period_days')->nullable()->default(0);
            $table->enum('fine_type', ['fixed', 'percentage']);
            $table->decimal('fine_amount', 15, 2);

            $table->timestamps();
            $table->softDeletes();
            // Composite unique key
            $table->unique(
                ['society_id', 'grace_period_days'],
                'society_grace_unique'
            );
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payment_late_fee_rules');
    }
};
