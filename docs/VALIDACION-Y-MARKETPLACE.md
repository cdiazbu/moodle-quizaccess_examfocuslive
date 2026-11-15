# CD Live Exam Control 0.3.6

**Versión candidata para Moodle 4.5 · compilación 2026111500 · 15 de noviembre de 2026**

| Comprobación | Resultado |
|---|---|
| Versión 0.3.2, compilación 2026111407 | El clon Moodle 4.5.13+ registró la instalación y la apertura del panel. Se verificó la huella del ZIP archivado. |
| Validación local | Pruebas JavaScript del listado, la selección, la pausa docente, la reanudación, el periodo de gracia y el fin de intento. Sintaxis PHP y esquema XMLDB de Moodle 4.5 comprobados. |
| PHP y estructura | Sintaxis PHP 8.2.34 comprobada en 25 archivos y 117 cadenas de idioma coincidentes. `db/install.xml` supera la validación con el esquema XMLDB de Moodle 4.5. |
| Privacidad y retención | Ocho comprobaciones locales sobre datos ficticios con PHP y SQLite. Cubren la declaración de campos, la localización de usuarios y contextos, las exportaciones, el borrado del docente y del alumno y la retención de intentos abandonados. |
| Moodle 4.5 con MariaDB y PostgreSQL | Consulta [Moodle Plugin CI](https://github.com/cdiazbu/moodle-quizaccess_examfocuslive/actions) para comprobar el resultado de cada motor. La suite de integración incluye tiempo individual, decisiones obsoletas, entrega y calificación, grupos, privacidad y retención. |
| Prueba en el clon | Usar las cuentas ficticias de la prueba y el procedimiento de este documento. |
| Repositorio público | [Código y seguimiento de incidencias en GitHub](https://github.com/cdiazbu/moodle-quizaccess_examfocuslive). |

El mínimo técnico heredado en `version.php` es Moodle 4.4. La matriz inicial tiene como objetivo Moodle 4.5 y se declarará esa rama cuando superen las pruebas el paquete y la instalación real.

## Prueba en el clon

Instala el ZIP en **Administración del sitio → Plugins → Instalar plugins**. Anota la compilación, las versiones de Moodle y de PHP, el motor de base de datos, el navegador y el resultado de cada caso. Usa un cuestionario de práctica con dos páginas y cuentas ficticias. La segunda sesión docente sirve para comprobar decisiones simultáneas. La vista previa de profesor queda fuera del seguimiento.

| Caso | Resultado esperado |
|---|---|
| Respuesta, Siguiente, Anterior, navegación del cuestionario y entrega | Las respuestas se conservan y el panel registra cero incidencias de foco por navegar. |
| Cambio de pestaña prolongado con pausa activada | Una incidencia. El reloj queda detenido y el formulario queda pausado. Una actualización del guardado automático de Moodle conserva esa pausa. |
| Pausa manual con el examen enfocado | El alumno ve «Intento pausado por el docente» y conserva el contador de incidencias. Repetir con la pausa automática desactivada y antes de la primera señal del alumno. |
| Selección, todos los intentos y confirmación | Selecciona un subconjunto en Aula o Detalle. Pausa solo ese subconjunto. En «Todo el alumnado», verifica el recuento y aplica la acción a los intentos autorizados. Para finalizar, comprueba la entrega de las respuestas guardadas y los resultados de la operación. |
| Volver al cuestionario y Pantalla completa | Ambos quedan en la cabecera. Prueba entrar y salir del modo ampliado, incluidas ventanas estrechas y una página embebida que restrinja la pantalla completa del navegador. |
| Cancelar, Escape y cerrar el aviso docente | La pausa continúa. El foco del teclado vuelve al control desde el que se abrió el aviso. |
| Permitir continuar | Retorna el control al alumno, con su respuesta y el tiempo que conservaba. Repetir con un límite individual, con hora de cierre y sin límite. |
| Hora de cierre durante la pausa | Moodle aplica su política de cierre. Con periodo de gracia, el alumno puede acceder al resumen para entregar las respuestas guardadas. |
| Entregar y calificar | Moodle cierra el intento ficticio, califica las respuestas guardadas y actualiza el panel docente. |
| Grupos, permisos y dos decisiones simultáneas | Con grupos separados se respeta la visibilidad. Con grupos visibles, un docente sin permiso para todos los grupos solo controla el suyo. Un profesor de solo lectura puede observar. Para la misma pausa se acepta una decisión. |
| Aula, teclado y ampliación | Se muestran todos los matriculados elegibles, con los cinco criterios de ordenación y sin paginación. Prueba las vistas Aula y Detalle con más de 30 usuarios ficticios, incluido uno exento y otro suspendido. |
| Exportar y borrar datos desde Moodle | El docente solo recibe sus decisiones. Tras borrarlo se conservan los registros de los alumnos y se eliminan las referencias al docente. El borrado del alumno afecta al historial del complemento y Moodle conserva su intento según su propio procedimiento de privacidad. |

En Moodle 4.5, `attempt.php` y `summary.php` consultan la regla de acceso. `autosave.ajax.php` y `processattempt.php` gestionan el guardado y procesamiento directos mediante Moodle core. La ficha describe la pausa de la interfaz y el temporizador y mantiene ese alcance. Una señal del navegador requiere interpretación docente.

## Publicación en Moodle Marketplace

1. Crea el repositorio público `cdiazbu/moodle-quizaccess_examfocuslive` y sincroniza la rama `main` del paquete de fuentes. Usa el componente `quizaccess_examfocuslive`. Comprueba que GitHub muestra el código, el README y el enlace de Issues.
2. Ejecuta el flujo **CD Live Exam Control verification** de GitHub Actions. Revisa MariaDB y PostgreSQL y descarga el ZIP generado con los módulos compilados por Moodle. Instala ese ZIP exacto en el clon, suma su SHA-256 a las evidencias y completa el recorrido docente–alumno. Usa ese mismo archivo en la versión etiquetada de GitHub y en Marketplace.
3. Entra en [Moodle Marketplace](https://marketplace.moodle.com/dashboard) con la cuenta de Moodle.org. Pulsa **Submit a plugin**, selecciona **Free**, elige **Quiz access rules** y sube el ZIP probado. Crea una ficha de **CD Live Exam Control** con su identificador, documentación, repositorio e incidencias. Declara las ramas que hayan pasado las pruebas.
4. Completa los controles automáticos. Añade capturas del panel y de la pausa con cuentas ficticias, sin datos del centro en la ficha pública. Atiende la revisión desde Jira Service Management. Tras su aprobación, comprueba la ficha y pulsa **Publish**.

### Ficha en inglés

| Field | Value |
|---|---|
| Name | CD Live Exam Control |
| Component | `quizaccess_examfocuslive` |
| Category | Quiz access rules |
| Pricing | Free |
| Licence | GNU GPL v3 or later |
| Maintainer | Carlos Díaz Bueno |

**Short description**

Monitor an entire class during a Moodle quiz with a live roster. Record sustained browser focus changes and optionally pause the quiz interface until a teacher acts.

**Description**

CD Live Exam Control adds a classroom dashboard to Moodle quizzes. Teachers can find participants who have yet to start, follow estimated progress, see the last browser signal and sort or filter the eligible roster. The dashboard offers compact and detailed views.

When automatic pause is enabled, sustained focus loss pauses the usual student interface and timer. An authorised teacher can pause, resume or finish eligible attempts individually, for a selection or for the whole roster in scope. Moodle submits the saved answers on confirmation. The Moodle closing time and overdue policy still apply. A focus observation requires teacher interpretation. The plugin uses neither a camera, microphone nor screen capture. Monitoring data stays in Moodle and is available through its Privacy API.

**Installation and setup**

Install the release ZIP in the Moodle plugin installer. Enable CD Live Exam Control in a practice quiz, review the dashboard and pause settings, then use separate teacher and enrolled student sessions. See the README for permissions, retention and the Moodle 4.5 pause behaviour.

**Documentación oficial**

- [Cómo enviar un complemento a Moodle Marketplace](https://docs.moodle.org/405/en/Plugins_FAQ#How_do_I_submit_my_own_plugin_to_Moodle_Marketplace.3F)
- [Moodle Plugin CI](https://moodlehq.github.io/moodle-plugin-ci/)
- [CD ExamFocus, ficha de referencia](https://marketplace.moodle.com/plugins/quizaccess_cdexamsave)
