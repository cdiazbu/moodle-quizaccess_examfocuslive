# CD Live Exam Control

CD Live Exam Control is a Moodle quiz access rule that gives authorised teachers a live overview of a class during a quiz. The compact roster shows eligible participants, including those who have not started, and provides filtering, five sort orders, a detailed view and an optional fullscreen mode.

## Installation

1. As a site administrator, open **Site administration → Plugins → Install plugins** and upload the release ZIP. Its sole top-level directory is `examfocuslive`.
2. Complete the Moodle upgrade. For a manual install, place the directory under the quiz access rules in your Moodle web root, at `mod/quiz/accessrule/examfocuslive`, then run the normal Moodle upgrade.
3. Edit a quiz and enable **CD Live Exam Control – live proctoring**. Choose how to identify participants, whether to display progress and whether sustained focus loss should pause an attempt. Use a practice quiz to check the settings before an assessed session.
4. Open the quiz as a teacher and choose **Open monitoring dashboard**. Start a separate attempt as an enrolled student to see live updates. Moodle teacher previews are excluded from monitoring.

Version 0.3.6 is an alpha release candidate. The validation target is Moodle 4.5. See [validation and Marketplace submission](docs/VALIDACION-Y-MARKETPLACE.md) for the evidence required to declare support.

## During a quiz

The monitoring permission grants dashboard access. Teachers with the management permission can pause or finish an in-progress attempt and resume a paused attempt. They can also select students or act on all eligible attempts in the quiz. Group permissions and exemptions apply. Moodle submits saved responses when a teacher confirms finishing an attempt. Manual pauses have their own notice and leave the focus incident count intact.

The focus observation delay filters brief tab changes and normal quiz navigation. When a browser restricts native fullscreen, the dashboard can expand to fill the available view. Use **Back to quiz** to return to the activity.

When automatic pause is enabled, the student's normal quiz interface is paused after the server confirms the event. The access rule adjusts the attempt's time limit to account for the pause. The effective Moodle closing time still applies. The teacher can resume the attempt or submit the responses already saved by Moodle.

The plugin observes browser focus and Moodle continues to store and grade answers. It uses neither screen, camera nor microphone capture and makes no request to an external monitoring service. In Moodle 4.5, the normal attempt and summary pages honour the rule's pause, while the direct browser scripts for saving and processing attempts are governed by Moodle core. Use the activity under teacher supervision.

## Privacy

Live monitoring stores the student and attempt identifiers, position, estimated progress, last browser signal, pause state and timestamps. The event history stores focus observations and limited identifiers for teacher decisions. Optional profile pictures are served through Moodle's existing picture service. Moodle's Privacy API supports identifying and exporting associated data and erasing or clearing identifiers. An optional site-level scheduled cleanup removes older monitoring data for finished and abandoned attempts. The default setting leaves automatic cleanup off.

## Development

Source modules are in `amd/src` and browser-ready modules are in `amd/build`. For standalone development, run `npm install --no-save --no-package-lock --ignore-scripts terser@5.51.2` followed by `node tools/build-amd.js`. Run `node --test tests/dashboard.test.cjs tests/student_monitor.test.cjs` for the isolated JavaScript tests. The Moodle CI workflow also compiles the Moodle-native AMD files and executes the PHPUnit suite.

Copyright (C) 2026 Carlos Díaz Bueno. Distributed under the [GNU General Public License, version 3 or later](COPYING.txt).

## Guía rápida en español

Instala el ZIP en **Administración del sitio → Plugins → Instalar plugins**. Activa **CD Live Exam Control** en los ajustes de un cuestionario de prueba. Abre el panel desde el cuestionario como docente e inicia el intento con una cuenta de alumno matriculada. En el panel puedes filtrar, ordenar o elegir la vista Detalle. Si activas la pausa automática, comprueba primero cómo responde al envío normal de respuestas y a un cambio de pestaña prolongado. Consulta el informe de validación antes de usar esta versión preliminar en un examen evaluable.
