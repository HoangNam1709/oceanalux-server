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
    Schema::table('cruises', function (Blueprint $table) {
        $table->string('destination')->nullable()->after('name'); // Điểm đến
        $table->integer('duration_days')->default(3)->after('description'); // Số ngày
        $table->integer('duration_nights')->default(2)->after('duration_days'); // Số đêm
    });
}

public function down()
{
    Schema::table('cruises', function (Blueprint $table) {
        $table->dropColumn(['destination', 'duration_days', 'duration_nights']);
    });
}
};
