<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tickets', function (Blueprint $table) {
            $table->string('payment_type', 50)
                ->nullable()
                ->after('has_financial_impact')
                ->comment('pay or receive_payment');

            $table->string('recurring_description', 200)
                ->nullable()
                ->after('payment_type')
                ->comment('Description of recurring payment schedule');
        });
    }

    public function down(): void
    {
        Schema::table('tickets', function (Blueprint $table) {
            $table->dropColumn(['payment_type', 'recurring_description']);
        });
    }
};
