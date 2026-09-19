# 05 — Despliegue y trabajo en producción (Hostinger)

Léelo antes de crear el proyecto. Las decisiones de esta página son caras de cambiar después.

---

## 1. Preparar el subdominio

En hPanel: crea el subdominio (por ejemplo `sop.tudominio.com`) y activa el certificado SSL gratuito
con redirección forzada a HTTPS.

Hostinger crea una carpeta para el subdominio, típicamente
`/home/uXXXXXXX/domains/sop.tudominio.com/public_html`.

**La aplicación no va dentro de `public_html`.** Si va ahí, `/.env` es accesible desde el navegador.
La estructura correcta:

```
/home/uXXXXXXX/domains/sop.tudominio.com/
├── app/              ← el proyecto Laravel completo
│   ├── public/       ← único directorio que debe servirse por web
│   └── .env
└── public_html       ← symlink → app/public
```

Por SSH:

```bash
cd ~/domains/sop.tudominio.com
rm -rf public_html            # solo si está vacío: verifica antes
ln -s app/public public_html
```

**Si tu plan no permite SSH o symlinks**, la alternativa es dejar el contenido de `public/` dentro de
`public_html` y el resto del proyecto en una carpeta hermana, editando `public_html/index.php` para
apuntar a las rutas nuevas de `bootstrap/app.php` y `vendor/autoload.php`. Funciona, pero es más
frágil ante actualizaciones: prefiere el symlink si puedes.

---

## 2. Base de datos

En hPanel, crea la base de datos MySQL y su usuario. Anota host, nombre, usuario y contraseña: van
al `.env`, nunca al repositorio.

El host suele ser `localhost`. Si Hostinger te da un host remoto, úsalo tal cual.

---

## 3. Primer despliegue

```bash
cd ~/domains/sop.tudominio.com/app
git clone <tu-repo> .              # o sube los archivos por SFTP
composer install --no-dev --optimize-autoloader
cp .env.example .env
nano .env                          # credenciales de BD, APP_URL, APP_ENV=production, APP_DEBUG=false
php artisan key:generate
php artisan migrate --force
php artisan storage:link
php artisan config:cache && php artisan route:cache && php artisan view:cache
```

Verifica que `APP_DEBUG=false` en producción. Con `true`, cualquier error muestra rutas, variables
de entorno y fragmentos de código al visitante.

Permisos: `storage/` y `bootstrap/cache/` escribibles (755 suele bastar en Hostinger; no uses 777).

---

## 4. Los assets

No hay Node en el servidor. El ciclo es siempre:

```bash
# en local, dentro de Antigravity
npm run build      # genera public/build
```

y esa carpeta se sube. Decide **una** de estas dos políticas y respétala:

- **A**: `public/build` versionado en Git (quitándolo de `.gitignore`). Despliegue = `git pull`.
  Repositorio más pesado, despliegue más simple.
- **B**: `public/build` ignorado en Git y subido por SFTP en cada despliegue. Repositorio limpio, un
  paso manual más.

La opción A es la que menos errores produce trabajando solo. Lo que no funciona es mezclar las dos:
ahí es donde aparece el "en local se ve bien, en producción se ve roto".

---

## 5. El cron

hPanel → Cron Jobs → uno solo, cada minuto:

```
* * * * * cd /home/uXXXXXXX/domains/sop.tudominio.com/app && php artisan schedule:run >> /dev/null 2>&1
```

Todo lo demás se programa dentro de Laravel (`routes/console.php`): el worker de la cola, la
limpieza de generaciones antiguas, los respaldos. Un único cron, todo el control en el código.

Si tu plan solo permite intervalos de 5 minutos, funciona igual: las tareas de IA tardarán hasta 5
minutos en arrancar, y la interfaz debe decirlo.

Comprueba la ruta de PHP: en CloudLinux / Hostinger compartido suele ser `/opt/alt/php84/usr/bin/php` o `/opt/alt/php83/usr/bin/php` si `php` genérico no apunta a la versión deseada.

---

## 6. Despliegues siguientes

```bash
php artisan down
git pull
composer install --no-dev --optimize-autoloader
# respaldo ANTES de migrar (siguiente sección)
php artisan migrate --force
php artisan config:cache && php artisan route:cache && php artisan view:cache
php artisan up
```

Si algo falla a mitad, deja la aplicación en mantenimiento y resuelve antes de continuar. Una app a
medio desplegar es peor que una app caída: falla de formas impredecibles.

---

## 7. Respaldos — la parte que no se salta

Antes de **cada** migración:

```bash
mkdir -p ~/backups
mysqldump -u USUARIO -p BASE > ~/backups/backup_$(date +%F_%H%M).sql
```

Programa además un respaldo diario dentro del scheduler de Laravel, y **haz una restauración de
prueba** a una base de datos vacía al menos una vez. Un respaldo que nunca restauraste no sabes si
sirve.

Guarda una copia fuera del servidor. Un respaldo que vive en la misma máquina que la base de datos
protege contra tu error, no contra la pérdida del servidor.

---

## 8. Trabajar "en vivo" sin sustos

Vas a construir con un agente directamente contra producción. Es viable si aceptas tres hábitos que
cuestan minutos y evitan días:

**Respaldo antes de migrar.** Sin excepción. Es el único paso que convierte un desastre en un susto.

**Git como mecanismo de despliegue**, no arrastrar archivos. Te da historial y vuelta atrás:
`git revert` y `git pull` te devuelven a un estado conocido. Con FTP no tienes ninguna de las dos
cosas.

**Un subdominio de pruebas** (`dev-sop.tudominio.com`) apuntando a otra carpeta y otra base de
datos, para todo cambio que toque el esquema. No hace falta que sea un entorno perfecto: basta con
que las migraciones destructivas se estrenen ahí.

Y un hábito más, barato: antes de cerrar la sesión de trabajo, verifica que la app carga. Es
frecuente irse tras un cambio que parecía inocuo y descubrir el lunes que el sitio llevaba dos días
mostrando error 500.

---

## 9. Qué revisar cuando algo falla

| Síntoma | Primer sitio a mirar |
|---|---|
| Error 500 sin detalle | `storage/logs/laravel.log` |
| Cambio en `.env` que no surte efecto | `php artisan config:clear` y volver a cachear |
| Las tareas de IA no arrancan | ¿El cron corre? ¿La ruta de PHP es la correcta? ¿Hay filas en `jobs`? |
| Estilos rotos | ¿Subiste `public/build` tras el último `npm run build`? |
| "Permission denied" al escribir | Permisos de `storage/` y `bootstrap/cache/` |
| Ruta que da 404 solo en producción | `php artisan route:clear` y volver a cachear |
