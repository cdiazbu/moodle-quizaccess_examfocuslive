<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.

/**
 * Spanish language strings.
 *
 * @package    quizaccess_examfocuslive
 * @copyright  2026 Carlos Díaz Bueno
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

$string['pluginname'] = 'CD Live Exam Control';
$string['examfocusliveheader'] = 'CD Live Exam Control — supervisión en directo';
$string['enabled'] = 'Activar supervisión en directo';
$string['enabled_help'] = 'Muestra el progreso y las observaciones de foco del navegador al profesorado autorizado.';
$string['displaymode'] = 'Identificación del alumnado';
$string['displaymode_help'] = 'Elija si el panel muestra la foto, el nombre o ambos.';
$string['displayphoto'] = 'Solo fotografía';
$string['displayname'] = 'Solo nombre';
$string['displayboth'] = 'Fotografía y nombre';
$string['autopause'] = 'Pausar al perder el foco';
$string['autopause_help'] = 'Tras una pérdida de foco prolongada, pausa la interfaz y el temporizador hasta que el docente actúe o cierre el cuestionario.';
$string['showprogress'] = 'Mostrar progreso';
$string['heartbeat'] = 'Frecuencia de actualización';
$string['heartbeat_help'] = 'Intervalo entre actualizaciones del panel. Un intervalo corto ofrece mayor inmediatez, pero genera más peticiones al servidor.';
$string['seconds'] = '{$a} segundos';
$string['dashboard'] = 'Supervisión en directo';
$string['dashboardtitle'] = 'Sala de supervisión';
$string['dashboardintro'] = 'Seguimiento en vivo de {$a}';
$string['backtoquiz'] = 'Volver al cuestionario';
$string['active'] = 'En curso';
$string['paused'] = 'Requiere decisión';
$string['terminated'] = 'Finalizado por el docente';
$string['completed'] = 'Entregado';
$string['offline'] = 'Sin conexión';
$string['all'] = 'Todos';
$string['alerts'] = 'Alertas';
$string['online'] = 'Conectados';
$string['finished'] = 'Finalizados';
$string['searchstudent'] = 'Buscar estudiante';
$string['student'] = 'Estudiante';
$string['progress'] = 'Progreso';
$string['location'] = 'Parte actual';
$string['incidents'] = 'Incidencias';
$string['lastseen'] = 'Última señal';
$string['resume'] = 'Permitir continuar';
$string['terminate'] = 'Finalizar intento';
$string['resumetitle'] = 'Reanudar intento';
$string['resumeconfirm'] = 'El temporizador continuará desde el punto en que se pausó. La hora de cierre del cuestionario sigue vigente.';
$string['terminateconfirm'] = 'Moodle entregará las respuestas guardadas hasta este momento y aplicará su calificación habitual. La entrega es definitiva.';
$string['focuslost'] = 'Pérdida de foco detectada';
$string['focuslostdetail'] = 'El intento se ha pausado automáticamente. Espera a que el profesor autorice la continuación.';
$string['attemptresumed'] = 'El profesor ha autorizado la continuación. Recuperando el cuestionario…';
$string['attemptterminated'] = 'El profesor ha finalizado este intento.';
$string['monitoringactive'] = 'Supervisión activa';
$string['monitoringdescription'] = 'Este cuestionario utiliza CD Live Exam Control. La pérdida de foco puede pausar el intento y avisar al docente.';
$string['opendashboard'] = 'Abrir panel de supervisión';
$string['pagequestion'] = 'Página {$a->page} · {$a->question}';
$string['pageonly'] = 'Página {$a}';
$string['never'] = 'Nunca';
$string['privacy:metadata:attempt'] = 'Estado en vivo de los intentos supervisados.';
$string['privacy:metadata:attempt:userid'] = 'Identificador del estudiante.';
$string['privacy:metadata:attempt:attemptid'] = 'Identificador del intento.';
$string['privacy:metadata:attempt:status'] = 'Estado de supervisión del intento.';
$string['privacy:metadata:attempt:progress'] = 'Porcentaje de progreso estimado.';
$string['privacy:metadata:attempt:lastheartbeat'] = 'Momento de la última señal del navegador.';
$string['privacy:metadata:attempt:lostfocuscount'] = 'Número de pérdidas de foco detectadas.';
$string['privacy:metadata:event'] = 'Registro de incidencias y decisiones del docente.';
$string['privacy:metadata:event:eventtype'] = 'Tipo de incidencia o decisión.';
$string['privacy:metadata:event:eventtime'] = 'Momento de la incidencia.';
$string['privacy:metadata:event:metadata'] = 'Información técnica mínima asociada a la incidencia.';
$string['examfocuslive:monitor'] = 'Ver el panel de supervisión en directo';
$string['examfocuslive:manage'] = 'Reanudar o finalizar intentos supervisados';
$string['errornotenabled'] = 'CD Live Exam Control no está activado para este cuestionario.';
$string['errorinvalidattempt'] = 'El intento indicado no es válido.';
$string['errorinvalidaction'] = 'La acción solicitada no es válida.';
$string['statusupdated'] = 'Estado actualizado';
$string['taskfreezedeadlines'] = 'Mantener congelado el tiempo de los intentos pausados';

$string['controlfailed'] = 'No se pudo confirmar la acción. Vuelve a intentarlo o cierra este cuadro.';

$string['gracems'] = 'Tiempo mínimo de pérdida de foco';
$string['connectionlost'] = 'Se ha interrumpido la conexión con el panel. Puedes seguir usando los controles del cuestionario.';
$string['staleaction'] = 'El intento ha cambiado o se ha cerrado. Revisa su estado antes de actuar.';
$string['examfocuslive:exempt'] = 'Exención de la supervisión de exámenes en directo';

$string['conflictingmonitor'] = 'Activa un solo complemento CD de detección de foco por cuestionario.';
$string['taskcleanup'] = 'Borrar datos caducados de supervisión en directo';
$string['retentiondays'] = 'Conservación de datos de supervisión finalizada (días)';
$string['retentiondays_help'] = 'La limpieza se aplica únicamente a intentos finalizados o abandonados.';
$string['retentiondisabled'] = 'Borrado automático desactivado';
$string['privacy:metadata:event:actorid'] = 'Usuario que originó el evento.';
$string['privacy:metadata:event:eventtoken'] = 'Identificador aleatorio para evitar duplicar una incidencia.';

$string['waiting'] = 'Sin iniciar';
$string['overviewpage'] = 'Página';
$string['overviewincidents'] = 'Incidencias';
$string['overviewunfocused'] = 'Fuera de foco';
$string['overviewsort'] = 'Orden';
$string['sortname'] = 'Apellidos';
$string['sortstatus'] = 'Estado';
$string['sortincidents'] = 'Más incidencias';
$string['sortprogress'] = 'Mayor progreso';
$string['sortseen'] = 'Última señal';
$string['overviewview'] = 'Vista';
$string['overviewcompact'] = 'Aula';
$string['overviewdetails'] = 'Detalle';
$string['overviewfullscreen'] = 'Pantalla completa';
$string['exitfullscreen'] = 'Salir de pantalla completa';

$string['pause'] = 'Pausar';
$string['pausetitle'] = 'Pausar intento';
$string['pauseconfirm'] = 'El tiempo individual se detendrá hasta que el docente permita continuar. La hora de cierre sigue vigente.';
$string['teacherpaused'] = 'Intento pausado por el docente';
$string['teacherpauseddetail'] = 'El docente ha pausado tu intento. Podrás seguir cuando autorice la continuación.';
$string['pausedattempts'] = 'En pausa';
$string['groupcontrol'] = 'Acciones de grupo';
$string['applyto'] = 'Aplicar a';
$string['selectedattempts'] = 'Selección';
$string['allattempts'] = 'Todo el alumnado';
$string['selectstudent'] = 'Seleccionar a {$a}';
$string['selectvisible'] = 'Seleccionar visibles';
$string['clearselection'] = 'Limpiar selección';
$string['selectioncount'] = 'En la selección: {$a}';
$string['bulkconfirm'] = 'Intentos que se verán afectados: {$a}.';
$string['bulkresult'] = 'Intentos actualizados: {$a->updated}. Sin cambios: {$a->skipped}.';
$string['bulkfailed'] = 'No se pudo confirmar el resultado de toda la operación. Revisa el panel actualizado.';

$string['waitingdecision'] = 'Esperando la decisión del docente.';
$string['overdue'] = 'Periodo de gracia para entregar';
$string['dashboardsummary'] = 'Resumen';
$string['dashboardfilters'] = 'Filtros';
$string['dashboardempty'] = 'No hay estudiantes en esta vista';
$string['syncstarting'] = 'Conectando';
$string['syncupdating'] = 'Actualizando';
$string['synclive'] = 'En directo';
$string['syncretrying'] = 'Reconectando';
$string['lastseennow'] = 'Ahora';
$string['lastseenseconds'] = 'Hace {$a} s';
$string['lastseenminutes'] = 'Hace {$a} min';
$string['activeelapsed'] = 'Tiempo activo';
$string['pausedalert'] = 'Pérdida de foco. Requiere decisión docente';
$string['privacy:metadata:attempt:focusstate'] = 'Último estado de foco observado en el navegador.';
$string['privacy:metadata:attempt:page'] = 'Página del cuestionario indicada por la interfaz de supervisión.';
$string['privacy:metadata:attempt:questionlabel'] = 'Etiqueta de navegación de la pregunta o página actual.';
$string['privacy:metadata:attempt:lastviolation'] = 'Momento de la última pérdida de foco prolongada.';
$string['privacy:metadata:attempt:pausedat'] = 'Inicio de la pausa actual.';
$string['privacy:metadata:attempt:pausedbyteacher'] = 'Indica si la pausa actual la activó un docente.';
$string['privacy:metadata:attempt:controlrevision'] = 'Identificador técnico de la versión del estado del intento.';
$string['privacy:metadata:attempt:pausedseconds'] = 'Segundos transcurridos en pausa.';
$string['privacy:metadata:attempt:remainingatpause'] = 'Tiempo disponible cuando se pausó el intento.';
$string['privacy:metadata:attempt:timecreated'] = 'Momento en que comenzó la supervisión.';
$string['privacy:metadata:attempt:timemodified'] = 'Momento del último cambio en el estado de supervisión.';
