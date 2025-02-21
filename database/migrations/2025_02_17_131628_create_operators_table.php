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
        Schema::create('operators', function (Blueprint $table) {
            $table->id();
            $table->integer('operator_id')->unique();
            $table->longText('email');
            $table->longText('password');
            $table->longText('csrf_token_key')->nullable();
            $table->longText('csrf_token_value')->nullable();
            $table->longText('oxy_kratos_session')->nullable();
            $table->longText('xsrf_token')->nullable();
            $table->longText('monta_session')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('operators');
    }
};
