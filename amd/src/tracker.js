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
define(['jquery', 'core/ajax', 'core/notification'], function($, ajax, notification) {
    return {
        init: function(
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
            var highestPercent = Number(currentPercent) || 0;
            if (highestPercent >= 98) {
                highestPercent = 100;
            }
            var lastSavedPercent = highestPercent;
            var lastSavedTime = Number(highestTime) || 0;
            var completed = targetPercent > 0 ? (highestPercent >= targetPercent) : false;
            var maxAllowedTime = Number(highestTime) || 0;
            var isForcing = false;
            var isResuming = false;
            var resumeTimer = null;
            var pendingResumePercent = 0;
            var pendingResumeSeconds = 0;
            var isWindowFocused = true;
            var isFreeNavigation = (targetPercent <= 0);

            window.videotrackAllowResumeTo = function(targetSeconds, fallbackPercent) {
                var sec = Number(targetSeconds) || 0;
                var pct = Number(fallbackPercent) || 0;
                if (sec > maxAllowedTime) {
                    maxAllowedTime = sec;
                }
                pendingResumeSeconds = sec;
                pendingResumePercent = pct;
                isResuming = true;
                if (resumeTimer) {
                    clearTimeout(resumeTimer);
                }
                resumeTimer = setTimeout(function() {
                    isResuming = false;
                }, 4000);
            };

            // Detect if current player contains a Google Drive preview iframe.
            var gdriveIframe = document.querySelector('.vt-player-wrapper iframe[src*="drive.google.com"]');
            var isGDriveIframe = !!gdriveIframe;

            /**
             * Checks whether the browser is currently in or transitioning into/out of Fullscreen mode.
             *
             * @return {Boolean} True if fullscreen or within the transition grace window.
             */
            var isFullscreenOrTransitioning = function() {
                var now = Date.now();
                if (window.vtFullscreenTransitionUntil && now < window.vtFullscreenTransitionUntil) {
                    return true;
                }
                if (document.fullscreenElement || document.webkitFullscreenElement || document.msFullscreenElement) {
                    return true;
                }
                if (document.querySelector('.vt-player-wrapper.vt-is-fullscreen')) {
                    return true;
                }
                try {
                    if (window.parent && window.parent !== window) {
                        if (window.parent.vtFullscreenTransitionUntil && now < window.parent.vtFullscreenTransitionUntil) {
                            return true;
                        }
                        var pDoc = window.parent.document;
                        if (pDoc && (pDoc.fullscreenElement || pDoc.webkitFullscreenElement || pDoc.msFullscreenElement)) {
                            return true;
                        }
                    }
                } catch (e) {
                    // Ignore cross-origin frame access errors.
                }
                return false;
            };

            var markFsTransition = function() {
                window.vtFullscreenTransitionUntil = Date.now() + 1200;
                try {
                    if (window.parent && window.parent !== window) {
                        window.parent.vtFullscreenTransitionUntil = Date.now() + 1200;
                    }
                } catch (e) {
                    // Ignore cross-origin frame access errors.
                }
            };

            var bindFullscreenListeners = function() {
                document.addEventListener('fullscreenchange', markFsTransition);
                document.addEventListener('webkitfullscreenchange', markFsTransition);
                document.addEventListener('MSFullscreenChange', markFsTransition);
                try {
                    if (window.parent && window.parent !== window && window.parent.document) {
                        window.parent.document.addEventListener('fullscreenchange', markFsTransition);
                        window.parent.document.addEventListener('webkitfullscreenchange', markFsTransition);
                        window.parent.document.addEventListener('MSFullscreenChange', markFsTransition);
                    }
                } catch (e) {
                    // Ignore cross-origin frame access errors.
                }
            };

            bindFullscreenListeners();

            /**
             * Reclaims focus from cross-origin iframes (YouTube/Shorts) back to our window
             * so subsequent window.blur events always fire reliably every time the user switches windows.
             */
            var reclaimWindowFocus = function() {
                setTimeout(function() {
                    if (isFullscreenOrTransitioning()) {
                        return;
                    }
                    try {
                        if (document.activeElement && document.activeElement.tagName === 'IFRAME') {
                            document.activeElement.blur();
                        }
                        window.focus();
                    } catch (e) {
                        // Ignore cross-origin frame access errors.
                    }
                }, 150);
            };

            var setFocusBadgeState = function(isPausedByFocus) {
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

            var triggerFocusPause = function() {
                if (!focusMode) {
                    return;
                }
                if (isGDriveIframe) {
                    setFocusBadgeState(true);
                    reclaimWindowFocus();
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

            var initFocusListeners = function() {
                var handleVisibilityChange = function() {
                    var topHidden = false;
                    try {
                        if (window.top && window.top.document && window.top.document.hidden) {
                            topHidden = true;
                        }
                    } catch (e) {
                        // Ignore cross-origin frame access errors.
                    }

                    if (document.hidden || topHidden) {
                        isWindowFocused = false;
                        triggerFocusPause();
                    } else {
                        isWindowFocused = true;
                        if (isManualTrack || isGDriveIframe) {
                            setFocusBadgeState(false);
                        }
                    }
                };

                var handleWindowBlur = function() {
                    setTimeout(function() {
                        if (window.vtFullscreenTransitionUntil && Date.now() < window.vtFullscreenTransitionUntil) {
                            return;
                        }
                        if (isFullscreenOrTransitioning() && !document.hidden) {
                            return;
                        }
                        var activeEl = document.activeElement;
                        if (activeEl && activeEl.tagName === 'IFRAME' &&
                            $(activeEl).closest('.vt-player-wrapper').length > 0 &&
                            !document.hidden && document.hasFocus()) {
                            isWindowFocused = true;
                            setFocusBadgeState(false);
                            reclaimWindowFocus();
                            return;
                        }
                        if (!document.hasFocus()) {
                            isWindowFocused = false;
                            triggerFocusPause();
                        }
                    }, 180);
                };

                var handleWindowFocus = function() {
                    isWindowFocused = true;
                    if ((isManualTrack || isGDriveIframe) && !document.hidden) {
                        setFocusBadgeState(false);
                    }
                };

                document.addEventListener('visibilitychange', handleVisibilityChange);
                window.addEventListener('blur', handleWindowBlur);
                window.addEventListener('focus', handleWindowFocus);

                try {
                    if (window.parent && window.parent !== window) {
                        window.parent.addEventListener('blur', handleWindowBlur);
                        window.parent.addEventListener('focus', handleWindowFocus);
                        if (window.parent.document) {
                            window.parent.document.addEventListener('visibilitychange', handleVisibilityChange);
                        }
                    }
                } catch (e) {
                    // Ignore cross-origin frame access errors.
                }
            };

            if (focusMode) {
                initFocusListeners();
                if (isGDriveIframe || isManualTrack) {
                    setInterval(function () {
                        if (isFullscreenOrTransitioning() && !document.hidden) {
                            return;
                        }
                        var hasFocusNow = !document.hidden && document.hasFocus();
                        if (!hasFocusNow) {
                            try {
                                if (window.top && window.top !== window && window.top.document && window.top.document.hasFocus()) {
                                    hasFocusNow = true;
                                }
                            } catch (e) { }
                        }
                        if (!hasFocusNow && isWindowFocused) {
                            isWindowFocused = false;
                            triggerFocusPause();
                        } else if (hasFocusNow && !isWindowFocused) {
                            isWindowFocused = true;
                            setFocusBadgeState(false);
                        }
                    }, 400);
                }
            }

            var isSaving = false;
            var queuedSave = null;

            var notifyParentProgress = function(pct) {
                try {
                    if (window.parent && window.parent !== window) {
                        window.parent.postMessage({
                            type: 'vt-progress',
                            cmid: cmid,
                            percent: pct
                        }, '*');
                    }
                } catch (e) {
                    // Ignore cross-origin frame access errors.
                }
            };

            var getLatestPlaybackTime = function() {
                var currentTime = maxAllowedTime;
                if (isManualTrack) {
                    currentTime = maxAllowedTime;
                } else if (isYouTube && window.ytPlayer && typeof window.ytPlayer.getCurrentTime === 'function') {
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
                return Math.max(0, Math.floor(currentTime));
            };

            var saveProgress = function(percent, forceSave) {
                var floorPercent = Math.min(100, Math.max(0, Math.floor(percent)));
                if (floorPercent >= 98) {
                    floorPercent = 100;
                }
                var floorTime = getLatestPlaybackTime();

                var reqPercent = Math.max(floorPercent, Math.floor(highestPercent), lastSavedPercent);
                if (reqPercent >= 98) {
                    reqPercent = 100;
                }
                var reqTime = Math.max(floorTime, lastSavedTime);

                if (!forceSave && reqPercent <= lastSavedPercent && reqTime <= lastSavedTime) {
                    return;
                }

                if (isSaving) {
                    queuedSave = {
                        percent: reqPercent,
                        force: !!forceSave
                    };
                    return;
                }

                isSaving = true;
                notifyParentProgress(reqPercent);

                ajax.call([{
                    methodname: 'mod_videotrack_save_progress',
                    args: {
                        cmid: cmid,
                        percent: reqPercent,
                        currenttime: reqTime
                    }
                }])[0].done(function(res) {
                    isSaving = false;
                    if (res && res.success) {
                        if (reqPercent > lastSavedPercent) {
                            lastSavedPercent = reqPercent;
                        }
                        if (reqTime > lastSavedTime) {
                            lastSavedTime = reqTime;
                        }
                    }
                    if (queuedSave) {
                        var next = queuedSave;
                        queuedSave = null;
                        saveProgress(next.percent, next.force);
                    }
                }).fail(function(ex) {
                    isSaving = false;
                    notification.exception(ex);
                });
            };

            window.videotrackFlushProgress = function() {
                saveProgress(highestPercent, true);
            };

            window.addEventListener('pagehide', function() {
                saveProgress(highestPercent, true);
            });

            var updateUI = function(percent, durationSec, currentSec) {
                var clamped = Math.min(100, Math.max(0, percent));
                if (clamped >= 98 || (durationSec && durationSec > 2 && currentSec && (durationSec - currentSec <= 1.2))) {
                    clamped = 100;
                }
                if (clamped > highestPercent) {
                    highestPercent = clamped;
                    var floorPercent = Math.floor(highestPercent);

                    $('#vt-progress-bar').css('width', floorPercent + '%');
                    $('#vt-progress-text').text(floorPercent + '%');
                    notifyParentProgress(floorPercent);

                    var justCompleted = false;
                    if (!isFreeNavigation && floorPercent >= targetPercent && !completed) {
                        completed = true;
                        justCompleted = true;
                        $('#vt-success-msg').removeClass('d-none').hide().fadeIn('slow');
                    }

                    if (justCompleted || floorPercent >= 100 || (floorPercent - lastSavedPercent >= 5)) {
                        saveProgress(floorPercent, justCompleted || floorPercent >= 100);
                    }
                }
            };

            if (isManualTrack && manualTime > 0) {
                maxAllowedTime = lastSavedTime;
                setTimeout(function() {
                    var manualTimer = setInterval(function() {
                        if (document.hidden || (focusMode && !isWindowFocused)) {
                            return;
                        }

                        maxAllowedTime += 1;
                        var currentP = (maxAllowedTime / manualTime) * 100;
                        updateUI(currentP, manualTime, maxAllowedTime);

                        if (currentP >= targetPercent || currentP >= 100 || completed) {
                            if (!completed) {
                                updateUI(Math.max(currentP, targetPercent), manualTime, maxAllowedTime);
                                saveProgress(Math.max(currentP, targetPercent), true);
                            }
                            if (currentP >= 100) {
                                clearInterval(manualTimer);
                            }
                        }
                    }, 1000);
                }, 1000);

                return;
            }

            if (isGDriveIframe) {
                return;
            }

            if (!isYouTube) {
                var video = document.getElementById('videotrack-player');
                if (video) {
                    video.addEventListener('loadedmetadata', function() {
                        if (video.duration > 0) {
                            var recordedMax = (highestPercent / 100) * video.duration;
                            if (recordedMax > maxAllowedTime) {
                                maxAllowedTime = recordedMax;
                            }
                        }
                    });

                    video.addEventListener('play', function() {
                        if (focusMode) {
                            setFocusBadgeState(false);
                        }
                    });

                    video.addEventListener('seeking', function() {
                        if (isFreeNavigation || isResuming) {
                            return;
                        }
                        if (isForcing || video.duration <= 0) {
                            return;
                        }

                        var recordedMax = (highestPercent / 100) * video.duration;
                        if (recordedMax > maxAllowedTime) {
                            maxAllowedTime = recordedMax;
                        }

                        if (video.currentTime > maxAllowedTime + 1.5) {
                            isForcing = true;
                            video.currentTime = maxAllowedTime;
                            setTimeout(function() {
                                isForcing = false;
                            }, 50);
                        }
                    });

                    video.addEventListener('timeupdate', function() {
                        if (video.duration <= 0) {
                            return;
                        }

                        if (isFreeNavigation) {
                            if (video.currentTime > maxAllowedTime) {
                                maxAllowedTime = video.currentTime;
                            }
                            updateUI((video.currentTime / video.duration) * 100, video.duration, video.currentTime);
                            return;
                        }

                        var recordedMax = (highestPercent / 100) * video.duration;
                        if (recordedMax > maxAllowedTime) {
                            maxAllowedTime = recordedMax;
                        }

                        if (!isResuming && video.currentTime > maxAllowedTime + 1.5) {
                            isForcing = true;
                            video.currentTime = maxAllowedTime;
                            setTimeout(function() {
                                isForcing = false;
                            }, 50);
                        } else if (video.currentTime > maxAllowedTime && !video.seeking) {
                            maxAllowedTime = video.currentTime;
                            updateUI((maxAllowedTime / video.duration) * 100, video.duration, maxAllowedTime);
                        }
                    });

                    // Save progress on pause.
                    video.addEventListener('pause', function() {
                        saveProgress(highestPercent, true);
                    });

                    // Ensure 100% and save when video ends.
                    video.addEventListener('ended', function() {
                        if (video.duration > 0 && video.duration > maxAllowedTime) {
                            maxAllowedTime = video.duration;
                        }
                        updateUI(100, video.duration, video.duration);
                        saveProgress(100, true);
                    });
                }
            } else {
                var applyPendingYtResume = function(state, duration, currentTime) {
                    var isActiveState = (state == YT.PlayerState.PLAYING || state == YT.PlayerState.BUFFERING);
                    var hasPending = (pendingResumeSeconds > 0 || pendingResumePercent > 0);
                    if (!isActiveState || !hasPending || !(duration > 0)) {
                        return false;
                    }
                    var desiredSec = Math.max(pendingResumeSeconds, (pendingResumePercent / 100) * duration);
                    if (desiredSec > maxAllowedTime) {
                        maxAllowedTime = desiredSec;
                    }
                    if (state == YT.PlayerState.PLAYING && currentTime < desiredSec - 1.5) {
                        pendingResumeSeconds = 0;
                        pendingResumePercent = 0;
                        window.ytPlayer.seekTo(desiredSec, true);
                        return true;
                    }
                    if (currentTime >= desiredSec - 1.5) {
                        pendingResumeSeconds = 0;
                        pendingResumePercent = 0;
                    }
                    return false;
                };

                var enforceYtSeekLimit = function(state, duration, currentTime) {
                    if (isFreeNavigation || isResuming || !(duration > 0)) {
                        return;
                    }
                    if (state == YT.PlayerState.BUFFERING || state == YT.PlayerState.PLAYING) {
                        if (currentTime > maxAllowedTime + 1.5) {
                            window.ytPlayer.seekTo(maxAllowedTime, true);
                        }
                    }
                };

                /**
                 * Callback for when player state changes.
                 *
                 * @param {Object} event YT event.
                 */
                var onPlayerStateChange = function(event) {
                    var duration = window.ytPlayer.getDuration();
                    var currentTime = window.ytPlayer.getCurrentTime();

                    if (duration > 0) {
                        var recordedMax = (highestPercent / 100) * duration;
                        if (recordedMax > maxAllowedTime) {
                            maxAllowedTime = recordedMax;
                        }
                    }

                    if (applyPendingYtResume(event.data, duration, currentTime)) {
                        return;
                    }

                    enforceYtSeekLimit(event.data, duration, currentTime);

                    if (event.data == YT.PlayerState.PLAYING) {
                        if (focusMode) {
                            isWindowFocused = true;
                            setFocusBadgeState(false);
                            reclaimWindowFocus();
                        }
                        if (window.vtCheckTimer) {
                            clearInterval(window.vtCheckTimer);
                        }
                        window.vtCheckTimer = setInterval(function() {
                            if (focusMode && !document.hasFocus()) {
                                if (isFullscreenOrTransitioning() && !document.hidden) {
                                    return;
                                }
                                var topHasFocus = false;
                                try {
                                    topHasFocus = !!(window.top && window.top.document && window.top.document.hasFocus());
                                } catch (e) {
                                    // Ignore cross-origin frame access errors.
                                }
                                if (!topHasFocus) {
                                    isWindowFocused = false;
                                    triggerFocusPause();
                                    return;
                                }
                            }
                            var eDuration = window.ytPlayer.getDuration();
                            var eCurrentTime = window.ytPlayer.getCurrentTime();
                            if (!eDuration || eDuration <= 0) {
                                return;
                            }
                            var recordedMaxTick = (highestPercent / 100) * eDuration;
                            if (recordedMaxTick > maxAllowedTime) {
                                maxAllowedTime = recordedMaxTick;
                            }
                            if (isFreeNavigation) {
                                if (eCurrentTime > maxAllowedTime) {
                                    maxAllowedTime = eCurrentTime;
                                }
                                updateUI((eCurrentTime / eDuration) * 100, eDuration, eCurrentTime);
                                return;
                            }
                            if (!isResuming && eCurrentTime > maxAllowedTime + 1.5) {
                                window.ytPlayer.seekTo(maxAllowedTime, true);
                            } else if (eCurrentTime > maxAllowedTime) {
                                maxAllowedTime = eCurrentTime;
                                updateUI((maxAllowedTime / eDuration) * 100, eDuration, maxAllowedTime);
                            }
                        }, 300);
                    } else {
                        if (window.vtCheckTimer) {
                            clearInterval(window.vtCheckTimer);
                        }
                        if (event.data == YT.PlayerState.PAUSED) {
                            saveProgress(highestPercent, true);
                        }
                    }

                    // Ensure 100% and save when YouTube video ends.
                    if (event.data == YT.PlayerState.ENDED || event.data === 0) {
                        if (duration > 0 && duration > maxAllowedTime) {
                            maxAllowedTime = duration;
                        }
                        updateUI(100, duration, duration);
                        saveProgress(100, true);
                    }
                };

                var initYTPlayer = function() {
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
                    window.onYouTubeIframeAPIReady = function() {
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
