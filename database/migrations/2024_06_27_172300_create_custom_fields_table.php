<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

class CreateCustomFieldsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        if (!Schema::hasTable('custom_fields')) {
            Schema::create('custom_fields', function (Blueprint $table) {
                $table->increments('id')->unsigned()->index();
                $table->string('name');
                $table->enum('type', ['text', 'integer']);
                $table->timestamps();
            });
        }

        if (!Schema::hasTable('custom_field_device')) {
            Schema::create('custom_field_device', function (Blueprint $table) {
                $table->increments('id')->unsigned()->index();
                $table->unsignedInteger('device_id')->unsigned()->index();
                $table->unsignedInteger('custom_field_id')->unsigned()->index();
                $table->timestamps();

                $table->foreign('device_id')->references('device_id')->on('devices')->onDelete('cascade');
                $table->foreign('custom_field_id')->references('id')->on('custom_fields')->onDelete('cascade');
            });
        }

        if (!Schema::hasTable('custom_field_values')) {
            Schema::create('custom_field_values', function (Blueprint $table) {
                $table->increments('id')->unsigned()->index();
                $table->unsignedInteger('custom_field_device_id')->index();
                $table->text('value');
                $table->timestamps();

                $table->foreign('custom_field_device_id')->references('id')->on('custom_field_device')->onDelete('cascade');
            });
        }
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('custom_field_values');
        Schema::dropIfExists('custom_field_device');
        Schema::dropIfExists('custom_fields');
    }
}
