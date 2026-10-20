<?php

namespace App\Console\Commands;

use App\Enums\RolCodigo;
use App\Models\Rol;
use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules\Password;

class CreateFirstAdministrator extends Command
{
    protected $signature = 'sirmd:crear-administrador';

    protected $description = 'Registra interactivamente la primera cuenta administrativa del SIRMD';

    public function handle(): int
    {
        $role = Rol::where('codigo', RolCodigo::AdministradorTecnico->value)->first();
        if (! $role || ! $role->permisos()->where('codigo', 'plataforma.acceder')->exists()) {
            $this->error('El rol administrador técnico debe existir y tener plataforma.acceder. Revisa el catálogo de permisos.');

            return self::FAILURE;
        }
        if ($role->usuarios()->exists()) {
            $this->error('Ya existe una cuenta administrativa. Este comando solo registra la primera.');

            return self::FAILURE;
        }
        if (! $this->input->isInteractive()) {
            $this->error('Ejecuta este comando en una terminal interactiva.');

            return self::FAILURE;
        }

        $data = [
            'dni' => trim((string) $this->ask('DNI')),
            'nombre' => Str::squish((string) $this->ask('Nombre')),
            'apellidos' => Str::squish((string) $this->ask('Apellidos')),
            'email' => Str::lower(trim((string) $this->ask('Correo electrónico'))),
            'password' => $this->secret('Contraseña (mínimo 12 caracteres, letras y números)', false),
            'password_confirmation' => $this->secret('Confirmar contraseña', false),
        ];
        $validator = Validator::make($data, [
            'dni' => ['required', 'string', 'regex:/^[0-9]{8}$/', 'unique:users,dni'],
            'nombre' => ['required', 'string', 'max:255'],
            'apellidos' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', 'unique:users,email'],
            'password' => ['required', 'string', 'max:72', 'confirmed', Password::min(12)->letters()->numbers()],
        ], [
            'required' => 'El campo :attribute es obligatorio.',
            'unique' => 'El campo :attribute ya está registrado.',
            'dni.regex' => 'El DNI debe contener ocho dígitos.',
            'email.email' => 'El correo electrónico no es válido.',
            'max' => 'El campo :attribute supera la longitud permitida (:max).',
            'password.confirmed' => 'Las contraseñas no coinciden.',
            'password.min' => 'La contraseña debe tener al menos 12 caracteres.',
            'password.letters' => 'La contraseña debe incluir letras.',
            'password.numbers' => 'La contraseña debe incluir números.',
        ]);
        if ($validator->fails()) {
            foreach ($validator->errors()->all() as $error) {
                $this->error($error);
            }

            return self::FAILURE;
        }

        $created = DB::transaction(function () use ($role, $data) {
            $lockedRole = Rol::whereKey($role->id)->lockForUpdate()->firstOrFail();
            if ($lockedRole->usuarios()->exists()) {
                return false;
            }
            $user = User::create(collect($data)->except('password_confirmation')->all());
            $user->roles()->attach($lockedRole);

            return true;
        });

        if (! $created) {
            $this->error('Ya existe una cuenta administrativa. No se creó otra cuenta.');

            return self::FAILURE;
        }
        $this->info('Cuenta administrativa creada. Puedes iniciar sesión con tu DNI. No se concedieron permisos documentales adicionales.');

        return self::SUCCESS;
    }
}
