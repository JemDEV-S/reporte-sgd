<?php

namespace Tests\Feature;

use App\Models\Rol;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Http\Middleware\PreventRequestForgery;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\RateLimiter;
use Tests\DatabaseTestCase;

class LoginTest extends DatabaseTestCase
{
    public function test_login_y_logout_exigen_csrf(): void
    {
        $this->app->bind(PreventRequestForgery::class, fn ($app) => new class($app, $app['encrypter']) extends PreventRequestForgery
        {
            protected function runningUnitTests()
            {
                return false;
            }
        });
        $this->post('/login', ['dni' => '00123456', 'password' => 'prueba'])->assertStatus(419);
        $this->actingAs($this->account())->post('/logout')->assertStatus(419);
        $this->assertAuthenticated();
    }

    public function test_ingreso_limpia_fallos_y_solo_consulta_base_propia(): void
    {
        $this->account();
        $connections = [];
        DB::listen(function ($query) use (&$connections) {
            $connections[] = $query->connectionName;
        });
        $this->login('incorrecta');
        $this->login()->assertRedirect('/');
        $key = 'login:'.hash('sha256', '00123456|127.0.0.1');
        $this->assertSame(0, RateLimiter::attempts($key));
        $this->assertNotEmpty($connections);
        $this->assertSame([DB::getDefaultConnection()], array_values(array_unique($connections)));
    }

    private function account(string $role = 'analista_institucional'): User
    {
        $this->seed(DatabaseSeeder::class);
        $user = User::factory()->create(['dni' => '00123456', 'password' => 'ClavePrueba123']);
        $user->roles()->attach(Rol::where('codigo', $role)->sole());

        return $user;
    }

    private function login(string $password = 'ClavePrueba123')
    {
        return $this->from('/login')->post('/login', ['dni' => '00123456', 'password' => $password]);
    }

    public function test_login_publico_y_validacion_sin_exponer_password(): void
    {
        $this->get('/login')->assertOk()->assertSee('Inicia sesión')->assertSee('autocomplete="current-password"', false);
        $this->from('/login')->post('/login', ['dni' => 'abc', 'password' => 'secreto'])->assertSessionHasErrors('dni')->assertSessionMissing('_old_input.password');
        $this->post('/login', [])->assertSessionHasErrors(['dni', 'password']);
        $this->assertGuest();
    }

    public function test_ingreso_valido_regenera_sesion_y_muestra_areas(): void
    {
        $user = $this->account();
        $this->withSession(['probe' => true]);
        $before = session()->getId();
        $this->login()->assertRedirect('/');
        $this->assertAuthenticatedAs($user);
        $this->assertNotSame($before, session()->getId());
        $this->get('/')->assertOk()->assertSee('Catálogo de reportes')->assertSee(route('platform.area', 'reportes'))->assertHeader('Cache-Control', 'no-store, private');
        $this->get('/areas/analisis')->assertOk()->assertSee('En preparación');
        $this->get('/login')->assertRedirect('/');
    }

    public function test_credenciales_incorrectas_inactivas_y_sin_permiso_tienen_mismo_error(): void
    {
        $message = 'No se pudo iniciar sesión con las credenciales proporcionadas.';
        $this->login()->assertSessionHasErrors(['dni' => $message]);
        $user = $this->account();
        $this->login('incorrecta')->assertSessionHasErrors(['dni' => $message]);
        $user->forceFill(['activo' => false])->save();
        $this->login()->assertSessionHasErrors(['dni' => $message]);
        $user->forceFill(['activo' => true])->save();
        $user->roles()->detach();
        $this->login()->assertSessionHasErrors(['dni' => $message]);
        $this->assertGuest();
    }

    public function test_cinco_fallos_bloquean_temporalmente_incluso_clave_correcta(): void
    {
        $this->account();
        for ($i = 0; $i < 5; $i++) {
            $this->login('incorrecta')->assertSessionHasErrors('dni');
        }
        $this->login()->assertSessionHasErrors('dni');
        $this->assertStringContainsString('Demasiados intentos', session('errors')->first('dni'));
        $this->assertGuest();
        $this->travel(61)->seconds();
        $this->login()->assertRedirect('/');
        $this->assertAuthenticated();
    }

    public function test_logout_invalida_sesion_y_csrf_y_no_acepta_get(): void
    {
        $this->actingAs($this->account())->withSession(['probe' => true, '_token' => 'old-token']);
        $before = session()->getId();
        $this->get('/logout')->assertStatus(405);
        $this->post('/logout')->assertRedirect('/login')->assertSessionMissing('probe');
        $this->assertGuest();
        $this->assertNotSame($before, session()->getId());
        $this->assertNotSame('old-token', session()->token());
        $this->get('/')->assertRedirect('/login');
    }

    public function test_rutas_protegidas_y_sin_registro_publico(): void
    {
        $this->get('/')->assertRedirect('/login');
        $this->get('/areas/reportes')->assertRedirect('/login');
        $this->get('/register')->assertNotFound();
        $this->post('/register')->assertNotFound();
        $this->actingAs(User::factory()->create())->get('/')->assertRedirect('/login');
        $this->assertGuest();
    }

    public function test_desactivacion_cierra_sesion_existente(): void
    {
        $user = $this->account();
        $this->login();
        DB::table('users')->where('id', $user->id)->update(['activo' => false]);
        $this->get('/')->assertRedirect('/login');
        $this->assertGuest();
    }

    public function test_revocacion_de_acceso_cierra_sesion_existente(): void
    {
        $this->account();
        $this->login();
        $role = Rol::where('codigo', 'analista_institucional')->sole();
        $role->permisos()->detach($role->permisos()->where('codigo', 'plataforma.acceder')->sole());
        $this->get('/')->assertRedirect('/login');
        $this->assertGuest();
    }

    public function test_revocacion_area_restringe_url_y_navegacion(): void
    {
        $this->account();
        $this->login();
        $role = Rol::where('codigo', 'analista_institucional')->sole();
        $role->permisos()->detach($role->permisos()->where('codigo', 'reportes.ver')->sole());
        $this->get('/areas/reportes')->assertForbidden();
        $this->get('/')->assertOk()->assertDontSee(route('platform.area', 'reportes'));
    }

    public function test_administrador_no_tiene_enlaces_ni_acceso_documental(): void
    {
        $this->actingAs($this->account('administrador_tecnico'))->get('/')->assertOk()->assertSee('Administración técnica')->assertDontSee(route('platform.area', 'reportes'));
        foreach (array_keys(config('platform.areas')) as $area) {
            $this->get('/areas/'.$area)->assertForbidden();
        }
        $this->get('/areas/desconocida')->assertNotFound();
    }
}
