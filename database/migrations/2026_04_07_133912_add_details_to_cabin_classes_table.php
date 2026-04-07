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
        Schema::table('cabin_classes', function (Blueprint $table) {
            $table->integer('area')->nullable()->after('name'); 
            $table->string('deck')->nullable()->after('area'); 
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('cabin_classes', function (Blueprint $table) {
            //
        });
    }
};
