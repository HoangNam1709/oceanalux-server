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
        $table->string('thumbnail')->nullable()->after('name'); // Thêm cột ảnh đại diện
    });
}

public function down()
{
    Schema::table('cruises', function (Blueprint $table) {
        $table->dropColumn('thumbnail');
    });
}
};
