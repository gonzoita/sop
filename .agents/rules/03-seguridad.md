---
trigger: always_on
description: Aislamiento entre clientes, cifrado, auditoría y manejo de datos sensibles
---

# Seguridad

Esta plataforma va a guardar información de clientes de una agencia y a enviar parte de esa
información a proveedores de IA externos. Trátala como datos de terceros desde la primera línea de
código, no "cuando esté en producción".

## 1. Aislamiento entre equipos y clientes

- Toda tabla de negocio lleva `team_id`; todo modelo aplica el global scope (ver
  `.agents/rules/01-laravel.md`).
- Los usuarios con rol `cliente` **no** son miembros del equipo. Su acceso se define por
  asignaciones explícitas (`client_user`, y SOP asignados), y se verifica en la Policy de cada
  recurso.
- Un cliente nunca debe poder ver: la librería de skills, prompts internos, otros clientes, costos
  de IA, ni las ejecuciones que no sean suyas.
- Antes de dar por terminada cualquier pantalla nueva, pregúntate: "¿qué ve aquí un usuario con rol
  cliente?" Si la respuesta es "no lo pensé", no está terminada.

## 2. Secretos y credenciales

- `.env` en `.gitignore`, siempre. Nunca lo subas al repositorio ni lo pegues en el chat.
- `APP_KEY` se genera una vez y no cambia: si cambia, todo lo cifrado queda ilegible.
- Las claves de API que guarde la aplicación (OpenRouter u otros proveedores) se almacenan con el
  cast `encrypted` de Eloquent:

```php
protected function casts(): array
{
    return ['api_key' => 'encrypted'];
}
```

- Nunca escribas una clave de API en logs, en respuestas JSON, ni en mensajes de error. Al mostrarla
  en la interfaz, muestra solo los últimos 4 caracteres.

## 3. Datos hacia proveedores de IA

- Todo lo que se envía a OpenRouter se registra en `ai_generations` con modelo, tokens y costo, pero
  **sin** volcar datos personales innecesarios en el log de la aplicación.
- Cada equipo debe poder ver qué SOP envían datos a IA externa. Es requisito para poder informar a
  los clientes de la agencia, no una función opcional.
- No envíes a un modelo externo campos que el SOP no necesite. Si un bloque `ai_task` solo requiere
  el nombre de la marca y el objetivo de campaña, envía eso, no el registro completo del cliente.

## 4. Auditoría

- `spatie/laravel-activitylog` activo desde la Fase 0, no añadido al final.
- Se registra: creación y publicación de versiones de SOP, inicio y cierre de ejecuciones,
  aprobación y rechazo de salidas de IA, cambios de rol y de permisos, acceso de clientes, y toda
  creación o revocación de credenciales de IA.
- El log de auditoría es de solo lectura desde la aplicación. Nadie lo edita ni lo borra desde la
  interfaz.

## 5. Entradas y archivos

- Toda entrada validada con Form Request. Nunca `$request->all()` directo a un `create()`.
- El contenido de texto enriquecido de los bloques se sanea antes de renderizarse. Si guardas HTML,
  sanéalo al guardar **y** escapa al mostrar.
- Subidas de archivo: lista blanca de extensiones y tipos MIME, límite de tamaño, nombre generado
  por el sistema (nunca el nombre original del usuario), y almacenamiento fuera de `public_html`
  servido a través de una ruta que verifique permisos. Un archivo subido por un cliente no puede ser
  accesible por URL adivinable.

## 6. Autenticación y acceso

- 2FA disponible para todas las cuentas del equipo y **obligatorio** para el rol admin.
- Límite de intentos (`throttle`) en login, recuperación de contraseña y endpoints de IA.
- Sesiones con expiración razonable; cierre de sesión invalida el token.
- Enlaces de invitación al portal de cliente: de un solo uso y con caducidad.

## 7. Derechos sobre los datos

Aunque no tengas certificación formal, la aplicación debe poder responder a lo básico desde el
inicio:

- Exportar todos los datos asociados a un cliente en un archivo.
- Eliminar o anonimizar los datos de un cliente, dejando constancia en el log de auditoría de quién
  lo pidió y cuándo.

No inventes promesas de cumplimiento en la interfaz ni en la documentación. Lo que existe es una
base técnica sólida; la certificación es un proceso aparte (ver `docs/04-seguridad.md`).
