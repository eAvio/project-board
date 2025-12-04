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
        Schema::table('boards', function (Blueprint $table) {
            if (!Schema::hasColumn('boards', 'background_url')) {
                $table->string('background_url', 500)->nullable()->after('boardable_id');
            }
            if (!Schema::hasColumn('boards', 'background_color')) {
                $table->string('background_color', 20)->nullable()->after('background_url');
            }
        });

        Schema::table('cards', function (Blueprint $table) {
            if (!Schema::hasColumn('cards', 'estimated_hours')) {
                $table->decimal('estimated_hours', 8, 2)->nullable()->after('description');
            }
            if (!Schema::hasColumn('cards', 'estimated_cost')) {
                $table->decimal('estimated_cost', 10, 2)->nullable()->after('estimated_hours');
            }
            if (!Schema::hasColumn('cards', 'actual_hours')) {
                $table->decimal('actual_hours', 8, 2)->nullable()->after('estimated_cost');
            }
            if (!Schema::hasColumn('cards', 'actual_cost')) {
                $table->decimal('actual_cost', 10, 2)->nullable()->after('actual_hours');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('cards', function (Blueprint $table) {
            if (Schema::hasColumn('cards', 'actual_cost')) {
                $table->dropColumn('actual_cost');
            }
            if (Schema::hasColumn('cards', 'actual_hours')) {
                $table->dropColumn('actual_hours');
            }
            if (Schema::hasColumn('cards', 'estimated_cost')) {
                $table->dropColumn('estimated_cost');
            }
            if (Schema::hasColumn('cards', 'estimated_hours')) {
                $table->dropColumn('estimated_hours');
            }
        });

        Schema::table('boards', function (Blueprint $table) {
            if (Schema::hasColumn('boards', 'background_color')) {
                $table->dropColumn('background_color');
            }
            if (Schema::hasColumn('boards', 'background_url')) {
                $table->dropColumn('background_url');
            }
        });
    }
};
