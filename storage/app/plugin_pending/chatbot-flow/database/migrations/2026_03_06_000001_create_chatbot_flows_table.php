<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateChatbotFlowsTable extends Migration
{
    public function up()
    {
        Schema::create('chatbot_flows', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_id');
            $table->unsignedBigInteger('device_id');
            $table->string('name');
            $table->string('keyword');
            $table->enum('type_keyword', ['Equal', 'Contain'])->default('Equal');
            $table->enum('reply_when', ['All', 'Group', 'Personal'])->default('All');
            $table->enum('status', ['active', 'inactive'])->default('active');
            $table->json('flow_data');
            $table->tinyInteger('is_read')->default(0);
            $table->tinyInteger('is_typing')->default(0);
            $table->tinyInteger('is_quoted')->default(0);
            $table->integer('delay')->default(0);
            $table->timestamps();
            $table->foreign('user_id')->references('id')->on('users')->onDelete('cascade');
            $table->foreign('device_id')->references('id')->on('devices')->onDelete('cascade');
        });
    }

    public function down()
    {
        Schema::dropIfExists('chatbot_flows');
    }
}
