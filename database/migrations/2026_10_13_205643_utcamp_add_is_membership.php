<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('utcamp', function (Blueprint $table) {
            $table->boolean('is_membership')->after('blisswisdom_type')->nullable();
            $table->string('county')->nullable()->after('is_membership');   //居住地點
            $table->string('subarea')->nullable()->after('county');         //居住地點
            $table->string('unit_subarea')->nullable()->after('unit_county');   //服務單位所在地
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('utcamp', function (Blueprint $table) {
            //
            $table->dropColumn('is_membership');
            $table->dropColumn('county');
            $table->dropColumn('subarea');
            $table->dropColumn('unit_subarea');
        });
    }
};
