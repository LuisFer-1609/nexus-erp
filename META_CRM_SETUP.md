# Empresas, Meta y leads

## Accesos

- El usuario `admin@utt.com` creado por `AdminUserSeeder` es administrador global. No pertenece a ninguna empresa y solo entra a `/admin` para crear empresas e invitar usuarios.
- Cada usuario invitado pertenece a una sola empresa. Su rol se asigna en el panel global. El usuario también queda vinculado a la tabla ERP `users` para poder atribuirle futuras actividades del CRM.
- La invitación envía un enlace para establecer contraseña. Hasta que lo complete, el inicio de sesión está bloqueado. Configura `MAIL_MAILER`, el proveedor SMTP y `APP_URL` para que el correo llegue y apunte al dominio correcto. Con `MAIL_MAILER=log`, el enlace solo aparece en el log local.
- Los roles `Administrador` y `Marketing` pueden consultar CRM y Meta. Solo el administrador de la empresa conecta Meta y vincula cuentas y páginas. `Recursos Humanos` accede a pre-nómina. El administrador global no puede consultar datos empresariales.

## Conexión de Meta

1. Crea la empresa y su administrador en `/admin`. El administrador invitado establece su contraseña desde el correo.
2. El administrador de la empresa entra a `/crm/campanas` y pega su token de Meta. Se almacena cifrado y asociado a esa empresa. Como alternativa para la integración local, `php artisan meta:connect-company ID` asigna el `META_ACCESS_TOKEN` del servidor a la empresa indicada. Un mismo token no puede asignarse a dos empresas.
3. Vincula la cuenta publicitaria que quieres consultar. Para importar leads, vincula también la página que contiene sus formularios.

El token debe tener acceso a los activos de esa empresa. La lectura de campañas usa `ads_read`; para importar formularios con referencia a anuncios y campañas se necesitan `leads_retrieval`, `ads_management`, `pages_show_list`, `pages_read_engagement` y `pages_manage_ads`, además del acceso a los leads de la página, según [Meta Lead Ads](https://developers.facebook.com/docs/marketing-api/guides/lead-ads/retrieving/v2.8/). El token de prueba anterior no contenía `leads_retrieval` ni `ads_management`, por lo que debe renovarse antes de probar la importación real.

## Persistencia

- La pantalla consulta campañas y métricas directamente a Meta para el rango de fechas elegido. **Guardar campañas visibles** crea una copia de esas campañas y sus métricas en `meta_saved_campaigns`. Si ya existe la campaña para el mismo rango, solo se actualiza cuando cambian sus datos. La tabla ERP `campaigns` no se usa para esta copia.
- Los leads de los formularios de las páginas vinculadas se insertan siempre en `leads`, con empresa, página, formulario y los identificadores de anuncio/campaña cuando Meta los devuelve. Una nueva importación no sobrescribe la prioridad, etapa ni otras ediciones del CRM. Los mensajes de WhatsApp y Messenger no forman parte de este importador.
- `php artisan meta:sync-leads` importa los leads en cualquier momento. La tarea está programada cada 15 minutos; en el servidor debe ejecutarse `php artisan schedule:run` cada minuto (o `php artisan schedule:work` en desarrollo). También hay un botón **Importar leads ahora** para el administrador de la empresa.

La funcionalidad de etiquetas y comentarios sobre leads aún no se ha implementado; la persistencia y la referencia al usuario ERP preparan esa siguiente etapa.
