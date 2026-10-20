<?php

namespace Tests\Feature;

use App\Enums\RolCodigo;
use App\Explorador\CatalogoExplorador;
use App\Explorador\ConsultaExplorador;
use App\Explorador\MotorExplorador;
use App\Models\ConsultaGuardada;
use App\Models\Publicacion;
use App\Models\Rol;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Database\Seeders\PublicacionSinteticaSeeder;
use Tests\DatabaseTestCase;

class ExploradorTest extends DatabaseTestCase
{
    public function test_sin_agrupaciones_el_total_coincide_con_el_universo_de_pendientes(): void
    {
        $this->sembrar();
        $publicacion = Publicacion::vigente();

        $resultado = (new MotorExplorador($publicacion))->ejecutar(new ConsultaExplorador);

        $esperado = $publicacion->destinos()
            ->where('tipo_destino', '01')
            ->where('activo', true)
            ->whereIn('estado_recepcion', ['0', '1'])
            ->count();

        $this->assertSame($esperado, $resultado->total);
    }

    public function test_agrupar_por_bandeja_reparte_el_total_sin_perder_destinos(): void
    {
        $this->sembrar();
        $publicacion = Publicacion::vigente();

        $consulta = ConsultaExplorador::desde(['agrupaciones' => ['bandeja']]);
        $resultado = (new MotorExplorador($publicacion))->ejecutar($consulta);

        // La suma de los grupos iguala el total del universo (partición exacta).
        $this->assertSame($resultado->total, (int) $resultado->filas->sum('cantidad'));
        $this->assertSame(['Bandeja'], $resultado->columnas);
    }

    public function test_el_explorador_descarta_agrupaciones_y_filtros_no_permitidos(): void
    {
        $consulta = ConsultaExplorador::desde([
            'agrupaciones' => ['bandeja', 'inventada', 'destinatario_empleado'],
            'filtros' => ['estado' => '9', 'prioridad' => '2', 'columna_libre' => 'x'],
        ]);

        // Solo sobrevive la agrupación válida y el filtro con valor de la lista cerrada.
        $this->assertSame(['bandeja'], $consulta->agrupaciones);
        $this->assertSame(['prioridad' => '2'], $consulta->filtros);
    }

    public function test_las_etiquetas_de_prioridad_mapean_cada_codigo_a_su_nombre(): void
    {
        $campo = CatalogoExplorador::campo('prioridad');

        // Las claves de código deben conservarse: 1 normal, 2 urgente, 3 muy urgente.
        $this->assertSame('Normal', $campo->etiquetaValor('1'));
        $this->assertSame('Urgente', $campo->etiquetaValor('2'));
        $this->assertSame('Muy urgente', $campo->etiquetaValor('3'));
    }

    public function test_un_filtro_de_prioridad_muy_urgente_sobrevive_la_validacion(): void
    {
        // El valor '3' pertenece a la lista cerrada y no debe descartarse.
        $consulta = ConsultaExplorador::desde(['filtros' => ['prioridad' => '3']]);

        $this->assertSame(['prioridad' => '3'], $consulta->filtros);
    }

    public function test_un_filtro_de_dependencia_acota_el_universo(): void
    {
        $this->sembrar();
        $publicacion = Publicacion::vigente();

        $consulta = ConsultaExplorador::desde(['dependencia' => '00021']);
        $resultado = (new MotorExplorador($publicacion))->ejecutar($consulta);

        // Coincide con el total propio de Abastecimiento en el reporte (662 sintético).
        $this->assertSame(662, $resultado->total);
    }

    public function test_guardar_una_consulta_crea_una_version_reproducible(): void
    {
        $this->sembrar();
        $analista = $this->usuarioConRol(RolCodigo::AnalistaInstitucional);

        $this->actingAs($analista)
            ->post(route('explorador.guardar', ['agrupaciones' => ['estado', 'prioridad']]), [
                'nombre' => 'Pendientes por estado y prioridad',
                'visibilidad' => 'privada',
            ])
            ->assertRedirect();

        $guardada = ConsultaGuardada::firstWhere('nombre', 'Pendientes por estado y prioridad');
        $this->assertNotNull($guardada);
        $version = $guardada->versionVigente();
        $this->assertSame(1, $version->numero);
        $this->assertSame(['estado', 'prioridad'], $version->aConsultaExplorador()->agrupaciones);
    }

    public function test_el_explorador_exige_el_permiso_de_usarlo(): void
    {
        $this->sembrar();
        // El administrador técnico accede a la plataforma pero no tiene explorador.usar.
        $adminTecnico = $this->usuarioConRol(RolCodigo::AdministradorTecnico);

        $this->actingAs($adminTecnico)
            ->get(route('explorador.index'))
            ->assertForbidden();
    }

    public function test_el_explorador_se_renderiza_para_un_analista(): void
    {
        $this->sembrar();
        $analista = $this->usuarioConRol(RolCodigo::AnalistaInstitucional);

        $this->actingAs($analista)
            ->get(route('explorador.index'))
            ->assertOk()
            ->assertSee('Explorador de datos')
            ->assertSee('Agrupar por');
    }

    public function test_no_se_puede_cargar_la_consulta_privada_de_otro_usuario(): void
    {
        $this->sembrar();
        $autor = $this->usuarioConRol(RolCodigo::AnalistaInstitucional);
        $otro = $this->usuarioConRol(RolCodigo::AnalistaInstitucional);

        $consulta = ConsultaGuardada::create([
            'creado_por' => $autor->id,
            'nombre' => 'Privada del autor',
            'reporte' => 'pendientes_dependencia',
            'visibilidad' => 'privada',
        ]);

        $this->actingAs($otro)
            ->get(route('explorador.cargar', $consulta))
            ->assertForbidden();
    }

    private function sembrar(): void
    {
        $this->seed(PublicacionSinteticaSeeder::class);
    }

    private function usuarioConRol(RolCodigo $codigo): User
    {
        $this->seed(DatabaseSeeder::class);
        $user = User::factory()->create();
        $user->roles()->attach(Rol::where('codigo', $codigo->value)->sole());

        return $user;
    }
}
