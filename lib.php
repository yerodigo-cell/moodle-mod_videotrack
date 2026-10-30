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

/**
 * Sanitizes a hex accent color string (#RRGGBB, RRGGBB, #RGB or RGB).
 *
 * @param string|null $color Raw color string.
 * @return string Normalized hex color with leading # or empty string.
 */
function videotrack_sanitize_accentcolor(?string $color): string {
    $color = trim((string)$color);
    if ($color === '') {
        return '';
    }
    if ($color[0] !== '#') {
        $color = '#' . $color;
    }
    if (preg_match('/^#([A-Fa-f0-9]{6}|[A-Fa-f0-9]{3})$/', $color)) {
        return strtolower($color);
    }
    return '';
}

/**
 * Ensures the 'accentcolor' and 'displaymode' columns exist in {videotrack} even if site upgrade has not been run yet.
 *
 * @return void
 */
function videotrack_ensure_schema_columns(): void {
    global $DB;
    try {
        $columns = $DB->get_columns('videotrack', true);
        $dbman = $DB->get_manager();
        $table = new xmldb_table('videotrack');
        $changed = false;

        if (!isset($columns['accentcolor'])) {
            $field = new xmldb_field('accentcolor', XMLDB_TYPE_CHAR, '20', null, null, null, null, 'focusmode');
            if (!$dbman->field_exists($table, $field)) {
                $dbman->add_field($table, $field);
                $changed = true;
            }
        }

        if (!isset($columns['displaymode'])) {
            $field2 = new xmldb_field('displaymode', XMLDB_TYPE_INTEGER, '2', null, XMLDB_NOTNULL, null, '0', 'accentcolor');
            if (!$dbman->field_exists($table, $field2)) {
                $dbman->add_field($table, $field2);
                $changed = true;
            }
        }

        if ($changed) {
            $DB->get_columns('videotrack', false);
        }
    } catch (Throwable $e) {
        // Ignore if DB user cannot alter schema on the fly.
    }
}

/**
 * Ensures course_modules.showdescription is enabled when displaymode is 1 or 2 so format_tiles exports content via AJAX.
 *
 * @param int $cmid Course module ID.
 * @param int $displaymode Display mode (0, 1 or 2).
 * @return void
 */
function videotrack_sync_cm_showdescription(int $cmid, int $displaymode): void {
    global $DB;
    if ($cmid > 0 && $displaymode > 0) {
        try {
            $DB->set_field('course_modules', 'showdescription', 1, ['id' => $cmid]);
        } catch (Throwable $e) {
            // Ignore if DB write is not permitted in current context.
        }
    }
}

/**
 * Injects the course-level JS helper for Popup Modal, PostMessage Iframe Resizing, and format_tiles sub-tile support.
 *
 * @param int $courseid The course ID.
 * @return void
 */
