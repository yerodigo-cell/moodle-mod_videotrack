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
 * AMD module to handle custom video controls.
 *
 * @module     mod_videotrack/controls
 * @copyright  2026 EduPlugins Studio
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
define(['jquery'], function($) {
    return {
        init: function(isYouTube) {
            var playPauseBtn = $('#vt-play-pause-btn');
            var playPauseIcon = playPauseBtn.find('i');
            var muteBtn = $('#vt-mute-btn');
            var muteIcon = muteBtn.find('i');
            var ccBtn = $('#vt-cc-btn');
            var customCaptions = $('#vt-custom-captions');
            var fullscreenBtn = $('#vt-fullscreen-btn');
            var seekSlider = $('#vt-seek-slider');
            var timeDisplay = $('#vt-time-display');
            var wrapper = $('.vt-player-wrapper')[0];
            var video = document.getElementById('videotrack-player');
            var isSeeking = false;
            var captionsEnabled = true;
            var parsedCues = [];

            // Start in paused visual state so controls are visible initially
            $(wrapper).addClass('vt-is-paused');

            var parseTimestamp = function(timeStr) {
                var clean = $.trim(timeStr).replace(',', '.');
                var parts = clean.split(':');
                if (parts.length === 3) {
                    return (parseFloat(parts[0]) * 3600) + (parseFloat(parts[1]) * 60) + parseFloat(parts[2]);
                } else if (parts.length === 2) {
                    return (parseFloat(parts[0]) * 60) + parseFloat(parts[1]);
                }
                return 0;
            };

            var parseSubtitleText = function(rawText) {
                var cues = [];
                if (!rawText) {
                    return cues;
                }
                var bom = String.fromCharCode(0xFEFF);
                var cr = String.fromCharCode(13);
                var lf = String.fromCharCode(10);
                var tab = String.fromCharCode(9);
                var normalized = rawText;
                if (normalized.indexOf(bom) === 0) {
                    normalized = normalized.substring(1);
                }
                normalized = normalized.split(cr + lf).join(lf).split(cr).join(lf);
                var blocks = normalized.split(new RegExp(lf + '[ ' + tab + ']*' + lf));
                var wsRegex = new RegExp('[ ' + tab + ']+');
                for (var i = 0; i < blocks.length; i++) {
                    var lines = $.trim(blocks[i]).split(lf);
                    if (lines.length === 0) {
                        continue;
                    }
                    var timeLineIdx = -1;
                    for (var j = 0; j < lines.length; j++) {
                        if (lines[j].indexOf('-->') !== -1) {
                            timeLineIdx = j;
                            break;
                        }
                    }
                    if (timeLineIdx !== -1) {
                        var times = lines[timeLineIdx].split('-->');
                        if (times.length >= 2) {
                            var start = parseTimestamp(times[0]);
                            var endPart = $.trim(times[1]).split(wsRegex)[0];
                            var end = parseTimestamp(endPart);
                            var textLines = lines.slice(timeLineIdx + 1);
                            var cueText = $.trim(textLines.join(lf));
                            if (cueText !== '') {
                                cues.push({
                                    start: start,
                                    end: end,
                                    text: cueText
                                });
                            }
                        }
                    }
                }
                return cues;
            };

            // Load and parse .vtt or .srt directly so subtitles work reliably in all browsers
            var trackEl = document.getElementById('vt-captions-track');
            if (trackEl && trackEl.getAttribute('src')) {
                $.ajax({
                    url: trackEl.getAttribute('src'),
                    dataType: 'text',
                    success: function(data) {
                        parsedCues = parseSubtitleText(data);
                        renderActiveCues();
                    }
                });
            }

            var formatTime = function(seconds, forceHours) {
                if (isNaN(seconds)) {
                    return forceHours ? "0:00:00" : "0:00";
                }
                var h = Math.floor(seconds / 3600);
                var m = Math.floor((seconds % 3600) / 60);
                var s = Math.floor(seconds % 60);

                if (h > 0 || forceHours) {
                    return (h < 10 ? "0" : "") + h + ":" + (m < 10 ? "0" : "") + m + ":" + (s < 10 ? "0" : "") + s;
                } else {
                    return m + ":" + (s < 10 ? "0" : "") + s;
                }
            };

            var updateTimeUI = function(currentTime, duration) {
                var isResumeActive = $('#vt-resume-overlay').is(':visible');
                if (isResumeActive) {
                    var targetPercent = Number($('#vt-progress-text').text().replace('%', '')) || 0;
                    seekSlider.val(targetPercent);
                    seekSlider.css('--vt-progress', targetPercent + '%');
                    var targetTime = duration > 0 ? (targetPercent / 100) * duration : 0;
                    var hasHrs = duration >= 3600;
                    timeDisplay.text(formatTime(targetTime, hasHrs) + ' / ' + formatTime(duration, hasHrs));
                    return;
                }

                if (!isSeeking && duration > 0) {
                    var pct = (currentTime / duration) * 100;
                    seekSlider.val(pct);
                    seekSlider.css('--vt-progress', pct + '%');
                }
                var hasHours = duration >= 3600;
                timeDisplay.text(formatTime(currentTime, hasHours) + ' / ' + formatTime(duration, hasHours));
            };

            var renderActiveCues = function() {
                if (!video || !captionsEnabled) {
                    customCaptions.hide().empty();
                    return;
                }

                if (video.textTracks && video.textTracks.length > 0) {
                    if (video.textTracks[0].mode !== 'hidden') {
                        video.textTracks[0].mode = 'hidden';
                    }
                }

                var ct = video.currentTime || 0;
                var lines = [];
                var lf = String.fromCharCode(10);
                var i;

                if (parsedCues.length > 0) {
                    for (i = 0; i < parsedCues.length; i++) {
                        if (ct >= parsedCues[i].start && ct <= parsedCues[i].end) {
                            var safeParsed = $('<div>').text(parsedCues[i].text).html().split(lf).join('<br>');
                            lines.push(safeParsed);
                        }
                    }
                } else if (video.textTracks && video.textTracks.length > 0 && video.textTracks[0].activeCues) {
                    var activeCues = video.textTracks[0].activeCues;
                    for (i = 0; i < activeCues.length; i++) {
                        if (activeCues[i].text) {
                            var safeNative = $('<div>').text(activeCues[i].text).html().split(lf).join('<br>');
                            lines.push(safeNative);
                        }
                    }
                }

                if (lines.length > 0) {
                    customCaptions.html('<span class="vt-caption-box">' + lines.join('<br>') + '</span>').show();
                } else {
                    customCaptions.hide().empty();
                }
            };

            var togglePlay = function() {
                if ($('#vt-resume-overlay').is(':visible')) {
                    if (window.videotrackResume) {
                        window.videotrackResume();
                    }
                    return;
                }

                if (isYouTube && window.ytPlayer && window.ytPlayer.getPlayerState) {
                    var state = window.ytPlayer.getPlayerState();
                    if (state === 1) {
                        window.ytPlayer.pauseVideo();
                    } else {
                        window.ytPlayer.playVideo();
                    }
                } else if (video) {
                    if (video.paused) {
                        video.play();
                    } else {
                        video.pause();
                    }
                }
            };

            var toggleMute = function() {
                if (isYouTube && window.ytPlayer) {
                    if (window.ytPlayer.isMuted()) {
                        window.ytPlayer.unMute();
                        muteIcon.removeClass('fa-volume-off').addClass('fa-volume-up');
                    } else {
                        window.ytPlayer.mute();
                        muteIcon.removeClass('fa-volume-up').addClass('fa-volume-off');
                    }
                } else if (video) {
                    video.muted = !video.muted;
                    if (video.muted) {
                        muteIcon.removeClass('fa-volume-up').addClass('fa-volume-off');
                    } else {
                        muteIcon.removeClass('fa-volume-off').addClass('fa-volume-up');
                    }
                }
            };

            var toggleCaptions = function() {
                captionsEnabled = !captionsEnabled;
                if (captionsEnabled) {
                    ccBtn.addClass('vt-cc-active');
                } else {
                    ccBtn.removeClass('vt-cc-active');
                }
                renderActiveCues();
            };

            var markFullscreenTransition = function() {
                window.vtFullscreenTransitionUntil = Date.now() + 1200;
                try {
                    if (window.parent && window.parent !== window) {
                        window.parent.vtFullscreenTransitionUntil = Date.now() + 1200;
                    }
                } catch (e) {
                    // Ignore cross-origin frame access errors.
                }
            };

            var toggleFullscreen = function() {
                markFullscreenTransition();
                if (!document.fullscreenElement && !document.webkitFullscreenElement && !document.msFullscreenElement) {
                    if (wrapper.requestFullscreen) {
                        wrapper.requestFullscreen();
                    } else if (wrapper.webkitRequestFullscreen) {
                        wrapper.webkitRequestFullscreen();
                    } else if (wrapper.msRequestFullscreen) {
                        wrapper.msRequestFullscreen();
                    }
                    fullscreenBtn.find('i').removeClass('fa-expand').addClass('fa-compress');
                } else {
                    if (document.exitFullscreen) {
                        document.exitFullscreen();
                    } else if (document.webkitExitFullscreen) {
                        document.webkitExitFullscreen();
                    } else if (document.msExitFullscreen) {
                        document.msExitFullscreen();
                    }
                    fullscreenBtn.find('i').removeClass('fa-compress').addClass('fa-expand');
                }
            };

            var onSeek = function() {
                var percent = seekSlider.val();
                if (isYouTube && window.ytPlayer) {
                    var duration = window.ytPlayer.getDuration();
                    window.ytPlayer.seekTo((percent / 100) * duration, true);
                } else if (video && video.duration) {
                    video.currentTime = (percent / 100) * video.duration;
                    renderActiveCues();
                }
            };

            // Event Listeners
            playPauseBtn.on('click', togglePlay);
            muteBtn.on('click', toggleMute);
            ccBtn.on('click', toggleCaptions);
            fullscreenBtn.on('click', toggleFullscreen);

            seekSlider.on('input', function() {
                isSeeking = true;
                seekSlider.css('--vt-progress', $(this).val() + '%');
            });
            seekSlider.on('change', function() {
                onSeek();
                isSeeking = false;
            });

            // HTML5 Video Events
            if (video) {
                if (video.textTracks && video.textTracks.length > 0) {
                    var track = video.textTracks[0];
                    track.mode = 'hidden';
                    track.addEventListener('cuechange', renderActiveCues);
                }

                video.addEventListener('error', function() {
                    var error = video.error;
                    var errorMsg = "Video Error Code: " + (error ? error.code : 'Unknown');
                    if (error && error.code === 1) {
                        errorMsg += " (ABORTED)";
                    }
                    if (error && error.code === 2) {
                        errorMsg += " (NETWORK)";
                    }
                    if (error && error.code === 3) {
                        errorMsg += " (DECODE)";
                    }
                    if (error && error.code === 4) {
                        errorMsg += " (SRC_NOT_SUPPORTED)";
                    }

                    var errorDiv = $('<div style="position:absolute; top:50%; left:50%; ' +
                        'transform:translate(-50%, -50%); color:white; ' +
                        'background:rgba(255,0,0,0.8); padding:15px; ' +
                        'border-radius:5px; text-align:center; z-index:9999; ' +
                        'font-weight:bold;"></div>');
                    errorDiv.text(errorMsg);
                    $(wrapper).append(errorDiv);
                });

                $(video).on('click', togglePlay);

                video.addEventListener('play', function() {
                    playPauseIcon.removeClass('fa-play').addClass('fa-pause');
                    $(wrapper).removeClass('vt-is-paused');
                });
                video.addEventListener('pause', function() {
                    playPauseIcon.removeClass('fa-pause').addClass('fa-play');
                    $(wrapper).addClass('vt-is-paused');
                });
                video.addEventListener('timeupdate', function() {
                    updateTimeUI(video.currentTime, video.duration);
                    renderActiveCues();
                });
                video.addEventListener('loadedmetadata', function() {
                    updateTimeUI(video.currentTime, video.duration);
                    if (video.textTracks && video.textTracks.length > 0) {
                        video.textTracks[0].mode = 'hidden';
                        ccBtn.addClass('vt-cc-active');
                    }
                    renderActiveCues();
                });
            }

            // YouTube Event Polling
            if (isYouTube) {
                setInterval(function() {
                    if (window.ytPlayer && window.ytPlayer.getPlayerState) {
                        var state = window.ytPlayer.getPlayerState();
                        if (state === 1) {
                            playPauseIcon.removeClass('fa-play').addClass('fa-pause');
                            $(wrapper).removeClass('vt-is-paused');
                        } else {
                            playPauseIcon.removeClass('fa-pause').addClass('fa-play');
                            $(wrapper).addClass('vt-is-paused');
                        }

                        var ct = window.ytPlayer.getCurrentTime() || 0;
                        var dur = window.ytPlayer.getDuration() || 0;
                        updateTimeUI(ct, dur);
                    }
                }, 300);
            }

            // Show/hide controls smoothly (Touch & Mouse)
            var controlTimeout;
            var wakeControls = function() {
                $(wrapper).addClass('vt-active-controls');
                clearTimeout(controlTimeout);

                var isPlaying = false;
                if (isYouTube && window.ytPlayer && window.ytPlayer.getPlayerState) {
                    isPlaying = (window.ytPlayer.getPlayerState() === 1);
                } else if (video) {
                    isPlaying = !video.paused;
                }

                if (isPlaying) {
                    controlTimeout = setTimeout(function() {
                        $(wrapper).removeClass('vt-active-controls');
                    }, 3000);
                }
            };

            $(wrapper).on('mousemove touchstart click', wakeControls);
            if (video) {
                $(video).on('play', wakeControls);
                $(video).on('pause', wakeControls);
            }

            // Fullscreen change listener to update icon and CSS classes
            var handleFsChange = function() {
                markFullscreenTransition();
                var isFs = document.fullscreenElement || document.webkitFullscreenElement || document.msFullscreenElement;
                if (isFs) {
                    $(wrapper).addClass('vt-is-fullscreen');
                    fullscreenBtn.find('i').removeClass('fa-expand').addClass('fa-compress');
                } else {
                    $(wrapper).removeClass('vt-is-fullscreen');
                    fullscreenBtn.find('i').removeClass('fa-compress').addClass('fa-expand');
                }
            };
            document.addEventListener('fullscreenchange', handleFsChange);
            document.addEventListener('webkitfullscreenchange', handleFsChange);
            document.addEventListener('MSFullscreenChange', handleFsChange);
        }
    };
});
