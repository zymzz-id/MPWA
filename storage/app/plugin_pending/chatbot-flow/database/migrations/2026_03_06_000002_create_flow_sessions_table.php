<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateFlowSessionsTable extends Migration
{
    public function up()
    {
        Schema::create('flow_sessions', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('device_id');
            $table->string('phone', 32);
            $table->unsignedBigInteger('flow_id');
            $table->string('current_node_id', 32);
            $table->timestamps();
            $table->foreign('flow_id')->references('id')->on('chatbot_flows')->onDelete('cascade');
            $table->index(['device_id', 'phone']);
        });
    }

    public function down()
    {
        Schema::dropIfExists('flow_sessions');
    }
}