function videotrack_inject_course_js(int $courseid): void {
    global $DB, $PAGE, $USER;

    static $injected = false;
    if ($injected || !isset($PAGE) || $courseid <= 0) {
        return;
    }

    try {
        $records = $DB->get_records('videotrack', ['course' => $courseid]);
        if (empty($records)) {
            return;
        }

        $modinfo = get_fast_modinfo($courseid);
        $cms = $modinfo->get_instances_of('videotrack');
        if (empty($cms)) {
            return;
        }

        $items = [];
        foreach ($cms as $instanceid => $cminfo) {
            if (!$cminfo->uservisible || !isset($records[$instanceid])) {
                continue;
            }
            $vt = $records[$instanceid];
            $mode = isset($vt->displaymode) ? (int)$vt->displaymode : 0;
            $rawaccent = videotrack_sanitize_accentcolor($vt->accentcolor ?? '');
            $accent = ($rawaccent !== '') ? $rawaccent : '#0ea5e9';
            $prog = $DB->get_record('videotrack_progress', ['videotrackid' => $vt->id, 'userid' => $USER->id]);
            $pct = $prog ? (int)$prog->highestpercent : 0;

            $items[(int)$cminfo->id] = [
                'cmid' => (int)$cminfo->id,
                'mode' => $mode,
                'name' => format_string($vt->name),
                'accent' => $accent,
                'pct' => $pct,
                'watchlabel' => get_string('watchvideo', 'mod_videotrack'),
                'progresslabel' => get_string('progresstitle', 'mod_videotrack'),
                'popupurl' => (new moodle_url('/mod/videotrack/view.php', ['id' => $cminfo->id, 'inpopup' => 1]))->out(false),
                'embedurl' => (new moodle_url('/mod/videotrack/view.php', ['id' => $cminfo->id, 'embed' => 1]))->out(false),
            ];
        }

        if (empty($items)) {
            return;
        }

        $injected = true;
        $PAGE->requires->css(new moodle_url('/mod/videotrack/styles.css'));

        $json = json_encode($items);
        $js = '(function(){' .
            'window.vtCourseItems=' . $json . ';' .
            'window.addEventListener("message",function(e){' .
                'if(e.data&&e.data.type==="vt-resize"){' .
                    'var f=document.getElementById("vt-inline-iframe-"+e.data.cmid);' .
                    'if(f&&e.data.height>200){f.style.height=e.data.height+"px";}' .
                '}' .
            '},false);' .
            'window.videotrackClosePopup=function(){' .
                'var modals=document.querySelectorAll(".vt-course-modal-backdrop");' .
                'for(var i=0;i<modals.length;i++){' .
                    'var m=modals[i];' .
                    'if(m.style.display!=="none"){' .
                        'var ifr=m.querySelector("iframe");' .
                        'if(ifr){' .
                            'try{' .
                                'var doc=ifr.contentWindow?ifr.contentWindow.document:null;' .
                                'var pctEl=doc?doc.getElementById("vt-progress-text"):null;' .
                                'var cmid=m.id?parseInt(m.id.replace("vt-popup-modal-",""),10):0;' .
                                'if(pctEl&&cmid&&window.vtCourseItems&&window.vtCourseItems[cmid]){' .
                                    'var newPct=parseInt(pctEl.textContent,10);' .
                                    'if(!isNaN(newPct)){' .
                                        'window.vtCourseItems[cmid].pct=newPct;' .
                                        'var li=document.getElementById("module-"+cmid);' .
                                        'if(li){' .
                                            'var st=li.querySelector(".vt-subtile-progress-meta strong, .vt-course-card-progress-top strong");' .
                                            'if(st)st.textContent=newPct+"%";' .
                                            'var fl=li.querySelector(".vt-subtile-progress-fill, .vt-course-card-bar > div");' .
                                            'if(fl)fl.style.width=newPct+"%";' .
                                        '}' .
                                    '}' .
                                '}' .
                            '}catch(err){}' .
                            'ifr.src="about:blank";' .
                        '}' .
                        'm.style.display="none";' .
                    '}' .
                '}' .
                'document.body.style.overflow="";' .
            '};' .
            'window.videotrackOpenPopup=function(cmid,url,title){' .
                'var cfg=window.vtCourseItems&&window.vtCourseItems[cmid]?window.vtCourseItems[cmid]:null;' .
                'var finalUrl=cfg?cfg.popupurl:url;' .
                'var finalTitle=cfg?cfg.name:(title||"");' .
                'var m=document.getElementById("vt-popup-modal-"+cmid);' .
                'if(!m){' .
                    'm=document.createElement("div");' .
                    'm.id="vt-popup-modal-"+cmid;' .
                    'm.className="vt-course-modal-backdrop";' .
                    'm.style.display="none";' .
                    'var dlg=document.createElement("div");' .
                    'dlg.className="vt-course-modal-dialog";' .
                    'var hdr=document.createElement("div");' .
                    'hdr.className="vt-course-modal-header";' .
                    'var ttl=document.createElement("span");' .
                    'ttl.className="vt-course-modal-title";' .
                    'var cls=document.createElement("button");' .
                    'cls.type="button";' .
                    'cls.className="vt-course-modal-close";' .
                    'cls.innerHTML="&times;";' .
                    'hdr.appendChild(ttl);' .
                    'hdr.appendChild(cls);' .
                    'var bdy=document.createElement("div");' .
                    'bdy.className="vt-course-modal-body";' .
                    'var ifr=document.createElement("iframe");' .
                    'ifr.id="vt-popup-iframe-"+cmid;' .
                    'ifr.src="about:blank";' .
                    'ifr.setAttribute("allowfullscreen","true");' .
                    'ifr.setAttribute("allow","autoplay; fullscreen");' .
                    'bdy.appendChild(ifr);' .
                    'dlg.appendChild(hdr);' .
                    'dlg.appendChild(bdy);' .
                    'm.appendChild(dlg);' .
                    'document.body.appendChild(m);' .
                '}else if(m.parentNode!==document.body){' .
                    'document.body.appendChild(m);' .
                '}' .
                'var t=m.querySelector(".vt-course-modal-title");' .
                'if(t)t.textContent=finalTitle;' .
                'var f=m.querySelector("iframe")||document.getElementById("vt-popup-iframe-"+cmid);' .
                'if(f)f.src=finalUrl;' .
                'm.style.display="flex";' .
                'document.body.style.overflow="hidden";' .
                'return false;' .
            '};' .
            'var findModuleLi=function(cmid){' .
                'var el=document.getElementById("module-"+cmid);' .
                'if(el)return el;' .
                'var list=document.querySelectorAll("li.activity.videotrack");' .
                'for(var i=0;i<list.length;i++){' .
                    'if(list[i].dataset&&String(list[i].dataset.cmid)===String(cmid))return list[i];' .
                '}' .
                'return null;' .
            '};' .
            'var upgradeTilesSubtiles=function(){' .
                'if(!window.vtCourseItems||document.body.classList.contains("editing"))return;' .
                'Object.keys(window.vtCourseItems).forEach(function(cmid){' .
                    'var cfg=window.vtCourseItems[cmid];' .
                    'var li=findModuleLi(cmid);' .
                    'if(!li||li.dataset.vtUpgraded==="1")return;' .
                    'if(!li.classList.contains("subtile"))return;' .
                    'if(cfg.mode===2){' .
                        'li.dataset.vtUpgraded="1";' .
                        'li.classList.remove("subtile");' .
                        'li.classList.add("vt-tiles-fullwidth-row");' .
                        'if(li.parentNode){li.parentNode.appendChild(li);}' .
                        'var wrap=document.createElement("div");' .
                        'wrap.className="vt-course-inline-embed";' .
                        'var ifr=document.createElement("iframe");' .
                        'ifr.id="vt-inline-iframe-"+cmid;' .
                        'ifr.src=cfg.embedurl;' .
                        'ifr.className="vt-course-inline-iframe";' .
                        'ifr.setAttribute("allowfullscreen","true");' .
                        'ifr.setAttribute("allow","autoplay; fullscreen");' .
                        'ifr.setAttribute("scrolling","no");' .
                        'wrap.appendChild(ifr);' .
                        'li.innerHTML="";' .
                        'li.appendChild(wrap);' .
                    '}else{' .
                        'li.dataset.vtUpgraded="1";' .
                        'li.classList.add("vt-enhanced-subtile");' .
                        'li.style.setProperty("--vt-accent",cfg.accent);' .
                        'var bar=document.createElement("div");' .
                        'bar.className="vt-subtile-progress-wrap";' .
                        'var meta=document.createElement("div");' .
                        'meta.className="vt-subtile-progress-meta";' .
                        'var sp=document.createElement("span");' .
                        'sp.className="vt-subtile-video-title";' .
                        'sp.textContent=cfg.name;' .
                        'sp.title=cfg.name;' .
                        'var st=document.createElement("strong");' .
                        'st.textContent=cfg.pct+"%";' .
                        'meta.appendChild(sp);' .
                        'meta.appendChild(st);' .
                        'var tr=document.createElement("div");' .
                        'tr.className="vt-subtile-progress-track";' .
                        'var fl=document.createElement("div");' .
                        'fl.className="vt-subtile-progress-fill";' .
                        'fl.style.width=cfg.pct+"%";' .
                        'tr.appendChild(fl);' .
                        'bar.appendChild(meta);' .
                        'bar.appendChild(tr);' .
                        'li.appendChild(bar);' .
                    '}' .
                '});' .
            '};' .
            'document.addEventListener("click",function(ev){' .
                'if(ev.target.closest(".vt-course-modal-close")||ev.target.classList.contains("vt-course-modal-backdrop")){' .
                    'ev.preventDefault();' .
                    'ev.stopPropagation();' .
                    'window.videotrackClosePopup();' .
                    'return;' .
                '}' .
                'if(!window.vtCourseItems||document.body.classList.contains("editing"))return;' .
                'var li=ev.target.closest("li.activity.videotrack");' .
                'if(!li)return;' .
                'if(ev.target.closest(".activity-actions, .editing, .dropdown, .cm-edit-action, .completioncheckbox, [data-action]"))return;' .
                'var cmid=0;' .
                'if(li.id&&li.id.indexOf("module-")===0){cmid=parseInt(li.id.replace("module-",""),10);}' .
                'if(!cmid&&li.dataset&&li.dataset.cmid){cmid=parseInt(li.dataset.cmid,10);}' .
                'if(cmid&&window.vtCourseItems[cmid]&&window.vtCourseItems[cmid].mode===1){' .
                    'ev.preventDefault();' .
                    'ev.stopPropagation();' .
                    'if(ev.stopImmediatePropagation)ev.stopImmediatePropagation();' .
                    'window.videotrackOpenPopup(cmid);' .
                '}' .
            '},true);' .
            'document.addEventListener("keydown",function(ev){' .
                'if(ev.key==="Escape"||ev.keyCode===27){window.videotrackClosePopup();}' .
            '});' .
            'upgradeTilesSubtiles();' .
            'setInterval(upgradeTilesSubtiles,400);' .
        '})();';

        $PAGE->requires->js_init_code($js);
    } catch (Throwable $e) {
        // Ignore if course context is not ready.
    }
}

