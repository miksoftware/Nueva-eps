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
        Schema::create('consultas', function (Blueprint $table) {
            $table->id();
            $table->string('lote')->index();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('tipo_documento')->default('CC');
            $table->string('numero_documento')->index();
            $table->string('primer_nombre')->nullable();
            $table->string('segundo_nombre')->nullable();
            $table->string('primer_apellido')->nullable();
            $table->string('segundo_apellido')->nullable();
            $table->string('sexo')->nullable();
            $table->string('celular')->nullable();
            $table->string('telefono1')->nullable();
            $table->string('telefono2')->nullable();
            $table->string('correo_electronico')->nullable();
            $table->string('tipo_afiliado')->nullable();
            $table->string('regimen')->nullable();
            $table->string('categoria')->nullable();
            $table->string('ips_primaria')->nullable();
            $table->string('departamento')->nullable();
            $table->string('municipio')->nullable();
            $table->string('estado')->default('pendiente');
            $table->text('respuesta_afiliado')->nullable();
            $table->text('respuesta_paciente')->nullable();
            $table->text('error')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('consultas');
    }
};
