---
trigger: model_decision
description: Leer antes de desplegar, migrar, tocar el .env o ejecutar comandos en el servidor de Hostinger
---

# Trabajar contra producción sin romperla

El usuario desarrolla contra su servidor de Hostinger en vivo. No hay red de seguridad automática.
Estas reglas existen para que un error sea reversible.

## Antes de cualquier migración

1. **Respaldo de la base de datos primero.** Sin respaldo confirmado, no se ejecuta la migración.
   Desde SSH:
   `mysqldump -u USUARIO -p BASE > ~/backups/backup_$(date +%F_%H%M).sql`
2. Revisa qué hace la migración. Si contiene `dropColumn`, `dropTable`, `renameColumn` o cambios de
   tipo sobre una tabla con datos, **avísalo explícitamente al usuario antes de ejecutarla** y
   espera confirmación.
3. Ejecuta `php artisan migrate --pretend` para ver el SQL antes de aplicarlo de verdad.

## Comandos prohibidos contra producción

`migrate:fresh`, `migrate:refresh`, `migrate:reset`, `db:wipe`, `rm -rf` sobre carpetas del
proyecto, y cualquier `DELETE` o `TRUNCATE` sin `WHERE` acotado.

Si crees que la situación exige uno de estos, **para y pregunta**. No lo ejecutes aunque parezca la
única salida.

## Despliegue

El orden importa. Un despliegue a medias deja la aplicación caída:

```
php artisan down                 # modo mantenimiento
git pull                         # o subida de archivos
composer install --no-dev --optimize-autoloader
php artisan migrate --force      # solo después del respaldo
php artisan config:cache
php artisan route:cache
php artisan view:cache
php artisan up
```

Los assets (`public/build`) se compilan en local con `npm run build` y se suben ya compilados. No
intentes compilar en el servidor.

Si algo falla a mitad, **no sigas adelante**: deja la aplicación en mantenimiento, dilo con
claridad y explica qué paso falló.

## El archivo .env

- Vive solo en el servidor. Nunca en el repositorio, nunca pegado en el chat.
- Antes de modificarlo, cópialo: `cp .env .env.bak_$(date +%F)`.
- Tras cambiarlo, siempre `php artisan config:clear` y luego `config:cache`. Un `.env` cambiado sin
  limpiar la caché de configuración no surte efecto y produce errores desconcertantes.

## Caché

En hosting compartido, `config:cache` y `route:cache` son la diferencia entre una app aceptable y
una lenta. Pero recuerda: con la configuración cacheada, `env()` fuera de los archivos de `config/`
devuelve `null`. Lee siempre vía `config('...')`.

## Cron

Un solo cron en hPanel, cada minuto:

```
* * * * * cd /home/USUARIO/domains/SUBDOMINIO/app && php artisan schedule:run >> /dev/null 2>&1
```

Todo lo demás (procesar la cola, limpiar generaciones antiguas, respaldos) se programa dentro de
Laravel. Si el plan no permite intervalos de un minuto, ajusta a cinco y documenta que las tareas de
IA tardarán hasta ese tiempo en arrancar.

## Permisos de archivos

`storage/` y `bootstrap/cache/` deben ser escribibles (755 en hosting compartido de Hostinger suele
bastar; no uses 777). Si hay errores de permisos al escribir logs o caché, es lo primero a revisar.

## Cuando el usuario pida "rápido, directo a producción"

Está bien avanzar rápido, pero el respaldo antes de migrar no es negociable: es el único paso que
convierte un error grave en un susto. Cuesta segundos. Hazlo y sigue.
