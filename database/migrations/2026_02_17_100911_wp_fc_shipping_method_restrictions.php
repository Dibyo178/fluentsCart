<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Eikhane prefix likhar dorkar nai, Capsule auto prefix bosiye nibe
        Schema::create('wp_fc_shipping_method_restrictions', function (Blueprint $table) {
            $table->id();
            $table->bigInteger('method_id')->unsigned()->default(0)->unique();
            $table->text('allowed_countries')->nullable();
            $table->text('excluded_countries')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('fc_shipping_method_restrictions');
    }
};
