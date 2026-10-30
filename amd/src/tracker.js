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
 * AMD module to track video progress.
 *
 * @module     mod_videotrack/tracker
 * @copyright  2026 EduPlugins Studio
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
/* global YT */
define(['jquery', 'core/ajax', 'core/notification'], function ($, ajax, notification) {
    return {
        init: function (
            cmid,
            targetPercent,
            isYouTube,
            videoId,
            currentPercent,
            highestTime,
            isManualTrack,
            manualTime,
            focusMode
        ) {
            var highestPercent = currentPercent || 0;
            var lastSavedPercent = highestPercent;
            var lastSavedTime = highestTime || 0;
            var completed = highestPercent >= targetPercent;
            var maxAllowedTime = 0;
            var isForcing = false;
            var isWindowFocused = true;
            var isFreeNavigation = (targetPercent <= 0);

            // Detect if current player contains a Google Drive preview iframe.
            var gdriveIframe = document.querySelector('.vt-player-wrapper iframe[src*="drive.google.com"]');
            var isGDriveIframe = !!gdriveIframe;
            var gdriveOriginalSrc = isGDriveIframe ? gdriveIframe.getAttribute('src') : '';

            /**
             * Reclaims focus from cross-origin iframes (YouTube/Shorts) back to our window
             * so subsequent window.blur events always fire reliably every time the user switches windows.
             */
            var reclaimWindowFocus = function () {
                setTimeout(function () {
                    try {
                        if (document.activeElement && document.activeElement.tagName === 'IFRAME') {
                            document.activeElement.blur();
                        }
                        window.focus();
                    } catch (e) { }
                }, 150);
            };

            var setFocusBadgeState = function (isPausedByFocus) {
                var $badge = $('#vt-focus-badge');
                if (!$badge.length) {
                    return;
                }
                var $text = $('#vt-focus-text');
                if (isPausedByFocus) {
                    $badge.addClass('vt-focus-paused');
                    $text.text($badge.attr('data-paused-text') || 'Paused — Focus Mode');
                } else {
                    $badge.removeClass('vt-focus-paused');
                    $text.text($badge.attr('data-default-text') || 'Focus Mode');
                }
            };

            /**
             * Shows a clean Focus Mode pause overlay for Google Drive iframes (since GDrive has no JS pause API).
             */
            var pauseGDriveByFocus = function () {
                if (!isGDriveIframe || !gdriveIframe) {
                    return;
                }
                if (gdriveIframe.getAttribute('src') === 'about:blank') {
                    return;
                }
                gdriveIframe.setAttribute('src', 'about:blank');
                setFocusBadgeState(true);

                var $overlay = $('#vt-gdrive-focus-overlay');
                if (!$overlay.length) {
                    var pausedLabel = $('#vt-focus-badge').attr('data-paused-text') || 'Paused — Focus Mode';
                    $overlay = $('<div id="vt-gdrive-focus-overlay"></div>').css({
                        position: 'absolute',
                        top: 0,
                        left: 0,
                        width: '100%',
                        height: '100%',
                        background: 'rgba(15, 23, 42, 0.86)',
                        backdropFilter: 'blur(4px)',
                        zIndex: 40,
                        display: 'flex',
                        flexDirection: 'column',
                        alignItems: 'center',
                        justifyContent: 'center',
                        gap: '14px',
                        color: '#ffffff',
                        textAlign: 'center',
                        padding: '20px'
                    });
                    var $btn = $('<button type="button" class="vt-gdrive-resume-btn"></button>').css({
                        background: 'var(--vt-accent, #0ea5e9)',
                        color: '#ffffff',
                        border: 'none',
                        borderRadius: '999px',
                        padding: '12px 28px',
                        fontSize: '1rem',
                        fontWeight: '700',
                        cursor: 'pointer',
                        display: 'inline-flex',
                        alignItems: 'center',
                        gap: '10px',
                        boxShadow: '0 4px 14px rgba(0, 0, 0, 0.35)'
                    }).html('<i class="fa fa-play"></i> <span>' + pausedLabel + '</span>');

                    $btn.on('click', function (e) {
                        e.preventDefault();
                        gdriveIframe.setAttribute('src', gdriveOriginalSrc);
                        $overlay.fadeOut(200);
                        isWindowFocused = true;
                        setFocusBadgeState(false);
                        reclaimWindowFocus();
                    });

                    $overlay.append($btn);
                    $('.vt-player-wrapper').append($overlay);
                } else {
                    $overlay.fadeIn(150);
                }
            };

            if (focusMode) {
                var triggerFocusPause = function () {
                    if (isGDriveIframe) {
                        pauseGDriveByFocus();
                        return;
                    }
                    if (!isYouTube && !isManualTrack) {
                        var v = document.getElementById('videotrack-player');
                        if (v && !v.paused && !v.ended) {
                            v.pause();
                            setFocusBadgeState(true);
                        }
                    } else if (isYouTube && window.ytPlayer && typeof window.ytPlayer.getPlayerState === 'function') {
                        var st = window.ytPlayer.getPlayerState();
                        if (st === YT.PlayerState.PLAYING || st === YT.PlayerState.BUFFERING || st === 1 || st === 3) {
                            window.ytPlayer.pauseVideo();
                            setFocusBadgeState(true);
                            reclaimWindowFocus();
                        }
                    } else if (isManualTrack) {
                        setFocusBadgeState(true);
                    }
                };

                var handleVisibilityChange = function () {
                    var topHidden = false;
                    try {
                        if (window.top && window.top.document && window.top.document.hidden) {
                            topHidden = true;
                        }
                    } catch (e) { }

                    if (document.hidden || topHidden) {
                        isWindowFocused = false;
                        triggerFocusPause();
                    } else {
                        isWindowFocused = true;
                        if (isManualTrack && !isGDriveIframe) {
                            setFocusBadgeState(false);
                        }
                    }
                };

                var handleWindowBlur = function () {
                    setTimeout(function () {
                        var activeEl = document.activeElement;
                        // If user just clicked inside our own player iframe while page is still focused, reclaim focus so next blur works.
                        if (activeEl && activeEl.tagName === 'IFRAME' &&
                            $(activeEl).closest('.vt-player-wrapper').length > 0 &&
                            !document.hidden && document.hasFocus()) {
                            if (!isGDriveIframe) {
                                reclaimWindowFocus();
                            }
                            return;
                        }
                        if (!document.hasFocus()) {
                            isWindowFocused = false;
                            triggerFocusPause();
                        }
                    }, 120);
                };

                var handleWindowFocus = function () {
                    isWindowFocused = true;
                    if (isManualTrack && !isGDriveIframe && !document.hidden) {
                        setFocusBadgeState(false);
                    }
                };

                document.addEventListener('visibilitychange', handleVisibilityChange);
                window.addEventListener('blur', handleWindowBlur);
                window.addEventListener('focus', handleWindowFocus);

                // Also attach to parent/top window when running inside course embed or modal popup iframe.
                try {
                    if (window.parent && window.parent !== window) {
                        window.parent.addEventListener('blur', handleWindowBlur);
                        window.parent.addEventListener('focus', handleWindowFocus);
                        if (window.parent.document) {
                            window.parent.document.addEventListener('visibilitychange', handleVisibilityChange);
                        }
                    }
                } catch (e) { }
            }

            var saveProgress = function (percent) {
                var floorPercent = Math.floor(percent);
                var currentTime = maxAllowedTime;

                if (isManualTrack) {
                    currentTime = maxAllowedTime;
                } else if (isYouTube && window.ytPlayer && window.ytPlayer.getCurrentTime) {
                    var ytTime = window.ytPlayer.getCurrentTime();
                    if (isFreeNavigation || ytTime > maxAllowedTime) {
                        currentTime = ytTime;
                    }
                } else {
                    var video = document.getElementById('videotrack-player');
                    if (video) {
                        var htmlTime = video.currentTime;
                        if (isFreeNavigation || htmlTime > maxAllowedTime) {
                            currentTime = htmlTime;
                        }
                    }
                }

                var floorTime = Math.floor(currentTime);

                if (floorPercent > lastSavedPercent || floorTime > lastSavedTime) {
                    lastSavedPercent = floorPercent;
                    lastSavedTime = floorTime;
                    ajax.call([{
                        methodname: 'mod_videotrack_save_progress',
                        args: {
                            cmid: cmid,
                            percent: floorPercent,
                            currenttime: Math.floor(currentTime)
                        }
                    }])[0].fail(notification.exception);
                }
            };

            var updateUI = function (percent) {
                if (percent > highestPercent) {
                    highestPercent = percent;

                    $('#vt-progress-bar').css('width', highestPercent + '%');
                    $('#vt-progress-text').text(Math.floor(highestPercent) + '%');

                    if (!isFreeNavigation && highestPercent >= targetPercent && !completed) {
                        completed = true;
                        $('#vt-success-msg').removeClass('d-none').hide().fadeIn('slow');
                    }

                    // Send progress to server every 5% increment or when reaching 100%.
                    var floorPercent = Math.floor(highestPercent);
                    if (floorPercent >= 100 || (floorPercent - lastSavedPercent >= 5)) {
                        saveProgress(floorPercent);
                    }
                }
            };

            if (isManualTrack && manualTime > 0) {
                maxAllowedTime = lastSavedTime; // Restore saved seconds
                setTimeout(function () {
                    var manualTimer = setInterval(function () {
                        if (document.hidden || (focusMode && !isWindowFocused)) {
                            return; // Pause timer if tab/window is not active
                        }

                        maxAllowedTime += 1; // 1 second elapsed
                        var currentP = (maxAllowedTime / manualTime) * 100;
                        updateUI(currentP);

                        if (currentP >= targetPercent || currentP >= 100 || completed) {
                            if (!completed) {
                                // Reached the required time
                                updateUI(Math.max(currentP, targetPercent));
                                saveProgress(Math.max(currentP, targetPercent));
                            }
                            if (currentP >= 100) {
                                clearInterval(manualTimer);
                            }
                        }
                    }, 1000);
                }, 1000); // Start 1 second after initialization

                return; // Stop here, don't execute native/YouTube trackers
            }

            if (isGDriveIframe) {
                return;
            }

            if (!isYouTube) {
                var video = document.getElementById('videotrack-player');
                if (video) {
                    video.addEventListener('play', function () {
                        if (focusMode) {
                            setFocusBadgeState(false);
                        }
                    });

                    video.addEventListener('seeking', function () {
                        if (isFreeNavigation) {
                            return;
                        }
                        if (isForcing || video.duration <= 0) {
                            return;
                        }

                        var recordedMax = (highestPercent / 100) * video.duration;
                        if (recordedMax > maxAllowedTime) {
                            maxAllowedTime = recordedMax;
                        }

                        if (video.currentTime > maxAllowedTime + 1) {
                            isForcing = true;
                            video.currentTime = maxAllowedTime;
                            setTimeout(function () {
                                isForcing = false;
                            }, 50);
                        }
                    });

                    video.addEventListener('timeupdate', function () {
                        if (video.duration <= 0) {
                            return;
                        }

                        if (isFreeNavigation) {
                            updateUI((video.currentTime / video.duration) * 100);
                            return;
                        }

                        var recordedMax = (highestPercent / 100) * video.duration;
                        if (recordedMax > maxAllowedTime) {
                            maxAllowedTime = recordedMax;
                        }

                        if (video.currentTime > maxAllowedTime + 1) {
                            isForcing = true;
                            video.currentTime = maxAllowedTime;
                            setTimeout(function () {
                                isForcing = false;
                            }, 50);
                        } else if (video.currentTime > maxAllowedTime && !video.seeking) {
                            maxAllowedTime = video.currentTime;
                            updateUI((maxAllowedTime / video.duration) * 100);
                        }
                    });

                    // Save progress on pause.
                    video.addEventListener('pause', function () {
                        saveProgress(highestPercent);
                    });

                    // Ensure 100% and save when video ends.
                    video.addEventListener('ended', function () {
                        updateUI(100);
                        saveProgress(100);
                    });
                }
            } else {
                /**
                 * Callback for when player state changes.
                 *
                 * @param {Object} event YT event.
                 */
                var onPlayerStateChange = function (event) {
                    var duration = window.ytPlayer.getDuration();
                    var currentTime = window.ytPlayer.getCurrentTime();

                    if (!isFreeNavigation) {
                        var recordedMax = (highestPercent / 100) * duration;
                        if (recordedMax > maxAllowedTime) {
                            maxAllowedTime = recordedMax;
                        }

                        if (event.data == YT.PlayerState.BUFFERING || event.data == YT.PlayerState.PLAYING) {
                            if (currentTime > maxAllowedTime + 1.5) {
                                window.ytPlayer.seekTo(maxAllowedTime, true);
                            }
                        }
                    }

                    if (event.data == YT.PlayerState.PLAYING) {
                        if (focusMode) {
                            isWindowFocused = true;
                            setFocusBadgeState(false);
                            // Reclaim focus from the YouTube/Short iframe so the next window switch triggers window.blur.
                            reclaimWindowFocus();
                        }
                        if (window.vtCheckTimer) {
                            clearInterval(window.vtCheckTimer);
                        }
                        window.vtCheckTimer = setInterval(function () {
                            if (focusMode && !document.hasFocus()) {
                                var topHasFocus = false;
                                try {
                                    topHasFocus = !!(window.top && window.top.document && window.top.document.hasFocus());
                                } catch (e) { }
                                if (!topHasFocus) {
                                    isWindowFocused = false;
                                    triggerFocusPause();
                                    return;
                                }
                            }
                            var eDuration = window.ytPlayer.getDuration();
                            var eCurrentTime = window.ytPlayer.getCurrentTime();
                            if (isFreeNavigation) {
                                updateUI((eCurrentTime / eDuration) * 100);
                                return;
                            }
                            if (eCurrentTime > maxAllowedTime + 1.5) {
                                window.ytPlayer.seekTo(maxAllowedTime, true);
                            } else if (eCurrentTime > maxAllowedTime) {
                                maxAllowedTime = eCurrentTime;
                                updateUI((maxAllowedTime / eDuration) * 100);
                            }
                        }, 500);
                    } else {
                        if (window.vtCheckTimer) {
                            clearInterval(window.vtCheckTimer);
                        }
                        // Save progress on pause.
                        if (event.data == YT.PlayerState.PAUSED) {
                            saveProgress(highestPercent);
                        }
                    }

                    // Ensure 100% and save when YouTube video ends.
                    if (event.data == YT.PlayerState.ENDED || event.data === 0) {
                        updateUI(100);
                        saveProgress(100);
                    }
                };

                var initYTPlayer = function () {
                    window.ytPlayer = new YT.Player('youtube-player', {
                        videoId: videoId,
                        playerVars: {
                            'playsinline': 1,
                            'rel': 0,
                            'controls': 0,
                            'disablekb': 1
                        },
                        events: {
                            'onStateChange': onPlayerStateChange
                        }
                    });
                };

                if (typeof window.YT === 'undefined' || typeof window.YT.Player === 'undefined') {
                    window.onYouTubeIframeAPIReady = function () {
                        initYTPlayer();
                    };
                    var tag = document.createElement('script');
                    tag.src = "https://www.youtube.com/iframe_api";
                    var firstScriptTag = document.getElementsByTagName('script')[0];
                    firstScriptTag.parentNode.insertBefore(tag, firstScriptTag);
                } else {
                    initYTPlayer();
                }
            }
        }
    };
});
