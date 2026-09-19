# 04 — Seguridad y cumplimiento

## Lo primero, con claridad

Elegiste "cumplimiento formal tipo SOC2/GDPR" como nivel objetivo. Vale la pena separar dos cosas
que suelen confundirse, porque la confusión lleva a prometerle a un cliente algo que no tienes:

**Lo que se construye con código** (y sí depende de ti y del agente): cifrado, control de acceso,
aislamiento entre clientes, registro de auditoría, respaldos, exportación y borrado de datos.

**Lo que no se construye con código**: SOC2 Type II requiere una auditoría externa realizada por un
despacho de contadores certificados, sobre varios meses de evidencia de que tus controles operan de
verdad en producción. GDPR formal exige acuerdos de tratamiento de datos (DPA) firmados con cada
proveedor que toque datos de tus clientes —OpenRouter incluido—, un registro de actividades de
tratamiento, bases legales documentadas y, normalmente, asesoría legal.

Ninguna cantidad de buen código te da una certificación. Lo que el código te da es que, el día que
decidas certificarte, el proceso sea viable en vez de una reconstrucción completa.

Hay además una consideración honesta sobre el hosting: **el hosting compartido no es una base
sólida para un producto que apunta a cumplimiento formal con datos de clientes de terceros.**
Compartes máquina con otros inquilinos, no controlas el aislamiento, y no puedes demostrar buena
parte de los controles que un auditor pide. Es perfectamente razonable para construir y validar el
producto. Antes de que datos reales y sensibles de varios clientes vivan ahí de forma permanente,
planifica el salto a un VPS. Anótalo como decisión pendiente, no como detalle.

---

## La base técnica que sí construyes desde la v1

### Aislamiento
`team_id` en toda tabla de negocio, global scope en todo modelo, Policies por recurso, y
verificación explícita de asignación para los usuarios con rol cliente. Es el control más importante
de todos: una fuga entre clientes de una agencia es un incidente que se cuenta a los afectados.

Pruebas automatizadas específicas para esto, no confianza en la revisión manual: un test que cree
dos equipos y verifique que el equipo A no puede leer nada del B.

### Cifrado
- En tránsito: TLS en todo el subdominio. Hostinger ofrece certificado gratuito; actívalo y fuerza
  HTTPS.
- En reposo, a nivel de aplicación: cast `encrypted` de Eloquent para claves de API y para cualquier
  campo sensible que decidas proteger.
- `APP_KEY` se genera una vez, se respalda aparte y **no cambia nunca**. Si cambia, todo lo cifrado
  queda irrecuperable.

### Autenticación
2FA disponible para todo el equipo y obligatorio para admin. Límite de intentos en login y en
recuperación de contraseña. Invitaciones al portal de cliente de un solo uso y con caducidad.

### Auditoría
`spatie/laravel-activitylog` desde la Fase 0. Registra publicaciones de versiones, ejecuciones,
aprobaciones y rechazos de IA, cambios de permisos, accesos de clientes y gestión de credenciales.
Solo lectura desde la interfaz.

Añadirlo al final nunca funciona: el valor del log está en tener historia, y la historia solo se
acumula si existe desde el principio.

### Respaldos
Automatizados, con retención definida y —esto es lo que la gente olvida— **probados**. Un respaldo
que nunca restauraste no es un respaldo, es una carpeta. Haz una restauración de prueba a una base
de datos vacía al menos una vez, y anota cuánto tardó.

### Derechos sobre los datos
Endpoints y proceso para exportar todo lo asociado a un cliente, y para eliminarlo o anonimizarlo,
con constancia en el log de quién lo pidió y cuándo. Tenerlo desde el inicio es barato; añadirlo
sobre un esquema con datos desperdigados es caro.

---

## Datos que salen hacia proveedores de IA

Esto es lo que más te va a preguntar un cliente serio, y con razón: al usar un `ai_task`, parte de
la información de ese cliente sale hacia OpenRouter y hacia el modelo que esté detrás.

Lo que corresponde hacer:

1. **Minimizar.** Envía al modelo solo los campos que el skill necesita, no el registro completo.
2. **Hacerlo visible.** Cada equipo debe poder ver qué SOP mandan datos a IA externa. No es una
   función opcional de reporting: es la información que necesitas para poder responder a un cliente.
3. **Declararlo.** Un aviso de privacidad claro que diga que ciertos datos se procesan con
   proveedores de IA externos. Si tus clientes de agencia tienen a su vez clientes finales cuyos
   datos entran ahí, esto deja de ser un detalle técnico y pasa a ser contractual.
4. **Revisar la política del proveedor** respecto a retención y uso de datos para entrenamiento, y
   configurarla si ofrece opciones.

---

## Lo que no debe entrar a la plataforma

Aunque un SOP lo pida, **no** guardes en estas tablas: números de documento de identidad, datos de
tarjetas o cuentas bancarias, ni credenciales de acceso de terceros (contraseñas de las cuentas
publicitarias del cliente).

Para accesos, el patrón correcto es que el cliente te añada como usuario en su plataforma, no que te
envíe su contraseña. Diseña los SOP de onboarding de esa forma: es más seguro y además es el
procedimiento correcto de todas formas.

---

## Orden de prioridad si el tiempo aprieta

Si tienes que recortar, este es el orden en que estas cosas importan:

1. Aislamiento por `team_id` con pruebas. No es negociable nunca.
2. Secretos fuera del repositorio y claves de API cifradas.
3. Respaldos que sepas restaurar.
4. Log de auditoría.
5. 2FA.
6. Exportación y borrado de datos.
7. Todo lo demás.

Los cuatro primeros antes de que el primer cliente real entre al portal.