/**
 * Hook called by Moodle on every course page load (including format_tiles) to extend course navigation.
 *
 * @param navigation_node $navigation The navigation node.
 * @param stdClass $course The course object.
 * @param context $context The course context.
 * @return void
 */
function videotrack_extend_navigation_course($navigation, $course, $context) {
    if (!empty($course->id)) {
        videotrack_inject_course_js((int)$course->id);
    }
}

/**
 * Checks whether a URL belongs strictly to an allowed HTTPS HeyGen host.
 *
 * @param string $url The URL to validate.
 * @return bool True if the URL uses HTTPS and its host is on the allowlist.
 */
function videotrack_is_allowed_heygen_url(string $url): bool {
    $parts = parse_url($url);
    if (!is_array($parts)) {
        return false;
    }

    $allowed = ['app.heygen.com'];
    return !empty($parts['scheme'])
        && strtolower($parts['scheme']) === 'https'
        && !empty($parts['host'])
        && in_array(strtolower($parts['host']), $allowed, true);
}

/**
 * Tries to extract a direct, public mp4 link from a HeyGen URL.
 *
 * @param string $url The original URL
 * @param curl|null $curlclient Optional curl instance (for unit testing/mocking)
 * @return string The mp4 URL if found, otherwise the original URL.
 */
