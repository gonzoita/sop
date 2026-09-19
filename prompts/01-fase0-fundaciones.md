# Prompt 01 — Fase 0: Fundaciones

**Objetivo:** que exista una aplicación con usuarios, equipos, roles, aislamiento de datos y
auditoría funcionando. Sin esto, todo lo demás se construye sobre arena.

**Duración razonable:** 2 a 4 sesiones de trabajo.

**Requisito previo:** Prompt 00 completado y proyecto creado.

---

## Copia desde aquí

```
Fase 0: fundaciones. Lee AGENTS.md, .agents/rules/01-laravel.md, .agents/rules/03-seguridad.md
y docs/02-modelo-de-datos.md.

Trabaja en pasos. Al terminar cada paso, párate, dime qué hiciste en tres líneas y espera mi
visto bueno antes de continuar con el siguiente.

PASO 1 — Equipos y roles
- Modelo Team y la relación con User (si el starter kit ya lo trae, úsalo; no dupliques).
- spatie/laravel-permission configurado con los roles: admin, editor, ejecutor, cliente.
- Seeder que cree el equipo inicial, un usuario admin y los cuatro roles con sus permisos.
- Selector de equipo activo en sesión, con un helper global currentTeamId().

PASO 2 — Aislamiento multi-tenant
- Trait app/Models/Concerns/BelongsToTeam.php con global scope por team_id y relleno
  automático de team_id al crear, tal como está especificado en .agents/rules/01-laravel.md.
- Migración de la tabla clients y su modelo usando el trait.
- Test de feature que cree dos equipos con un cliente cada uno y verifique que el equipo A
  no puede leer, actualizar ni borrar el cliente del equipo B. Este test es obligatorio:
  es el control de seguridad más importante del sistema.

PASO 3 — Auditoría
- spatie/laravel-activitylog instalado y registrando, como mínimo: creación y actualización
  de clientes, cambios de rol y accesos fallidos de autenticación.
- Pantalla de solo lectura del log para el rol admin, paginada y filtrable por fecha y usuario.
  Nadie puede editar ni borrar entradas desde la interfaz.

PASO 4 — Autenticación reforzada
- 2FA disponible para todos y obligatorio para el rol admin (bloquea el acceso al panel de
  admin si no lo tiene activado).
- Throttle en login y recuperación de contraseña.
- APP_DEBUG=false y APP_ENV=production documentados para el despliegue.

PASO 5 — Layout base
- Layout de la aplicación con Inertia + Vue 3 + Tailwind: barra lateral, encabezado con
  selector de equipo y menú de usuario.
- Página de inicio (dashboard) vacía con espacio reservado para: SOPs recientes, ejecuciones
  en curso y consumo de IA del mes.
- Todos los textos en español.

Criterios de aceptación de la fase:
1. Puedo registrarme, activar 2FA e iniciar sesión.
2. Existen los cuatro roles y puedo asignarlos.
3. El test de aislamiento entre equipos pasa.
4. Creo un cliente y aparece en el log de auditoría.
5. La app carga sin errores con APP_DEBUG=false.

Al terminar la fase: actualiza el grafo de Graphify, haz commit, y dime exactamente qué debo
probar yo a mano para validar los cinco criterios.
```

## Hasta aquí

---

## Antes de pasar a la Fase 1

Despliega esto a tu subdominio siguiendo `docs/05-deploy-hostinger.md`. Es deliberado: quieres
descubrir los problemas de despliegue ahora, con cinco tablas, y no en la Fase 3 con el motor de IA
de por medio.

Confirma en el servidor que: la app carga por HTTPS, el cron está configurado, el login funciona y
`storage/` es escribible.
