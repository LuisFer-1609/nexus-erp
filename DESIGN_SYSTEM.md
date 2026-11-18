# Sistema de diseño de NexusERP

Este documento define las reglas visuales, de interacción y de composición para
NexusERP. Es una referencia viva: cualquier pantalla nueva debe seguir estas
reglas y cualquier excepción debe quedar documentada.

## 1. Arquitectura de interfaz

NexusERP utiliza los siguientes patrones:

- **MVC en Laravel:** las rutas delegan en controladores y los modelos
  representan los datos del dominio.
- **Adapter mediante Inertia:** Laravel entrega páginas y propiedades a Vue sin
  mantener una API duplicada para la navegación principal.
- **Layouts persistentes:** autenticación, configuración y operación usan
  layouts seleccionados desde `resources/js/app.ts`.
- **Composición de componentes:** las pantallas se construyen con componentes
  pequeños y reutilizables, no mediante vistas monolíticas.
- **Navegación dirigida por configuración:** las secciones y enlaces del sidebar
  se declaran como datos en `NexusLayout.vue`.
- **Estado reactivo:** Vue administra expansión, navegación y preferencias
  locales; `localStorage` solo conserva preferencias no sensibles.
- **Autenticación mediante Fortify:** login, recuperación, verificación, 2FA,
  passkeys y logout dependen de las rutas y acciones de Fortify.

La arquitectura actual es un monolito modular en evolución. La lógica de
negocio no debe implementarse dentro de componentes Vue ni controladores
extensos. Cuando un módulo crezca, se separará en servicios y repositorios.

## 2. Dirección visual

La interfaz sigue un patrón de **dashboard empresarial sobrio**:

- Sidebar azul petróleo oscuro.
- Superficies de trabajo claras.
- Cian como único acento principal.
- Grises fríos para texto, bordes y estados secundarios.
- Elevación discreta y transiciones breves.
- Densidad suficiente para datos empresariales sin sacrificar legibilidad.

No se deben introducir gradientes morados, sombras negras pesadas, colores de
acento arbitrarios ni estilos visuales ajenos a esta dirección.

## 3. Tokens de color

| Uso                      | Valor de referencia |
| ------------------------ | ------------------- |
| Fondo del sidebar        | `#0a161e`           |
| Superficie activa oscura | `#162732`           |
| Acento principal         | `#23c6c8`           |
| Acento de texto          | `#139b9e`           |
| Fondo de aplicación      | `#f6f8fa`           |
| Superficie               | `#ffffff`           |
| Texto principal          | `slate-900`         |
| Texto secundario         | `slate-500`         |
| Borde                    | `slate-200`         |
| Error                    | `red-600`           |
| Éxito                    | `emerald-600`       |
| Advertencia              | `amber-600`         |

Reglas:

- Usar variables semánticas o clases del tema antes de escribir un hexadecimal.
- El cian identifica acción, selección o foco; no debe decorar contenido sin
  significado.
- Los estados de error, éxito y advertencia conservan sus colores semánticos.
- No mezclar grises cálidos y fríos en una misma pantalla.
- El contraste mínimo debe cumplir WCAG AA.

## 4. Tipografía

La familia principal es **Instrument Sans**. No se sustituye sin una revisión
completa del producto.

| Elemento                  | Tamaño                               |
| ------------------------- | ------------------------------------ |
| Título de página          | 30–32 px, semibold                   |
| Título de sección         | 20–24 px, semibold                   |
| Valor KPI                 | 26–30 px, semibold, cifras tabulares |
| Texto principal           | 14–16 px                             |
| Navegación principal      | 14 px, medium o semibold             |
| Navegación secundaria     | 13 px, medium                        |
| Etiqueta de formulario    | 13–14 px, semibold                   |
| Texto auxiliar            | 12–13 px                             |
| Microetiqueta excepcional | 11 px como mínimo                    |

Reglas:

- No utilizar texto menor a 11 px.
- Reservar mayúsculas y tracking amplio para etiquetas breves.
- Usar `tracking-tight` en títulos y `tabular-nums` en métricas.
- Limitar descripciones a un ancho legible y usar interlineado de 1.5.
- Mantener jerarquía por tamaño y peso, no solo por color.

## 5. Espaciado, radios y superficies

- Usar la escala de Tailwind y evitar valores arbitrarios cuando exista una
  equivalencia.
