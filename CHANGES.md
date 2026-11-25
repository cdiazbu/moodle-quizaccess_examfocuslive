# Historial de cambios

## 0.3.7 - release candidate (2026-11-25)

- Address Moodle review MMR-661: add standard licence and copyright headers to AMD sources (#1).
- Render the teacher dashboard through Moodle Output API and Mustache (#2).
- Pass only the course module ID and polling interval to the dashboard AMD initializer; provide translated strings through the template (#3).
- Render student cards and the pause dialog through Moodle templates (#4).
- Adapt regression tests to the new rendering structure. Release pending compilation, automated integration tests and classroom validation.

## 0.3.6 - 2026-11-15

- Return to the quiz through its native link even when fullscreen exit is delayed or denied.
- Adopt Moodle’s table naming convention and migrate existing monitoring records in place.
- Keep Moodle’s normal timer when a quiz attempt is created before it has a database ID.
- Apply activity group boundaries consistently when Moodle returns group modes as database strings.
- Remove the loading placeholders when the roster first renders.
- Preserve modified link clicks and clean up the expanded dashboard when leaving it.

## 0.3.5 - 2026-11-14

- Leave fullscreen before opening the quiz in the same tab.

## 0.3.4 - 2026-11-14

- Let authorised teachers pause, resume or finish individual attempts and groups, including when automatic focus pauses are off.
- Keep manual pauses out of focus-incident counts and show students who paused the attempt.
- Place the quiz link and fullscreen control together and provide an expanded view when native fullscreen is unavailable.

## 0.3.3 - 2026-11-14

- Keep the quiz timer stopped when Moodle autosaves during a confirmed pause and restore the permitted deadline on resume.
- Preserve Moodle's closing time and submission grace period, and retain the timed behaviour of a teacher preview.
- Keep course navigation and the response form accessible after resuming. Translate supervision and dashboard messages.
- Exclude suspended users from the roster, check both teacher capabilities, and refresh the dashboard after a concurrent poll.
- Include abandoned attempts in retention and document the Moodle Privacy API's stored fields.
- Add regression coverage and include minified AMD files with source maps.

## 0.3.2 - 2026-11-14

- Rename the plugin to CD Live Exam Control and retain the installed component identifier.

## 0.3.1 - 2026-11-14

- Include teacher decisions in Moodle privacy requests and clear teacher identifiers on erasure.
- Document the quiz setup, monitoring scope and supported release verification.

## 0.3.0 - 2026-11-14

- Add a compact live classroom roster, including eligible participants who have not started.
- Add five sorting options, a detail view and fullscreen mode.
- Replace the large header with a compact status bar.

## 0.2.2 - 2026-11-14

- Ignore stale heartbeats after a focus observation and improve focus management in pause and decision dialogs.

## 0.2.1 - 2026-11-14

- Notify the student when a paused attempt is submitted or expires.

## 0.2.0 - 2026-11-14

- Preserve effective deadlines, enforce activity groups and serialize teacher decisions.
- Suppress navigation-related focus signals and pause only after server confirmation.
- Exclude previews, add exemptions and implement optional retention.

## 0.1.2 - 2026-11-14

- Cancel pending focus checks when the outgoing quiz page unloads.

## 0.1.0 — 2026-08-23

- Primera versión funcional.
- Supervisión en vivo y panel visual responsive.
- Detección de pérdida de foco con pausa automática.
- Temporizador congelado durante la pausa.
- Reanudación o finalización por el docente.
- Registro de incidencias, capacidades y Privacy API.

