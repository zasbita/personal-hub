<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('category_budgets')) {
            Schema::create('category_budgets', function (Blueprint $table) {
                $table->uuid('id')->primary();
                $table->bigInteger('user_id');
                $table->string('category');
                $table->decimal('monthly_limit', 15, 2);
                $table->timestampTz('created_at')->nullable()->useCurrent();
                $table->unique(['user_id', 'category']);
                // ponytail: SQLite checks via app validation; Supabase has CHECK monthly_limit 0-100M — keep in Supabase DDL only
            });
        }

        if (! Schema::hasTable('recurring_expenses')) {
            Schema::create('recurring_expenses', function (Blueprint $table) {
                $table->uuid('id')->primary();
                $table->bigInteger('user_id');
                $table->decimal('amount', 15, 2);
                $table->string('description', 200);
                $table->string('category', 30)->default('General');
                $table->integer('day_of_month');
                $table->timestampTz('created_at')->nullable()->useCurrent();
            });
        }

        if (! Schema::hasTable('vehicles')) {
            Schema::create('vehicles', function (Blueprint $table) {
                $table->uuid('id')->primary();
                $table->bigInteger('user_id');
                $table->string('name', 50);
                $table->integer('last_km');
                $table->integer('next_service_km');
                $table->integer('service_interval')->default(2000);
                $table->timestampTz('created_at')->nullable()->useCurrent();
            });
        }

        if (! Schema::hasTable('service_logs')) {
            Schema::create('service_logs', function (Blueprint $table) {
                $table->uuid('id')->primary();
                $table->uuid('vehicle_id');
                $table->integer('old_km')->nullable();
                $table->integer('new_km')->nullable();
                $table->timestampTz('created_at')->nullable()->useCurrent();
                $table->foreign('vehicle_id')->references('id')->on('vehicles')->cascadeOnDelete();
            });
        }

        if (! Schema::hasTable('fuel_logs')) {
            Schema::create('fuel_logs', function (Blueprint $table) {
                $table->uuid('id')->primary();
                $table->uuid('vehicle_id');
                $table->bigInteger('user_id')->nullable();
                $table->integer('km');
                $table->decimal('liters', 10, 2);
                $table->decimal('cost', 15, 2)->nullable();
                $table->timestampTz('created_at')->nullable()->useCurrent();
                $table->foreign('vehicle_id')->references('id')->on('vehicles')->cascadeOnDelete();
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('fuel_logs');
        Schema::dropIfExists('service_logs');
        Schema::dropIfExists('vehicles');
        Schema::dropIfExists('recurring_expenses');
        Schema::dropIfExists('category_budgets');
    }
};