- Separación entre secciones: 24–32 px.
- Separación interna de paneles: 16–24 px.
- Controles: altura mínima de 40 px; 44 px en acciones principales.
- Radio de controles: 6–8 px.
- Radio de paneles: 10–12 px.
- El contenido operativo no debe superar aproximadamente 1500 px de ancho.
- Usar sombra únicamente cuando comunique elevación o interacción.
- No envolver cada bloque en una tarjeta; el espacio también crea jerarquía.

## 6. Layout y navegación

- `NexusLayout` es el layout principal de los módulos operativos.
- El sidebar debe conservar estados expandido, colapsado y móvil.
- La ruta activa siempre debe ser visible.
- Toda pantalla debe tener título, descripción y ruta contextual.
- El encabezado de usuario debe ofrecer perfil, seguridad y cierre de sesión.
- Los controles sin funcionalidad deben ocultarse o mostrarse deshabilitados
  con una explicación.
- Configuración debe converger gradualmente con el lenguaje visual de
  `NexusLayout`.

## 7. Componentes

- Reutilizar `NexusPageHeader`, `NexusEmptyState`, `NexusKpiCard` y
  `NexusBadge` antes de crear variantes locales.
- Extraer un componente cuando un patrón aparezca en dos o más pantallas.
- Mantener una sola responsabilidad por componente.
- Los componentes base de `resources/js/components/ui` no deben contener
  reglas de negocio.
- Los iconos deben usar el mismo grosor visual y medir normalmente 16–20 px.
- Las acciones destructivas requieren texto explícito y confirmación.

## 8. Estados de interfaz

Toda vista que consuma datos debe contemplar:

1. **Carga:** skeleton con la forma del contenido esperado.
2. **Vacío:** explicación breve y una acción útil cuando corresponda.
3. **Error:** mensaje directo, causa recuperable y acción para reintentar.
4. **Éxito:** confirmación breve sin exclamaciones innecesarias.
5. **Sin permisos:** explicar qué acceso falta sin revelar datos protegidos.

No usar `window.alert()` ni dejar áreas vacías sin explicación.

## 9. Interacción y accesibilidad

- Todos los controles deben ser accesibles por teclado.
- Mantener un foco visible con el color de acento.
- Añadir estados hover, active y disabled.
- Usar transiciones de 150–250 ms basadas en `transform` y `opacity`.
- Respetar `prefers-reduced-motion`.
- Los botones de icono requieren `aria-label`.
- Las imágenes informativas requieren texto alternativo.
- No comunicar estado únicamente mediante color.
- Los objetivos táctiles deben medir al menos 40 × 40 px.

## 10. Autenticación y sesión

- El registro público permanece deshabilitado.
- Los usuarios se crean únicamente mediante procesos administrativos
  autorizados.
- El cierre de sesión utiliza la ruta POST de Fortify.
- Antes de cerrar sesión se limpia el estado de navegación de Inertia.
- La acción “Cerrar sesión” debe estar disponible desde el menú de usuario del
  layout principal y funcionar también en móvil.
- Nunca almacenar contraseñas, tokens o datos de sesión en `localStorage`.

## 11. Convenciones de implementación

- Usar TypeScript en componentes Vue.
- No duplicar rutas como cadenas cuando exista un helper de Wayfinder.
- No agregar dependencias sin comprobar primero que el stack actual no resuelva
  el problema.
- Evitar estilos inline y colores repetidos fuera del tema.
- Mantener el contenido de la interfaz en español neutro.
- Probar responsive, teclado y estados vacíos antes de cerrar una tarea.
- Los mensajes de commit siguen `tipo(ALCANCE): descripción`.

## 12. Lista de verificación

Antes de integrar una pantalla:

- [ ] Usa el layout y los componentes compartidos.
- [ ] Respeta la escala tipográfica y no contiene texto menor a 11 px.
- [ ] Usa colores y espaciados del sistema.
- [ ] Tiene estados de carga, vacío, error y permisos cuando aplican.
- [ ] Tiene foco, hover, active y disabled.
- [ ] Funciona con teclado y en viewport móvil.
- [ ] No contiene datos sensibles, placeholders genéricos ni acciones muertas.
- [ ] Pasa `npm run check`, `npm run build` y las pruebas relacionadas.