function videotrack_extract_heygen_url($url, ?curl $curlclient = null) {
    if (!is_string($url) || !videotrack_is_allowed_heygen_url($url)) {
        return $url;
    }

    global $CFG;
    require_once($CFG->libdir . '/filelib.php');

    $curl = $curlclient ?? new curl();
    $curl->setopt([
        'CURLOPT_USERAGENT' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 ' .
            '(KHTML, like Gecko) Chrome/91.0.4472.124 Safari/537.36',
        'CURLOPT_TIMEOUT' => 10,
        'CURLOPT_FOLLOWLOCATION' => false,
        'CURLOPT_MAXREDIRS' => 0,
    ]);
    $content = $curl->get($url);

    if (is_string($content) && preg_match_all('#https://resource2[.]heygen[.]ai[^"<>]+?[.]mp4#i', $content, $matches)) {
        if (!empty($matches[0])) {
            $extracted = $matches[0][0];
            $unicodeamp = chr(92) . 'u0026';
            $extracted = str_replace($unicodeamp, '&', $extracted);
            return $extracted;
        }
    }

    return $url;
}

/**
 * Processes the video URL to extract direct links for supported platforms.
 *
 * @param string $url The original URL
 * @return string The processed URL
 */
function videotrack_process_video_url($url) {
    $url = videotrack_extract_heygen_url($url);

    if (strpos($url, 'drive.google.com') !== false) {
        $fileid = '';
        if (preg_match('#/file/d/([a-zA-Z0-9_-]+)#', $url, $matches)) {
            $fileid = $matches[1];
        } else if (preg_match('#[?&]id=([a-zA-Z0-9_-]+)#', $url, $matches)) {
            $fileid = $matches[1];
        }

        if (!empty($fileid)) {
            $url = 'https://drive.google.com/file/d/' . $fileid . '/preview';
        }
    }

    return $url;
}

/**
 * Add a new instance of the videotrack activity.
 *
 * @param stdClass $videotrack The activity instance object.
 * @param mod_videotrack_mod_form|null $mform The form.
 * @return int The ID of the new instance.
 */
