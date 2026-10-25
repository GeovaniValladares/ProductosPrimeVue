# Vue + Laravel + SQLite

Documentación del proyecto: [guía técnica](DOCUMENTACION.md), [versión visual imprimible](DOCUMENTACION.html) y [PDF](DOCUMENTACION.pdf).

Se conserva Vue 3, Vue Router, Axios, PrimeVue y el diseño del login/dashboard. El backend independiente está en `backend/` y usa Laravel 13, Sanctum, SQLite, Google2FA y Tinker.

## Arranque local

Requisitos: PHP 8.4.1 o superior para las dependencias instaladas, con PDO SQLite, Composer y Node compatible con `package.json`.

```powershell
npm.cmd install
Copy-Item .env.example .env
cd backend
composer install
Copy-Item .env.example .env
php artisan key:generate
if (!(Test-Path database/database.sqlite)) { New-Item database/database.sqlite -ItemType File }
php artisan migrate
php artisan app:create-admin
php artisan serve --host=127.0.0.1 --port=8000
```

En otra terminal, desde la raíz:

```powershell
npm.cmd run dev
```

Abrir http://localhost:5173. Vite redirige `/api` hacia Laravel. No hay contraseñas predeterminadas. `app:create-admin` solicita nombre, correo y contraseña de forma interactiva. La contraseña no se muestra. Alternativamente el seeder acepta `ADMIN_EMAIL` y `ADMIN_PASSWORD` desde `backend/.env`.

## Acceso y permisos

- Superadmin: correo y contraseña → dashboard directo por defecto. Si activa voluntariamente Authenticator, debe confirmar su código para entrar.
- Cada usuario, incluido el superadmin, puede activar Google Authenticator desde **Mi cuenta / Seguridad**: validar su contraseña, escanear el QR con la app y confirmar el código de seis dígitos. El QR se genera localmente en Vue. También se muestra una clave manual como alternativa. Solo entonces se solicita el código al iniciar sesión. Desde esa misma pantalla se puede desactivar con contraseña y código actual. No se envían códigos por correo.
- El superadmin crea usuarios, productos y contenidos desde Vue. Los usuarios normales consultan productos y contenidos publicados; no acceden al CRUD de usuarios ni modifican datos.
- Sanctum emite tokens de 8 horas. Salir revoca el token. El servidor verifica los permisos en cada operación. Los códigos TOTP no pueden reutilizarse, los desafíos expiran y hay límites de intentos. Las claves TOTP se cifran en SQLite.
- Para recuperar una cuenta tras perder Authenticator, un operador con acceso al servidor puede ejecutar `php artisan app:reset-authenticator correo@example.com`. Revoca tokens y desactiva el segundo factor para permitir el acceso con contraseña. Proteger este acceso operativo.

## Funcionalidad Vue

Productos: crear, listar, buscar, editar y eliminar; precio y existencias validados. Usuarios: CRUD con roles y contraseña opcional al editar. Gestor de contenido básico: título, texto plano y estado publicado/borrador; el texto se muestra escapado, sin HTML ejecutable.

PDF (jsPDF + AutoTable) y CSV se generan en el navegador con todos los resultados de la búsqueda, no solo la página visible. El CSV preserva acentos y escapa valores que podrían ejecutar fórmulas. El gráfico usa TanStack Charts 1.1 y su adaptador Vue; muestra los diez productos con mayor existencia entre los resultados filtrados, con totales de unidades y valor del inventario para todos los resultados. Se actualiza al buscar y después de cada cambio del CRUD.

Para agregar ocho productos y cuentas de demostración sin sobrescribir registros existentes: `php artisan db:seed --class=DemoSeeder --no-interaction` desde `backend`. El seeder solo funciona en local/testing. Crea `admin.prueba@example.com` con contraseña `Admin2026!`, y `ana.prueba@example.com`, `carlos.prueba@example.com` y `maria.prueba@example.com` con contraseña `Prueba2026!`. Authenticator queda desactivado por defecto.

## Pruebas y TDD

Se escribieron primero las pruebas de API, se ejecutaron con fallos por rutas/modelos faltantes y después se implementó el backend hasta pasarlas. La base de pruebas SQLite es en memoria, aislada de la base local.

```powershell
cd backend
php artisan test
# Formato PHP
php vendor/laravel/pint/builds/pint
cd ..
npm.cmd run test:unit -- --run
npm.cmd run build
```

Las pruebas cubren acceso directo del superadmin y del usuario sin segundo factor, revocación, desafío TOTP para cuentas con Authenticator activado, código inválido, desafío de un solo uso, autorización, validación de precios, CRUD y borradores privados.

## Laravel Tinker

```powershell
cd backend
php artisan tinker
```

```php
App\Models\User::select('id', 'name', 'email', 'role')->get();
App\Models\Producto::create(['nombre' => 'Teclado', 'precio' => 25, 'stock' => 10]);
App\Models\Contenido::where('publicado', true)->get();
```

## API

`POST /api/login`, `POST /api/auth/verify`, `GET /api/user`, `POST /api/logout`.

Configuración voluntaria del segundo factor: `GET /api/auth/authenticator`, `POST /api/auth/authenticator/setup`, `POST /api/auth/authenticator/confirm`, `DELETE /api/auth/authenticator`.

`/api/usuarios`, `/api/productos`, `/api/contenidos` aceptan GET (lista), POST (crear), GET /{id}, PUT /{id} y DELETE /{id}. Todas las rutas de recursos requieren `Authorization: Bearer <token>`.

Para despliegue: configurar HTTPS, `APP_DEBUG=false`, mantener privada `APP_KEY` y la base SQLite, dar permisos de escritura a storage/cache y SQLite, y configurar `FRONTEND_URL` si Vue se sirve desde otro origen. El proxy de Vite solo aplica al desarrollo; en producción publicar `/api` hacia Laravel o configurar `VITE_API_URL` antes del build.
# ProductosPrimeVue
