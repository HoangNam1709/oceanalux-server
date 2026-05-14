<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up()
    {
        Schema::table('schedules', function (Blueprint $table) {
            $table->foreignId('holiday_id')->nullable()->constrained('holidays')->onDelete('set null');
            
            $table->decimal('price_factor', 4, 2)->default(1.00)->after('status'); 
        });
    }

    public function down()
    {
        Schema::table('schedules', function (Blueprint $table) {
            $table->dropForeign(['holiday_id']);
            $table->dropColumn(['holiday_id', 'price_factor']);
        });
    }
};