function videotrack_add_instance($videotrack, $mform = null) {
    global $DB;
    videotrack_ensure_schema_columns();

    $videotrack->timecreated = time();
    $videotrack->timemodified = $videotrack->timecreated;
    $videotrack->isgdrive = !empty($videotrack->isgdrive) ? 1 : 0;
    $videotrack->focusmode = !empty($videotrack->focusmode) ? 1 : 0;
    $videotrack->accentcolor = videotrack_sanitize_accentcolor($videotrack->accentcolor ?? '');
    $videotrack->displaymode = isset($videotrack->displaymode) ? (int)$videotrack->displaymode : 0;
    $videotrack->targetpercent = isset($videotrack->targetpercent) ? (int)$videotrack->targetpercent : 0;

    if (!empty($videotrack->videourl)) {
        $videotrack->videourl = videotrack_process_video_url($videotrack->videourl);
    }

    if (isset($videotrack->manualtime_h) || isset($videotrack->manualtime_m) || isset($videotrack->manualtime_s)) {
        $h = (int)($videotrack->manualtime_h ?? 0);
        $m = (int)($videotrack->manualtime_m ?? 0);
        $s = (int)($videotrack->manualtime_s ?? 0);
        $videotrack->manualtime = ($h * 3600) + ($m * 60) + $s;
    }

    $id = $DB->insert_record('videotrack', $videotrack);

    if (!empty($videotrack->coursemodule) && $videotrack->displaymode > 0) {
        videotrack_sync_cm_showdescription((int)$videotrack->coursemodule, (int)$videotrack->displaymode);
    }

    $context = context_module::instance($videotrack->coursemodule);

    if (isset($videotrack->video)) {
        file_save_draft_area_files(
            $videotrack->video,
            $context->id,
            'mod_videotrack',
            'video',
            0,
            ['subdirs' => 0, 'maxfiles' => 1]
        );
    }

    if (isset($videotrack->captions)) {
        file_save_draft_area_files(
            $videotrack->captions,
            $context->id,
            'mod_videotrack',
            'captions',
            0,
            ['subdirs' => 0, 'maxfiles' => 1]
        );
    }

    if (!empty($videotrack->course)) {
        rebuild_course_cache($videotrack->course, true);
    }

    return $id;
}

/**
 * Update an existing instance of the videotrack activity.
 *
 * @param stdClass $videotrack The activity instance object.
 * @param mod_videotrack_mod_form|null $mform The form.
 * @return bool True on success.
 */
function videotrack_update_instance($videotrack, $mform = null) {
    global $DB;
    videotrack_ensure_schema_columns();

    $videotrack->timemodified = time();
    $videotrack->id = $videotrack->instance;
    $videotrack->isgdrive = !empty($videotrack->isgdrive) ? 1 : 0;
    $videotrack->focusmode = !empty($videotrack->focusmode) ? 1 : 0;
    $videotrack->accentcolor = videotrack_sanitize_accentcolor($videotrack->accentcolor ?? '');
    $videotrack->displaymode = isset($videotrack->displaymode) ? (int)$videotrack->displaymode : 0;
    if (isset($videotrack->targetpercent)) {
        $videotrack->targetpercent = (int)$videotrack->targetpercent;
    }

    if (!empty($videotrack->videourl)) {
        $videotrack->videourl = videotrack_process_video_url($videotrack->videourl);
    }

    if (isset($videotrack->manualtime_h) || isset($videotrack->manualtime_m) || isset($videotrack->manualtime_s)) {
        $h = (int)($videotrack->manualtime_h ?? 0);
        $m = (int)($videotrack->manualtime_m ?? 0);
        $s = (int)($videotrack->manualtime_s ?? 0);
        $videotrack->manualtime = ($h * 3600) + ($m * 60) + $s;
    }

    $DB->update_record('videotrack', $videotrack);

    if (!empty($videotrack->coursemodule) && $videotrack->displaymode > 0) {
        videotrack_sync_cm_showdescription((int)$videotrack->coursemodule, (int)$videotrack->displaymode);
    }

    $context = context_module::instance($videotrack->coursemodule);

    if (isset($videotrack->video)) {
        file_save_draft_area_files(
            $videotrack->video,
            $context->id,
            'mod_videotrack',
            'video',
            0,
            ['subdirs' => 0, 'maxfiles' => 1]
        );
    }

    if (isset($videotrack->captions)) {
        file_save_draft_area_files(
            $videotrack->captions,
            $context->id,
            'mod_videotrack',
            'captions',
            0,
            ['subdirs' => 0, 'maxfiles' => 1]
        );
    }

    if (!empty($videotrack->course)) {
        rebuild_course_cache($videotrack->course, true);
    }

    return true;
}

/**
 * Delete an instance of the videotrack activity.
 *
 * @param int $id The ID of the instance.
 * @return bool True on success.
 */
