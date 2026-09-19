---
trigger: always_on
description: Restricciones no negociables del stack y del hosting compartido de Hostinger
---

# Restricciones duras

Antes de proponer cualquier tecnología, librería o patrón, verifica que cumpla estas restricciones.
Si no las cumple, no la propongas: dilo y ofrece la alternativa que sí corre en este entorno.

## Prohibido en este proyecto

- **Redis** en cualquier forma (colas, caché, sesiones, locks). No existe en el servidor.
- **Laravel Horizon**, `supervisor`, workers permanentes o cualquier proceso que deba quedar vivo.
- **PostgreSQL**. La base es MySQL 8. Nada de `jsonb`, `ILIKE`, arrays nativos ni `RETURNING`.
- **Llamadas HTTP lentas dentro de una petición web.** Toda llamada a OpenRouter o a cualquier API
  externa va dentro de un Job en cola. `max_execution_time` en el servidor es de 60–120 s.
- **Compilar assets en el servidor.** No hay Node/npm. `npm run build` corre en local y se sube
  `public/build` compilado.
- **Instalar extensiones de PHP o paquetes del sistema.** No hay acceso root.
- **WebSockets persistentes** (Reverb, Echo Server, Pusher self-hosted). Para actualizaciones en
  vivo usa polling ligero desde Vue.
- **`migrate:fresh`, `migrate:refresh`, `db:wipe`** contra producción. Jamás.

## Obligatorio

- MySQL 8 con columnas `JSON` para los bloques del SOP y los metadatos.
- Cola con driver `database`, avanzada por el scheduler de Laravel vía un único cron.
- Caché y sesiones en `database` o `file`.
- PHP 8.2 como mínimo. Comprueba la versión real del servidor antes de usar sintaxis más nueva.
- Todos los textos de interfaz en español; el código en inglés.

## Verificar, no asumir

Las versiones cambian. Antes de crear el proyecto o instalar paquetes, comprueba cuál es la versión
estable vigente de Laravel y qué starter kits oficiales existen hoy. No escribas dependencias de
memoria ni copies un `composer.json` de un ejemplo antiguo.

Lo mismo aplica a nombres de paquetes de terceros: confirma el nombre y la versión compatible antes
de añadirlos.
