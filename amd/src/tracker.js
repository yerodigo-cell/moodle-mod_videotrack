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
 * @copyright  2026 Yeison Díaz
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
/* global YT */
define(['jquery', 'core/ajax'], function($, ajax) {
    return {
        init: function(cmid, targetPercent, isYouTube, videoId, currentPercent) {
            var highestPercent = currentPercent || 0;
            var lastSavedPercent = highestPercent;
            var completed = highestPercent >= targetPercent;
            var maxAllowedTime = 0;
            var isForcing = false;

            var isFreeNavigation = (targetPercent <= 0);

            var saveProgress = function(percent) {
                var floorPercent = Math.floor(percent);
                if (floorPercent > lastSavedPercent) {
                    lastSavedPercent = floorPercent;
                    ajax.call([{
                        methodname: 'mod_videotrack_save_progress',
                        args: {
                            cmid: cmid,
                            percent: floorPercent
                        }
                    }])[0].fail(function(ex) {
                        console.error('VideoTrack AJAX Error:', ex);
                        alert('Error saving progress: ' + ex.message);
                    });
                }
            };

            var updateUI = function(percent) {
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

            var setupResumeButton = function(getDurationFn, playFn) {
                $(document).off('click', '#vt-resume-btn');
                $(document).on('click', '#vt-resume-btn', function(e) {
                    e.preventDefault();
                    var $resumeBtn = $(this);
                    var duration = getDurationFn();
                    
                    var executeResume = function(dur) {
                        var resumeTime = (highestPercent / 100) * dur;
                        playFn(resumeTime);
                        $resumeBtn.fadeOut();
                    };

                    if (duration && duration > 0 && !isNaN(duration)) {
                        executeResume(duration);
                    } else {
                        // For HTML5, load first
                        if (!isYouTube) {
                            var v = document.getElementById('videotrack-player');
                            if (v) {
                                v.addEventListener('loadedmetadata', function() {
                                    executeResume(v.duration);
                                }, {once: true});
                                v.preload = "metadata";
                                v.load();
                            }
                        } else {
                            if (window.ytPlayer && window.ytPlayer.playVideo) {
                                window.ytPlayer.playVideo();
                                var ytInterval = setInterval(function() {
                                    var ytDur = window.ytPlayer.getDuration();
                                    if (ytDur && ytDur > 0) {
                                        clearInterval(ytInterval);
                                        executeResume(ytDur);
                                    }
                                }, 200);
                            }
                        }
                    }
                });
            };

            if (!isYouTube) {
                var video = document.getElementById('videotrack-player');
                if (video) {
                    setupResumeButton(
                        function() { return video.duration; },
                        function(time) { 
                            if (video.readyState >= 1) {
                                video.currentTime = time; 
                                video.play(); 
                            } else {
                                video.addEventListener('loadedmetadata', function() {
                                    video.currentTime = time;
                                    video.play();
                                }, {once: true});
                                video.load();
                            }
                        }
                    );

                    video.addEventListener('play', function() {
                        $('#vt-resume-btn').fadeOut();
                    });

                    video.addEventListener('seeking', function() {
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
                            setTimeout(function() {
                                isForcing = false;
                            }, 50);
                        } else if (video.currentTime > maxAllowedTime && !video.seeking) {
                            maxAllowedTime = video.currentTime;
                            updateUI((maxAllowedTime / video.duration) * 100);
                        }
                    });

                    // Save progress on pause.
                    video.addEventListener('pause', function() {
                        saveProgress(highestPercent);
                    });

                    // Ensure 100% and save when video ends.
                    video.addEventListener('ended', function() {
                        updateUI(100);
                        saveProgress(100);
                    });
                }
            } else {
                var initYTPlayer = function() {
                    window.ytPlayer = new YT.Player('youtube-player', {
                        videoId: videoId,
                        playerVars: {
                            'playsinline': 1,
                            'rel': 0
                        },
                        events: {
                            'onStateChange': onPlayerStateChange
                        }
                    });

                    setupResumeButton(
                        function() { return (window.ytPlayer && window.ytPlayer.getDuration) ? window.ytPlayer.getDuration() : 0; },
                        function(time) { if (window.ytPlayer) { window.ytPlayer.seekTo(time, true); window.ytPlayer.playVideo(); } }
                    );
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

                /**
                 * Callback for when player state changes.
                 *
                 * @param {Object} event YT event.
                 */
                var onPlayerStateChange = function(event) {
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
                        $('#vt-resume-btn').fadeOut();
                        if (window.vtCheckTimer) {
                            clearInterval(window.vtCheckTimer);
                        }
                        window.vtCheckTimer = setInterval(function() {
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
            }
        }
    };
});