function videotrack_delete_instance($id) {
    global $DB;
    if (!$videotrack = $DB->get_record('videotrack', ['id' => $id])) {
        return true;
    }

    $DB->delete_records('videotrack_progress', ['videotrackid' => $videotrack->id]);
    $DB->delete_records('videotrack_reactions', ['videotrackid' => $videotrack->id]);

    $cm = get_coursemodule_from_instance('videotrack', $id);
    if ($cm) {
        $context = context_module::instance($cm->id);
        $fs = get_file_storage();
        $fs->delete_area_files($context->id, 'mod_videotrack', 'video');
        $fs->delete_area_files($context->id, 'mod_videotrack', 'captions');
    }

    $DB->delete_records('videotrack', ['id' => $videotrack->id]);
    return true;
}

/**
 * Converts SubRip (.srt) subtitle content into WebVTT (.vtt) format on the fly.
 *
 * @param string $srtcontent Raw SRT file content.
 * @return string Valid WebVTT content.
 */
function videotrack_convert_srt_to_vtt(string $srtcontent): string {
    $bom = chr(0xEF) . chr(0xBB) . chr(0xBF);
    if (strpos($srtcontent, $bom) === 0) {
        $srtcontent = substr($srtcontent, 3);
    }
    $cr = chr(13);
    $lf = chr(10);
    $srtcontent = str_replace([$cr . $lf, $cr], $lf, $srtcontent);
    $vttbody = preg_replace(
        '/([0-9]{2}:[0-9]{2}:[0-9]{2}),([0-9]{3})[ ]*-->[ ]*([0-9]{2}:[0-9]{2}:[0-9]{2}),([0-9]{3})/',
        '$1.$2 --> $3.$4',
        $srtcontent
    );
    return 'WEBVTT' . $lf . $lf . ltrim($vttbody);
}

/**
 * Serves the files from the videotrack file areas.
 *
 * @param stdClass $course The course object.
 * @param stdClass $cm The course module object.
 * @param context $context The context.
 * @param string $filearea The file area.
 * @param array $args The arguments.
 * @param bool $forcedownload Whether to force download.
 * @param array $options Options.
 * @return bool False if file not found, otherwise does not return.
 */
function mod_videotrack_pluginfile($course, $cm, $context, $filearea, $args, $forcedownload, array $options = []) {
    if ($context->contextlevel != CONTEXT_MODULE) {
        return false;
    }
    require_login($course, true, $cm);
    if (!in_array($filearea, ['video', 'captions'], true)) {
        return false;
    }

    $itemid = (int)array_shift($args);

    if (empty($args)) {
        return false;
    }

    $filename = array_pop($args);
    $filepath = empty($args) ? '/' : '/' . implode('/', $args) . '/';

    $fs = get_file_storage();
    $file = $fs->get_file($context->id, 'mod_videotrack', $filearea, $itemid, $filepath, $filename);

    if (!$file || $file->is_directory()) {
        return false;
    }

    if ($filearea === 'captions' && strtolower(pathinfo($filename, PATHINFO_EXTENSION)) === 'srt') {
        $vttcontent = videotrack_convert_srt_to_vtt($file->get_content());
        send_file($vttcontent, pathinfo($filename, PATHINFO_FILENAME) . '.vtt', 0, 0, true, false, 'text/vtt; charset=utf-8');
        return true;
    }

    send_stored_file($file, 0, 0, $forcedownload, $options);
}

/**
 * Indicates API features that the videotrack supports.
 *
 * @param string $feature The feature to check.
 * @return mixed True if supported, null if unknown.
 */
function videotrack_supports($feature) {
    switch ($feature) {
        case FEATURE_MOD_INTRO:
            return true;
        case FEATURE_SHOW_DESCRIPTION:
            return true;
        case FEATURE_COMPLETION_TRACKS_VIEWS:
            return true;
        case FEATURE_COMPLETION_HAS_RULES:
            return true;
        case FEATURE_GRADE_HAS_GRADE:
            return false;
        case FEATURE_BACKUP_MOODLE2:
            return true;
        case FEATURE_MOD_PURPOSE:
            return MOD_PURPOSE_CONTENT;
        default:
            return null;
    }
}

/**
 * Callback used by Moodle course cache (modinfo) to populate custom completion rules and display mode.
 *
 * @param stdClass $coursemodule The coursemodule object.
 * @return cached_cm_info|false
 */
