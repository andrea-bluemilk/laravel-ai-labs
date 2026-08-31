<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('checkins', function (Blueprint $table) {
            $table->increments('id');
            $table->unsignedInteger('security_guard_id');
            $table->string('status', 25);
            $table->text('response_text')->nullable();
            $table->timestamp('called_at')->nullable();
            $table->timestamps();

            $table->foreign('security_guard_id')->references('id')->on('security_guards')->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('checkins');
    }
};
