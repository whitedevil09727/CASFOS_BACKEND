<?php
// database/migrations/2024_01_01_000006_add_tour_details_to_tour_links_table.php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddTourDetailsToTourLinksTable extends Migration
{
    public function up()
    {
        Schema::table('tour_links', function (Blueprint $table) {
            $table->string('location')->nullable();
            $table->string('duration')->nullable();
            $table->string('oic')->nullable();
            $table->string('gl')->nullable();
            $table->date('tour_date')->nullable();
        });
    }

    public function down()
    {
        Schema::table('tour_links', function (Blueprint $table) {
            $table->dropColumn(['location', 'duration', 'oic', 'gl', 'tour_date']);
        });
    }
}