function videotrack_get_coursemodule_info($coursemodule) {
    global $DB;

    $dbparams = ['id' => $coursemodule->instance];
    if (!$videotrack = $DB->get_record('videotrack', $dbparams)) {
        return false;
    }

    $displaymode = isset($videotrack->displaymode) ? (int)$videotrack->displaymode : 0;

    if ($displaymode > 0 && empty($coursemodule->showdescription)) {
        videotrack_sync_cm_showdescription((int)$coursemodule->id, $displaymode);
        $coursemodule->showdescription = 1;
    }

    $result = new cached_cm_info();
    $result->name = $videotrack->name;

    if ($coursemodule->showdescription) {
        $result->content = format_module_intro('videotrack', $videotrack, $coursemodule->id, false);
    }

    if ($coursemodule->completion == COMPLETION_TRACKING_AUTOMATIC && (int)$videotrack->targetpercent > 0) {
        $result->customdata['customcompletionrules']['targetpercent'] = (int)$videotrack->targetpercent;
    }

    $result->customdata['displaymode'] = $displaymode;

    if ($displaymode === 1) {
        $popupurl = (new moodle_url('/mod/videotrack/view.php', ['id' => $coursemodule->id, 'inpopup' => 1]))->out(false);
        $result->onclick = 'if(window.videotrackOpenPopup){return window.videotrackOpenPopup(' .
            (int)$coursemodule->id . ',"' . s($popupurl) . '",this.textContent||"");}';
    }

    return $result;
}

/**
 * Alias for videotrack_get_coursemodule_info.
 *
 * @param stdClass $coursemodule The coursemodule object.
 * @return cached_cm_info|false
 */
function mod_videotrack_get_coursemodule_info($coursemodule) {
    return videotrack_get_coursemodule_info($coursemodule);
}

/**
 * Dynamic course module info callback (Moodle allows $cm->set_on_click ONLY here, never in _cm_info_view).
 *
 * @param cm_info $cm Course module info object.
 * @return void
 */
function videotrack_cm_info_dynamic(cm_info $cm) {
    $customdata = $cm->customdata;
    $displaymode = (is_array($customdata) && isset($customdata['displaymode'])) ? (int)$customdata['displaymode'] : 0;
    if ($displaymode === 1) {
        $popupurl = (new moodle_url('/mod/videotrack/view.php', ['id' => $cm->id, 'inpopup' => 1]))->out(false);
        $cm->set_on_click(
            'if(window.videotrackOpenPopup){return window.videotrackOpenPopup(' .
            (int)$cm->id . ',"' . s($popupurl) . '",this.textContent||"");}'
        );
    }
}

/**
 * Renders the custom course page view when displaymode is 1 (Card + Popup) or 2 (Embedded player on course page).
 * Only calls $cm->set_content() as required by Moodle's cm_info API.
 *
 * @param cm_info $cm Course module info object.
 * @return void
 */
