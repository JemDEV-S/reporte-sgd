<?php

namespace App\Reportes\PendientesDependencia;

use App\Models\Publicacion;
use App\Models\User;
use Illuminate\Support\Facades\Gate;
use Symfony\Component\HttpKernel\Exception\HttpException;

/**
 * Contrato de consulta y autorización compartido (§7). Un solo resolvedor fija,
 * para toda la vida de una petición, el mismo contexto que deben usar resumen,
 * detalle, (futuro) modal y exportación: usuario autorizado, publicación fijada,
 * filtros, alcance de dependencias y permisos de campo.
 *
 * Fijar la publicación aquí (no resolver "vigente" en cada capa) garantiza que,
 * si el scheduler publica otra captura durante la navegación, la pantalla
 * continúa sobre su corte original.
 *
 * Permisos de campo (§7.3):
 *  - datos.ver_institucionales → ámbito de todas las dependencias.
 *  - datos.ver_personales → nombre de destinatario y nombres personales de origen.
 *  - datos.ver_asuntos → asunto (si falta, NO se selecciona ni serializa).
 */
class ContextoReporte
{
    private function __construct(
        public readonly Publicacion $publicacion,
        public readonly FiltrosPendientes $filtros,
        public readonly bool $puedeVerPersonales,
        public readonly bool $puedeVerAsuntos,
        public readonly bool $ambitoInstitucional,
        /** @var array<int, string>|null null = todas (institucional); [] = ninguna */
        public readonly ?array $ambitoDependencias,
    ) {}

    /**
     * Resuelve el contexto desde el usuario y los filtros ya validados. La
     * publicación se pasa explícita (fijada por el controlador): debe estar
     * finalizada (vigente/historica), nunca "construyendo".
     *
     * @throws HttpException 403 si el
     *                       usuario no tiene ningún ámbito de datos.
     */
    public static function resolver(?User $usuario, Publicacion $publicacion, FiltrosPendientes $filtros): self
    {
        abort_unless(
            in_array($publicacion->estado, ['vigente', 'historica'], true),
            404,
            'La publicación solicitada no está disponible.',
        );

        $institucional = Gate::allows('datos.ver_institucionales');

        // Sin ámbito institucional, se resolverían asignaciones explícitas de
        // oficina. La relación usuario_dependencia aún no existe: hasta que se
        // implemente, se deniega el acceso a usuarios sin ámbito institucional.
        $ambitoDependencias = $institucional ? null : self::dependenciasExplicitas($usuario);
        abort_if($ambitoDependencias !== null && $ambitoDependencias === [], 403, 'Sin ámbito de datos autorizado.');

        // Compone los filtros con el alcance resuelto (null = todas).
        $filtrosConAmbito = new FiltrosPendientes(
            prioridad: $filtros->prioridad,
            estadoRecepcion: $filtros->estadoRecepcion,
            bandeja: $filtros->bandeja,
            dependenciasAutorizadas: $ambitoDependencias,
        );

        return new self(
            publicacion: $publicacion,
            filtros: $filtrosConAmbito,
            puedeVerPersonales: Gate::allows('datos.ver_personales'),
            puedeVerAsuntos: Gate::allows('datos.ver_asuntos'),
            ambitoInstitucional: $institucional,
            ambitoDependencias: $ambitoDependencias,
        );
    }

    /**
     * Contexto institucional implícito con todos los campos permitidos. Para
     * llamadas internas, pruebas de motor o escrituras del sistema que NO pasan
     * por la capa HTTP de autorización. No debe usarse para responder a usuarios
     * finales: ahí siempre se resuelve con resolver() y sus Gates.
     */
    public static function institucional(Publicacion $publicacion, FiltrosPendientes $filtros): self
    {
        return new self(
            publicacion: $publicacion,
            filtros: $filtros,
            puedeVerPersonales: true,
            puedeVerAsuntos: true,
            ambitoInstitucional: true,
            ambitoDependencias: null,
        );
    }

    /**
     * ¿El usuario puede ver el detalle de esta dependencia? Institucional ve
     * todas; en otro caso, solo las de su ámbito explícito.
     */
    public function permiteDependencia(string $codigo): bool
    {
        if ($this->ambitoInstitucional) {
            return true;
        }

        return $this->ambitoDependencias !== null
            && in_array($codigo, $this->ambitoDependencias, true);
    }

    /**
     * Asignaciones explícitas de oficina de un usuario. Devuelve [] mientras no
     * exista la relación usuario_dependencia (ningún acceso no institucional).
     *
     * @return array<int, string>
     */
    private static function dependenciasExplicitas(?User $usuario): array
    {
        // Diseñada pero no implementada (§7.3): incorporar la migración/relación
        // usuario_dependencia aquí cuando se habiliten usuarios limitados.
        return [];
    }
}
