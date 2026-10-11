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
        Schema::table('tcamp', function (Blueprint $table) {
            $table->renameColumn('unit_district', 'unit_subarea');
        });

        Schema::table('tcamp', function (Blueprint $table) {
            // Add new columns to the 'tcamp' table
            $table->boolean('is_membership')->after('lrclass')->nullable();
            $table->string('county')->nullable()->after('is_membership');   //居住地點
            $table->string('subarea')->nullable()->after('county');         //居住地點
            // Change the length of existing string columns to 255
            $table->string('education', 255)->change();
            $table->string('school_or_course', 255)->change();
            $table->string('subject_teaches', 255)->change();
            $table->string('title', 255)->change();
            $table->string('unit', 255)->change();
            $table->string('unit_county', 255)->change();
            $table->string('unit_subarea', 255)->change();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('tcamp', function (Blueprint $table) {
            //
            $table->renameColumn('unit_subarea', 'unit_district');
            $table->dropColumn('county');
            $table->dropColumn('subarea');
            $table->dropColumn('is_membership');
        });
    }
};
