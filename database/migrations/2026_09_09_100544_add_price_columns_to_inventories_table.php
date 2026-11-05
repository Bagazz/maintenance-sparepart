<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddPriceColumnsToInventoriesTable extends Migration
{
    public function up()
    {
        Schema::table('inventories', function (Blueprint $table) {
            // Menyimpan harga beli terbaru untuk referensi saat check-out
            $table->decimal('harga_beli_terakhir', 15, 2)->default(0)->after('stock');
        });
    }

    public function down()
    {
        Schema::table('inventories', function (Blueprint $table) {
            $table->dropColumn('harga_beli_terakhir');
        });
    }
}