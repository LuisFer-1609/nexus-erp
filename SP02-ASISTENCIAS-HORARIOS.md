# SP/02 — Vista de asistencias y horarios

## Objetivo

Entregar una vista operativa para consultar la asistencia diaria de empleados de prueba, definir la jornada semanal y corregir manualmente los registros.

## Alcance de esta entrega

- Configuración de horario por día de la semana.
- Activación o desactivación de días laborables para representar descansos.
- Consulta diaria con filtros por fecha, empleado y estado.
- Comparación visual entre horario esperado y entrada/salida real.
- Cálculo de minutos de retardo y horas extra.
- Estados de `A tiempo`, `Retardo` y `Falta`.
- Corrección manual de entrada y salida desde el detalle del registro.
- Persistencia local para la demostración con datos de prueba.

## Criterios de aceptación

| Criterio                                         | Estado                      |
| ------------------------------------------------ | --------------------------- |
| La vista diaria muestra retardos y horas extra   | Cumplido                    |
| Se pueden definir horarios por día               | Cumplido                    |
| Se pueden marcar días de descanso                | Cumplido                    |
| Se pueden filtrar registros por empleado y fecha | Cumplido                    |
| Las correcciones se guardan                      | Cumplido para la demo local |

## Consideraciones técnicas

La implementación actual se encuentra en `resources/js/pages/preNomina/Asistencias.vue` y utiliza empleados de prueba, como se definió en Trello. Los cambios se guardan en `localStorage` para conservar la interacción durante la demostración; la conexión con el alta real de empleados, base de datos, autenticación por empresa y endpoints de persistencia queda como integración posterior.

## Prueba manual sugerida

1. Abrir `PRE-NÓMINA > Asistencias`.
2. En `Asistencia diaria`, cambiar fecha, empleado o estado y comprobar que la tabla se filtra.
3. Seleccionar `Corregir` en un empleado, modificar la entrada o salida y guardar.
4. Confirmar que se actualizan el retardo, las horas extra y el estado.
5. Abrir `Horarios de trabajo`, cambiar un horario o marcar un día como descanso y guardar.
6. Recargar la página y comprobar que la configuración de la demo se conserva.

## Verificación

`git diff --check` no reporta errores. La validación con `npm run types:check` y `npm run build` queda pendiente de instalar dependencias, ya que el entorno actual no contiene `node_modules`.
