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
        Schema::create('nexus_cargas', function (Blueprint $table) {
            $table->id();
            $table->string('nombre_archivo')->nullable();
            $table->string('archivo_path')->nullable();
            $table->string('hash', 64)->nullable()->index();
            $table->unsignedInteger('total_filas')->default(0);
            $table->unsignedInteger('total_colegios')->default(0);
            $table->unsignedInteger('nombrados')->default(0);
            $table->unsignedInteger('contratados')->default(0);
            $table->unsignedInteger('vacantes')->default(0);
            $table->unsignedInteger('designados')->default(0);
            $table->string('estado', 50)->default('completado')->index();
            $table->text('error_msg')->nullable();
            $table->timestamps();
        });

        Schema::create('nexus_plazas', function (Blueprint $table) {
            $table->id();
            $table->foreignId('carga_id')->constrained('nexus_cargas')->onDelete('cascade');
            $table->string('region')->nullable();
            $table->string('unidad_ejecutora')->nullable();
            $table->string('organo_intermedio')->nullable();
            $table->string('provincia')->nullable();
            $table->string('distrito')->nullable();
            $table->string('tipo_ie')->nullable();
            $table->string('gestion')->nullable();
            $table->string('zona')->nullable();
            $table->string('codmod_ie', 50)->nullable()->index();
            $table->string('dre_ubicacion')->nullable();
            $table->string('ugel_ubicacion')->nullable();
            $table->string('codigo_local', 50)->nullable()->index();
            $table->string('clave8', 50)->nullable();
            $table->string('codigo_rie', 50)->nullable();
            $table->string('nivel_educativo', 100)->nullable()->index();
            $table->string('institucion_educativa', 255)->nullable()->index();
            $table->string('jec', 20)->nullable();
            $table->string('codigo_plaza', 50)->nullable()->index();
            $table->string('codigo_airhsp', 50)->nullable();
            $table->string('especialidad')->nullable();
            $table->string('tipo_trabajador')->nullable();
            $table->string('sub_tipo_trabajador')->nullable();
            $table->string('cargo')->nullable();
            $table->string('situacion_laboral', 100)->nullable()->index();
            $table->text('motivo_vacante')->nullable();
            $table->string('descripcion_escala')->nullable();
            $table->string('escala_historial', 50)->nullable();
            $table->string('categoria_remunerativa', 50)->nullable();
            $table->string('jornada_laboral', 50)->nullable();
            $table->string('estado', 100)->nullable();
            $table->string('fecha_inicio', 50)->nullable();
            $table->string('fecha_termino', 50)->nullable();
            $table->string('tipo_registro', 100)->nullable();
            $table->string('ley', 100)->nullable();
            $table->string('fecha_ingreso_nomb', 50)->nullable();
            $table->string('documento_identidad', 50)->nullable()->index();
            $table->string('codigo_modular', 50)->nullable();
            $table->string('fecha_nacimiento', 50)->nullable();
            $table->string('sexo', 20)->nullable();
            $table->string('apellido_paterno')->nullable();
            $table->string('apellido_materno')->nullable();
            $table->string('nombres')->nullable();
            $table->string('regimen_pensionario')->nullable();
            $table->string('fecha_afiliacion_rp', 50)->nullable();
            $table->string('codigo_essalud', 50)->nullable();
            $table->string('afp', 100)->nullable();
            $table->string('codigo_afp', 50)->nullable();
            $table->string('fecha_afiliacion_afp', 50)->nullable();
            $table->string('fecha_devengue_afp', 50)->nullable();
            $table->text('detalle_estudio')->nullable();
            $table->string('centro_estudios')->nullable();
            $table->string('tipo_estudios')->nullable();
            $table->string('estado_estudios')->nullable();
            $table->string('especialidad_profesional')->nullable();
            $table->string('grado', 100)->nullable();
            $table->string('celular', 50)->nullable();
            $table->string('email', 150)->nullable();
            $table->string('fecha_resolucion', 50)->nullable();
            $table->string('numero_resolucion', 100)->nullable();
            $table->string('desc_superior')->nullable();
            $table->string('numero_contrato_cas', 100)->nullable();
            $table->string('numero_adenda_cas', 100)->nullable();
            $table->string('preventiva', 50)->nullable();
            $table->string('referencia_preventiva')->nullable();
            $table->string('plaza_reemplazo', 100)->nullable();
            $table->json('raw_columns')->nullable();
            $table->unsignedInteger('orden')->default(0);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('nexus_plazas');
        Schema::dropIfExists('nexus_cargas');
    }
};
