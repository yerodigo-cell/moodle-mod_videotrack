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
 * VideoTrack (mod_videotrack)
 *
 * @package     mod_videotrack
 * @copyright   2026 EduPlugins Studio
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

$string['accentcolor'] = 'Color de acento';
$string['accentcolor_help'] = 'Ingresa un código de color hexadecimal (por ejemplo #0f6cbf) para personalizar la barra de progreso y los acentos del reproductor. Déjalo vacío o en #0ea5e9 para mantener el color azul original.';
$string['backtocourse'] = 'Volver al curso';
$string['captionsfile'] = 'Subtítulos (archivo VTT o SRT)';
$string['captionsfile_help'] = 'Sube un archivo WebVTT (.vtt) o SubRip (.srt) para mostrar subtítulos cerrados en el reproductor de video.';
$string['completed'] = 'Completado';
$string['completiondetail:targetpercent'] = 'Ver al menos el {$a}% del video';
$string['completionwatch'] = 'El estudiante debe ver al menos';
$string['displayandplayback'] = 'Visualización y reproducción';
$string['displaymode'] = 'Visualización en la página del curso';
$string['displaymode_help'] = 'Elige cómo se presenta la actividad en la página del curso: como página de actividad estándar, como tarjeta resumen que abre el reproductor en una ventana emergente (popup), o con el reproductor incrustado directamente en la página del curso.';
$string['displaymode_inline'] = 'Reproductor incrustado directamente en la página del curso';
$string['displaymode_page'] = 'Página de actividad estándar';
$string['displaymode_popup'] = 'Tarjeta en la página del curso (abre el reproductor en popup)';
$string['error_invalidcolor'] = 'Por favor, ingresa un código de color hexadecimal válido (ej. #0f6cbf).';
$string['error_invalidpercent'] = 'Por favor, ingresa un porcentaje válido entre 1 y 100.';
$string['error_nouploadorurl'] = 'Debes proveer una URL externa O subir un archivo de video local.';
$string['eventcoursemoduleviewed'] = 'VideoTrack visualizado';
$string['focusmode'] = 'Modo Enfoque (Focus Mode)';
$string['focusmode_badge'] = 'Modo Enfoque';
$string['focusmode_desc'] = 'Pausar automáticamente el video al cambiar de pestaña o de ventana (en videos de Google Drive se pausará la barra de progreso, no el reproductor)';
$string['focusmode_help'] = 'Cuando está activo, el video se pausará automáticamente si el estudiante cambia a otra pestaña del navegador o ventana de aplicación, mostrando un indicador de Modo Enfoque en la esquina superior derecha de la tarjeta. Nota: Por restricciones del reproductor de Google Drive, en videos de Google Drive este modo pausa el avance de la barra de progreso y no el video en sí.';
$string['focusmode_paused'] = 'Pausado — Mantén esta ventana activa';
$string['gdrivedisclaimer'] = 'Para una mejor experiencia y un seguimiento exacto, te recomendamos subir el video a YouTube o adjuntar el archivo MP4 directamente a Moodle. El seguimiento manual para Google Drive se ofrece solo como una alternativa de respaldo.';
$string['gdrivenotrack'] = 'Este video está incrustado desde Google Drive. El rastreo de progreso no está disponible para este reproductor.';
$string['highestpercent'] = 'Mayor porcentaje visto';
$string['isgdrive'] = 'Habilitar seguimiento de tiempo manual (Google Drive)';
$string['isgdrive_help'] = 'Marca esta casilla si el video es de Google Drive o un reproductor que no soporta rastreo automático. El progreso se medirá según el tiempo que el estudiante mantenga activa la página.';
$string['lastaccess'] = 'Último acceso';
$string['manualresumehint'] = 'Tu progreso está guardado en <strong>{$a}</strong>. Por favor, dale a reproducir y adelanta el video manualmente hasta este punto.';
$string['manualtime'] = 'Duración total del video (HH:MM:SS)';
$string['manualtime_help'] = 'Ingresa la duración total del video en horas, minutos y segundos. El tiempo requerido en página se calculará con base en este valor y el Porcentaje requerido.';
$string['modulename'] = 'VideoTrack';
$string['modulename_help'] = 'La actividad VideoTrack permite incrustar un video y requerir que el estudiante visualice un porcentaje específico.';
$string['modulenameplural'] = 'VideoTracks';
$string['noresponses'] = 'Aún no hay registro de progreso para este video.';
$string['pluginadministration'] = 'Administración de VideoTrack';
$string['pluginname'] = 'VideoTrack';
$string['privacy:metadata:videotrack_progress'] = 'Almacena el progreso de visualización de video del usuario y el estado de finalización.';
$string['privacy:metadata:videotrack_progress:highestpercent'] = 'El mayor porcentaje del video que el usuario ha visto.';
$string['privacy:metadata:videotrack_progress:highesttime'] = 'El tiempo máximo de reproducción en segundos alcanzado por el usuario.';
$string['privacy:metadata:videotrack_progress:iscompleted'] = 'Indica si el usuario ha completado el porcentaje objetivo requerido.';
$string['privacy:metadata:videotrack_progress:timecreated'] = 'El momento en que se creó el registro de progreso.';
$string['privacy:metadata:videotrack_progress:timemodified'] = 'El momento en que se modificó por última vez el registro de progreso.';
$string['privacy:metadata:videotrack_progress:userid'] = 'El ID del usuario.';
$string['privacy:metadata:videotrack_reactions'] = 'Información sobre las reacciones del usuario durante la reproducción del video.';
$string['privacy:metadata:videotrack_reactions:reaction'] = 'El tipo de reacción enviada por el usuario.';
$string['privacy:metadata:videotrack_reactions:timecreated'] = 'La fecha y hora en que se registró la reacción.';
$string['privacy:metadata:videotrack_reactions:timeoffset'] = 'El segundo del video en el que ocurrió la reacción.';
$string['privacy:metadata:videotrack_reactions:userid'] = 'El ID del usuario que envió la reacción.';
$string['progressfree'] = 'Este video es de exploración libre. Puedes visualizarlo y adelantarlo a tu propio ritmo.';
$string['progresshint'] = 'Debes visualizar al menos el <strong>{$a}%</strong> del video para completar esta actividad.';
$string['progresstitle'] = 'Progreso de visualización';
$string['report'] = 'Reporte de progreso';
$string['resumebutton'] = 'Reanudar desde {$a}';
$string['student'] = 'Estudiante';
$string['successmsg'] = '¡Felicidades! Has alcanzado el porcentaje requerido. Ya puedes continuar.';
$string['targetpercent'] = 'Porcentaje requerido (%)';
$string['targetpercent_help'] = 'El porcentaje del video que el estudiante debe ver para completar la actividad (por defecto 80%). Desmarca esta condición si quieres que el video sea libre y se pueda adelantar sin restricciones.';
$string['videofile'] = 'Archivo de Video (Local)';
$string['videofile_help'] = 'Sube tu archivo de video MP4 aquí. Nota: Si ingresas una URL externa arriba, tendrá prioridad sobre este archivo.';
$string['videotrack:addinstance'] = 'Añadir un nuevo VideoTrack';
$string['videotrack:view'] = 'Ver VideoTrack';
$string['videotrack:viewreport'] = 'Ver reporte de progreso';
$string['videourl'] = 'URL del video (Externo)';
$string['videourl_help'] = 'Pega aquí el enlace de YouTube o URL directa MP4. Si prefieres subir un archivo directamente a Moodle, deja esto en blanco y usa el subidor de archivos de abajo.';
$string['watchvideo'] = 'Ver video';
