<?php

namespace App\Models;

use App\Enums\PermisoCodigo;
use App\Enums\RolCodigo;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Str;

#[Fillable(['dni', 'nombre', 'apellidos', 'email', 'password'])]
#[Hidden(['password', 'remember_token'])]
class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'activo' => 'boolean',
        ];
    }

    public function roles(): BelongsToMany
    {
        return $this->belongsToMany(Rol::class, 'rol_usuario', 'user_id', 'rol_id')->withTimestamps();
    }

    public function tieneRol(RolCodigo|string $codigo): bool
    {
        $valor = $codigo instanceof RolCodigo ? $codigo->value : $codigo;

        return $this->exists && static::query()
            ->whereKey($this->getKey())
            ->where('activo', true)
            ->whereHas('roles', fn (Builder $query) => $query->where('codigo', $valor))
            ->exists();
    }

    public function tienePermiso(PermisoCodigo|string $codigo): bool
    {
        $valor = $codigo instanceof PermisoCodigo ? $codigo->value : $codigo;

        return $this->exists && static::query()
            ->whereKey($this->getKey())
            ->where('activo', true)
            ->whereHas('roles.permisos', fn (Builder $query) => $query->where('codigo', $valor))
            ->exists();
    }

    protected function dni(): Attribute
    {
        return Attribute::make(set: fn (string $value) => trim($value));
    }

    protected function nombre(): Attribute
    {
        return Attribute::make(set: fn (string $value) => Str::squish($value));
    }

    protected function apellidos(): Attribute
    {
        return Attribute::make(set: fn (string $value) => Str::squish($value));
    }

    protected function email(): Attribute
    {
        return Attribute::make(set: fn (string $value) => Str::lower(trim($value)));
    }

    protected function nombreCompleto(): Attribute
    {
        return Attribute::make(get: fn () => trim($this->nombre.' '.$this->apellidos));
    }

    protected function name(): Attribute
    {
        return Attribute::make(get: fn () => $this->nombre_completo);
    }
}
