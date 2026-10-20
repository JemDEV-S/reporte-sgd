<?php

namespace App\Enums;

enum RolCodigo: string
{
    case AdministradorTecnico = 'administrador_tecnico';
    case AnalistaInstitucional = 'analista_institucional';
    case UsuarioDependencia = 'usuario_dependencia';

    public function nombre(): string
    {
        return match ($this) {
            self::AdministradorTecnico => 'Administrador técnico',
            self::AnalistaInstitucional => 'Analista institucional',
            self::UsuarioDependencia => 'Usuario por dependencia',
        };
    }

    public function descripcion(): string
    {
        return match ($this) {
            self::AdministradorTecnico => 'Administra cuentas, roles y fuentes. No recibe acceso documental automáticamente.',
            self::AnalistaInstitucional => 'Consulta y analiza datos institucionales, y genera borradores de informes.',
            self::UsuarioDependencia => 'Consulta y exporta datos únicamente de sus dependencias autorizadas.',
        };
    }

    /** @return list<PermisoCodigo> */
    public function permisosIniciales(): array
    {
        return match ($this) {
            self::AdministradorTecnico => [
                PermisoCodigo::AccederPlataforma,
                PermisoCodigo::GestionarUsuarios,
                PermisoCodigo::GestionarRoles,
                PermisoCodigo::GestionarFuentes,
            ],
            self::AnalistaInstitucional => [
                PermisoCodigo::AccederPlataforma,
                PermisoCodigo::VerAnalisis,
                PermisoCodigo::VerReportes,
                PermisoCodigo::EjecutarReportes,
                PermisoCodigo::ExportarReportes,
                PermisoCodigo::UsarExplorador,
                PermisoCodigo::VerInformes,
                PermisoCodigo::GenerarInformes,
                PermisoCodigo::VerDatosInstitucionales,
            ],
            self::UsuarioDependencia => [
                PermisoCodigo::AccederPlataforma,
                PermisoCodigo::VerAnalisis,
                PermisoCodigo::VerReportes,
                PermisoCodigo::EjecutarReportes,
                PermisoCodigo::ExportarReportes,
                PermisoCodigo::UsarExplorador,
                PermisoCodigo::VerInformes,
            ],
        };
    }
}
