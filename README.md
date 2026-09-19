# SOPForge — paquete de contexto para construir con Google Antigravity

Este paquete **no es la aplicación**: es todo el contexto, las reglas y los prompts que Antigravity
necesita para construirla. Lo descomprimes en la carpeta raíz de tu proyecto, abres esa carpeta en
Antigravity, y a partir de ahí trabajas por fases con los prompts de `prompts/`.

El nombre `SOPForge` es un marcador de posición. Si lo cambias, cámbialo en `AGENTS.md`,
`README.md` y en el `.env` cuando exista; el resto de la documentación lo llama "la plataforma".

---

## Qué vas a construir

Una plataforma web donde documentas los SOP de tu agencia de marketing con un constructor de
arrastrar y soltar, y donde esos SOP **se ejecutan**: el usuario llena unos datos de entrada y el
sistema —con IA vía OpenRouter o exportando/importando Markdown para una IA externa— adelanta la
mayor parte del trabajo. Equipo interno más un portal restringido para clientes.

El detalle completo está en `docs/00-vision-y-alcance.md`.

---

## Estructura de este paquete

```
.
├── AGENTS.md                       Contexto maestro. Antigravity lo lee siempre.
├── .agents/rules/                  Reglas de comportamiento del agente
│   ├── 00-stack-y-entorno.md       Stack fijo y restricciones de Hostinger (always on)
│   ├── 01-laravel.md               Convenciones de backend (glob *.php)
│   ├── 02-vue.md                   Convenciones de frontend (glob *.vue)
│   ├── 03-seguridad.md             Multi-tenancy, cifrado, auditoría (always on)
│   ├── 04-produccion-hostinger.md  Cómo tocar producción sin romperla (model decision)
│   └── 05-ahorro-de-tokens.md      Disciplina de contexto y Graphify (always on)
├── docs/
│   ├── 00-vision-y-alcance.md      Qué es, para quién, qué NO es
│   ├── 01-arquitectura.md          Stack adaptado a Hostinger compartido
│   ├── 02-modelo-de-datos.md       Tablas, bloques del SOP, sistema de variables
│   ├── 03-motor-ia.md              OpenRouter, colas, skills, importar/exportar Markdown
│   ├── 04-seguridad.md             Base "compliance-ready" y qué exige auditoría real
│   ├── 05-deploy-hostinger.md      Subdominio, symlinks, cron, despliegue, respaldos
│   └── 06-graphify-y-tokens.md     Instalación y uso de Graphify, disciplina de contexto
├── prompts/
│   ├── 00-bootstrap.md             Primer prompt: verificar entorno y crear el esqueleto
│   ├── 01-fase0-fundaciones.md     Auth, equipos, roles, auditoría, multi-tenancy
│   ├── 02-fase1-constructor.md     Editor drag & drop y versionado de SOP
│   ├── 03-fase2-portal-cliente.md  Rol cliente, onboarding automatizado
│   ├── 04-fase3-motor-ia.md        OpenRouter, skills, aprobación humana
│   ├── 05-fase4-exportadores.md    GPTs, Gems, Proyectos de Claude
│   └── 99-plantilla-de-tarea.md    Plantilla para cualquier tarea nueva
└── sql/
    └── schema-v1.sql               DDL de referencia (MySQL 8)
```

---

## Orden de uso

1. **Descomprime** este paquete en la carpeta vacía del proyecto y ábrela en Antigravity.
2. **Lee `docs/05-deploy-hostinger.md` antes que nada.** Define dónde vive la app en tu
   subdominio y cómo despliegas. Equivocarte ahí te cuesta rehacer trabajo.
3. **Instala Graphify** siguiendo `docs/06-graphify-y-tokens.md`. Hazlo antes de que el
   proyecto crezca: el ahorro de tokens es acumulativo.
4. **Ejecuta `prompts/00-bootstrap.md`** en Antigravity. Ese prompt hace que el agente verifique
   versiones reales (PHP, Laravel, starter kits) antes de escribir nada.
5. **Avanza fase por fase**, en orden, con los prompts `01` a `05`. No saltes fases: cada una
   asume la anterior terminada.
6. Para cualquier cosa fuera del roadmap, usa `prompts/99-plantilla-de-tarea.md`.

---

## Sobre las reglas de Antigravity

Antigravity lee las reglas de `.agents/rules/` (mantiene compatibilidad con `.agent/rules`) y el
`AGENTS.md` de la raíz. Cada archivo de regla tiene un límite de **12.000 caracteres**; todos los de
este paquete están por debajo.

Cada archivo trae al inicio un bloque YAML con su modo de activación
(`always_on`, `glob`, `model_decision`, `manual`). Si tu versión de Antigravity configura la
activación desde el panel del IDE en lugar de leerla del archivo, ese bloque es inofensivo: déjalo y
configura el modo en el panel usando el valor que indica cada archivo.

---

## Advertencia sobre "trabajar en vivo en producción"

Vas a construir esto con un agente, sobre un servidor de producción, para una plataforma que
guardará datos de tus clientes. Eso funciona, pero solo si te proteges de lo que sí puede salir mal:
una migración destructiva, un `.env` sobrescrito, un despliegue a medias.

`docs/05-deploy-hostinger.md` define un flujo mínimo que casi no te frena: respaldo automático de
base de datos antes de cada migración, despliegue por Git en vez de arrastrar archivos por FTP, y un
subdominio de pruebas para lo que toque el esquema. Léelo antes de la primera migración, no después
de la primera pérdida de datos.
