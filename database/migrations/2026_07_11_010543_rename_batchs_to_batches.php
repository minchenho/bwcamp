<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * 執行遷移：將舊表名改為新表名
     */
    public function up()
    {
        // ✨ 一行搞定：Schema::rename('舊名字', '新名字');
        Schema::rename('batchs', 'batches');
    }

    /**
     * 還原遷移：萬一後悔了，執行 rollback 時會幫你改回舊名字
     */
    public function down()
    {
        Schema::rename('batches', 'batchs');
    }
};
