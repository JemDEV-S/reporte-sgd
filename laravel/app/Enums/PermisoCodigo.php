<?php

namespace App\Enums;

enum PermisoCodigo: string
{
    case AccederPlataforma = 'plataforma.acceder';
    case GestionarUsuarios = 'usuarios.gestionar';
    case GestionarRoles = 'roles.gestionar';
    case GestionarFuentes = 'fuentes.gestionar';
    case VerAnalisis = 'analisis.ver';
    case VerReportes = 'reportes.ver';
    case EjecutarReportes = 'reportes.ejecutar';
    case ExportarReportes = 'reportes.exportar';
    case UsarExplorador = 'explorador.usar';
    case VerInformes = 'informes.ver';
    case GenerarInformes = 'informes.generar';
    case AprobarInformes = 'informes.aprobar';
    case VerDatosInstitucionales = 'datos.ver_institucionales';
    case VerDatosPersonales = 'datos.ver_personales';
    case VerAsuntos = 'datos.ver_asuntos';

    public function descripcion(): string
    {
        return match ($this) {
            self::AccederPlataforma => 'Acceder a la plataforma.',
            self::GestionarUsuarios => 'Administrar las cuentas de la plataforma.',
            self::GestionarRoles => 'Administrar roles y sus asignaciones de permisos.',
            self::GestionarFuentes => 'Administrar las fuentes e integración de datos.',
            self::VerAnalisis => 'Consultar el centro de análisis.',
            self::VerReportes => 'Consultar el catálogo de reportes.',
            self::EjecutarReportes => 'Ejecutar reportes dentro del alcance autorizado.',
            self::ExportarReportes => 'Exportar resultados dentro del alcance autorizado.',
            self::UsarExplorador => 'Utilizar el explorador de datos.',
            self::VerInformes => 'Consultar informes dentro del alcance autorizado.',
            self::GenerarInformes => 'Generar borradores de informes.',
            self::AprobarInformes => 'Aprobar informes revisados.',
            self::VerDatosInstitucionales => 'Consultar datos de todas las dependencias de la institución.',
            self::VerDatosPersonales => 'Visualizar datos personales documentales autorizados.',
            self::VerAsuntos => 'Visualizar asuntos documentales autorizados.',
        };
    }
}
