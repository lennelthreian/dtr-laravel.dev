<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateChatThreadsTable extends Migration
{
    public function up()
    {
        Schema::create('chat_threads', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_one_id');
            $table->unsignedBigInteger('user_two_id');
            $table->timestamps();

            $table->unique(['user_one_id', 'user_two_id']);
            $table->foreign('user_one_id')->references('id')->on('users')->cascadeOnDelete();
            $table->foreign('user_two_id')->references('id')->on('users')->cascadeOnDelete();
        });
    }

    public function down()
    {
        Schema::dropIfExists('chat_threads');
    }
}
