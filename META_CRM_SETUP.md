# Empresas, Meta y leads

## Accesos

- El usuario `admin@utt.com` creado por `AdminUserSeeder` es administrador global. No pertenece a ninguna empresa y solo entra a `/admin` para crear empresas e invitar usuarios.
- Cada usuario invitado pertenece a una sola empresa. Su rol se asigna en el panel global. El usuario también queda vinculado a la tabla ERP `users` para poder atribuirle futuras actividades del CRM.
- La invitación envía un enlace para establecer contraseña. Hasta que lo complete, el inicio de sesión está bloqueado. Configura `MAIL_MAILER`, el proveedor SMTP y `APP_URL` para que el correo llegue y apunte al dominio correcto. Con `MAIL_MAILER=log`, el enlace solo aparece en el log local.
- Los roles `Administrador` y `Marketing` pueden consultar CRM y Meta. Solo el administrador de la empresa conecta Meta y vincula cuentas y páginas. `Recursos Humanos` accede a pre-nómina. El administrador global no puede consultar datos empresariales.

## Conexión de Meta

1. Crea la empresa y su administrador en `/admin`. El administrador invitado establece su contraseña desde el correo.
2. En Meta for Developers, configura **Facebook Login for Business** para la app de Nexus ERP. Habilita OAuth web, registra exactamente `META_REDIRECT_URI` en **Valid OAuth Redirect URIs** y solicita `ads_read`, `business_management`, `pages_show_list`, `pages_read_engagement` y `leads_retrieval`. Si usas una configuración de Facebook Login for Business, elige **User access token**, coloca su ID en `META_LOGIN_CONFIG_ID` y añade esos permisos a esa configuración. Completa el acceso avanzado y la revisión de Meta cuando la app deba atender a usuarios que no tengan un rol en la app.
3. En `.env`, coloca `META_APP_ID`, `META_APP_SECRET` y `META_REDIRECT_URI`. Para el servidor local: `META_REDIRECT_URI=http://localhost:8000/crm/meta/oauth/callback`. El valor de `APP_URL` debe usar el mismo host. Ejecuta `php artisan config:clear` y `php artisan route:clear` si el proyecto tenía caché de configuración o rutas.
4. Cada administrador de empresa entra a `/crm/campanas`, pulsa **Iniciar sesión con Meta** y autoriza la app. El servidor obtiene un token de usuario de larga duración, lo guarda cifrado y asocia la conexión a ese administrador y su empresa. Si el token expira o se revoca, el administrador debe reconectar.
5. En **Cuentas publicitarias**, el administrador vincula o desvincula las cuentas a las que tiene acceso con su usuario de Meta. La cuenta no tiene que pertenecer al Business Manager elegido. En **Consultar campañas**, selecciona una cuenta vinculada para ver solo sus campañas y métricas del período indicado. El dashboard consulta las cuentas vinculadas para los últimos 30 días.
6. Para importar leads, el administrador elige un Business Manager y vincula la página del negocio que contiene sus formularios. Las páginas y las cuentas publicitarias se administran por separado.

El administrador global no conecta Meta ni puede consultar sus datos. Cada administrador empresarial tiene su propia conexión; los usuarios de Marketing solo leen las cuentas vinculadas a su empresa. La lectura de campañas usa `ads_read`. La importación de formularios requiere `leads_retrieval` y acceso a leads en la página; Meta puede requerir permisos adicionales para datos asociados a anuncios, según [Meta Lead Ads](https://developers.facebook.com/docs/marketing-api/guides/lead-ads/retrieving/v2.8/).

## Persistencia

- La pantalla consulta campañas y métricas directamente a Meta para el rango de fechas elegido. Si falla solo la consulta de métricas, las campañas todavía se muestran. **Guardar campañas visibles** crea una copia de esas campañas y sus métricas en `meta_saved_campaigns`. Si ya existe la campaña para el mismo rango, solo se actualiza cuando cambian sus datos. La tabla ERP `campaigns` no se usa para esta copia.
- Los leads de los formularios de las páginas vinculadas se insertan siempre en `leads`, con empresa, página, formulario y los identificadores de anuncio/campaña cuando Meta los devuelve. Una nueva importación no sobrescribe la prioridad, etapa ni otras ediciones del CRM. Los mensajes de WhatsApp y Messenger no forman parte de este importador.
- `php artisan meta:sync-leads` importa los leads en cualquier momento. La tarea está programada cada 15 minutos; en el servidor debe ejecutarse `php artisan schedule:run` cada minuto (o `php artisan schedule:work` en desarrollo). También hay un botón **Importar leads ahora** para el administrador de la empresa.

La funcionalidad de etiquetas y comentarios sobre leads aún no se ha implementado; la persistencia y la referencia al usuario ERP preparan esa siguiente etapa.
