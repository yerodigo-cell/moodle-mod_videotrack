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
            var fullscreenBtn = $('#vt-fullscreen-btn');
            var seekSlider = $('#vt-seek-slider');
            var timeDisplay = $('#vt-time-display');
            var wrapper = $('.vt-player-wrapper')[0];
            var video = document.getElementById('videotrack-player');
            var isSeeking = false;

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
                if (!isSeeking && duration > 0) {
                    var pct = (currentTime / duration) * 100;
                    seekSlider.val(pct);
                    seekSlider.css('--vt-progress', pct + '%');
                }
                var hasHours = duration >= 3600;
                timeDisplay.text(formatTime(currentTime, hasHours) + ' / ' + formatTime(duration, hasHours));
            };

            var togglePlay = function() {
                if (isYouTube && window.ytPlayer && window.ytPlayer.getPlayerState) {
                    var state = window.ytPlayer.getPlayerState();
                    if (state === 1) { // Playing
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

            var toggleFullscreen = function() {
                if (!document.fullscreenElement && !document.webkitFullscreenElement && !document.msFullscreenElement) {
                    if (wrapper.requestFullscreen) {
                        wrapper.requestFullscreen();
                    } else if (wrapper.webkitRequestFullscreen) { /* Safari */
                        wrapper.webkitRequestFullscreen();
                    } else if (wrapper.msRequestFullscreen) { /* IE11 */
                        wrapper.msRequestFullscreen();
                    }
                    fullscreenBtn.find('i').removeClass('fa-expand').addClass('fa-compress');
                } else {
                    if (document.exitFullscreen) {
                        document.exitFullscreen();
                    } else if (document.webkitExitFullscreen) { /* Safari */
                        document.webkitExitFullscreen();
                    } else if (document.msExitFullscreen) { /* IE11 */
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
                }
            };

            // Event Listeners
            playPauseBtn.on('click', togglePlay);
            muteBtn.on('click', toggleMute);
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

                // Clicking the video should also toggle play/pause
                $(video).on('click', togglePlay);

                video.addEventListener('play', function() {
                    playPauseIcon.removeClass('fa-play').addClass('fa-pause');
                });
                video.addEventListener('pause', function() {
                    playPauseIcon.removeClass('fa-pause').addClass('fa-play');
                });
                video.addEventListener('timeupdate', function() {
                    updateTimeUI(video.currentTime, video.duration);
                });
                video.addEventListener('loadedmetadata', function() {
                    updateTimeUI(video.currentTime, video.duration);
                });
            }

            // YouTube Event Polling
            if (isYouTube) {
                // Cannot natively bind click to iframe easily due to CORS,
                // but wrapper is above it partially or users can use the play button.

                setInterval(function() {
                    if (window.ytPlayer && window.ytPlayer.getPlayerState) {
                        var state = window.ytPlayer.getPlayerState();
                        if (state === 1) { // Playing
                            playPauseIcon.removeClass('fa-play').addClass('fa-pause');
                        } else {
                            playPauseIcon.removeClass('fa-pause').addClass('fa-play');
                        }

                        var ct = window.ytPlayer.getCurrentTime() || 0;
                        var dur = window.ytPlayer.getDuration() || 0;
                        updateTimeUI(ct, dur);
                    }
                }, 500);
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