function videotrack_cm_info_view(cm_info $cm) {
    global $DB, $USER;

    if (!$cm->uservisible) {
        return;
    }

    $videotrack = $DB->get_record('videotrack', ['id' => $cm->instance]);
    if (!$videotrack) {
        return;
    }

    $displaymode = isset($videotrack->displaymode) ? (int)$videotrack->displaymode : 0;
    if ($displaymode === 0) {
        return;
    }

    videotrack_inject_course_js((int)$cm->course);

    $rawaccent = videotrack_sanitize_accentcolor($videotrack->accentcolor ?? '');
    $accent = ($rawaccent !== '') ? $rawaccent : '#0ea5e9';
    $introhtml = ($cm->showdescription && trim((string)$videotrack->intro) !== '')
        ? format_module_intro('videotrack', $videotrack, $cm->id, false)
        : '';

    if ($displaymode === 1) {
        // Mode 1: Card on course page that opens the player in a clean modal popup.
        $progress = $DB->get_record('videotrack_progress', ['videotrackid' => $videotrack->id, 'userid' => $USER->id]);
        $pct = $progress ? (int)$progress->highestpercent : 0;
        $popupurl = (new moodle_url('/mod/videotrack/view.php', ['id' => $cm->id, 'inpopup' => 1]))->out(false);
        $watchlabel = get_string('watchvideo', 'mod_videotrack');
        $progresslabel = get_string('progresstitle', 'mod_videotrack');
        $modalid = 'vt-popup-modal-' . (int)$cm->id;
        $iframeid = 'vt-popup-iframe-' . (int)$cm->id;
        $cleanname = format_string($videotrack->name);
        $safetitleattr = s($cleanname);

        $html = $introhtml;
        $html .= '<div class="vt-course-card" style="--vt-accent: ' . s($accent) . ';">';
        $html .= '  <div class="vt-course-card-body">';
        $html .= '    <button type="button" class="vt-course-card-play" ' .
            'data-cmid="' . (int)$cm->id . '" data-modal="' . s($modalid) . '" data-iframe="' . s($iframeid) . '" ' .
            'data-src="' . s($popupurl) . '" data-title="' . $safetitleattr . '" data-flex="flex" data-ov="hidden" ' .
            'onclick="if(window.videotrackOpenPopup){return window.videotrackOpenPopup(this.dataset.cmid,this.dataset.src,this.dataset.title);}return false;">';
        $html .= '      <span class="vt-course-play-circle"><i class="fa fa-play"></i></span>';
        $html .= '      <span class="vt-course-play-text">' . s($watchlabel) . '</span>';
        $html .= '    </button>';
        $html .= '    <div class="vt-course-card-progress">';
        $html .= '      <div class="vt-course-card-progress-top">';
        $html .= '        <span>' . s($progresslabel) . '</span>';
        $html .= '        <strong>' . $pct . '%</strong>';
        $html .= '      </div>';
        $html .= '      <div class="vt-course-card-bar"><div style="width:' . $pct . '%;background:' . s($accent) . ';"></div></div>';
        $html .= '    </div>';
        $html .= '  </div>';
        $html .= '</div>';

        // Lightweight Modal Overlay for Popup Player.
        $html .= '<div id="' . $modalid . '" class="vt-course-modal-backdrop" style="display:none;" ' .
            'onclick="if(event.target===this&&window.videotrackClosePopup){window.videotrackClosePopup();}">';
        $html .= '  <div class="vt-course-modal-dialog">';
        $html .= '    <div class="vt-course-modal-header">';
        $html .= '      <span class="vt-course-modal-title">' . $cleanname . '</span>';
        $html .= '      <button type="button" class="vt-course-modal-close" ' .
            'onclick="if(window.videotrackClosePopup){window.videotrackClosePopup();}">&times;</button>';
        $html .= '    </div>';
        $html .= '    <div class="vt-course-modal-body">';
        $html .= '      <iframe id="' . $iframeid . '" src="about:blank" allowfullscreen="true" allow="autoplay; fullscreen"></iframe>';
        $html .= '    </div>';
        $html .= '  </div>';
        $html .= '</div>';

        $cm->set_content($html, true);
    } else if ($displaymode === 2) {
        // Mode 2: Player embedded directly on the course page via isolated responsive iframe.
        $embedurl = (new moodle_url('/mod/videotrack/view.php', ['id' => $cm->id, 'embed' => 1]))->out(false);
        $iframeid = 'vt-inline-iframe-' . (int)$cm->id;

        $html = $introhtml;
        $html .= '<div class="vt-course-inline-embed">';
        $html .= '  <iframe id="' . $iframeid . '" src="' . s($embedurl) . '" class="vt-course-inline-iframe" ' .
            'data-card=".videotrack-card" data-unit="px" ' .
            'onload="try{var d=this.contentWindow.document;var c=d.querySelector(this.dataset.card);var r=c?c.getBoundingClientRect():null;var h=r?Math.ceil(r.bottom)+20:Math.max(d.body.scrollHeight,d.documentElement.scrollHeight);if(h>250)this.style.height=h+this.dataset.unit;}catch(e){}" ' .
            'allowfullscreen="true" allow="autoplay; fullscreen" scrolling="no"></iframe>';
        $html .= '</div>';

        $cm->set_content($html, true);
    }
}

/**
 * Extends settings secondary course/activity navigation (tabs) with report.php for teachers.
 *
 * @param settings_navigation $settingsnav The settings navigation object.
 * @param navigation_node|null $node The navigation node.
 */
function videotrack_extend_settings_navigation(settings_navigation $settingsnav, ?navigation_node $node = null) {
    global $PAGE;
    $cm = $PAGE->cm;

    if (!$cm || !$node) {
        return;
    }

    $context = context_module::instance($cm->id);
    if (has_capability('moodle/course:manageactivities', $context)) {
        $url = new moodle_url('/mod/videotrack/report.php', ['id' => $cm->id]);

        $reportnode = $node->add(
            get_string('report', 'mod_videotrack'),
            $url,
            navigation_node::TYPE_SETTING,
            null,
            'videotrackreport',
            new pix_icon('i/report', '')
        );

        $reportnode->showinflatnavigation = true;
    }
}
