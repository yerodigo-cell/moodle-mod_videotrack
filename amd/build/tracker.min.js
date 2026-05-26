/**
 * Módulo AMD para rastrear el progreso del video
 */
define(['jquery', 'core/config'], function($, cfg) {
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
                    $.post(cfg.wwwroot + '/mod/videotrack/ajax.php', {
                        cmid: cmid,
                        percent: floorPercent,
                        sesskey: cfg.sesskey
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

                    // Enviar progreso al servidor cada 5% de incremento o al llegar al 100%
                    var floorPercent = Math.floor(highestPercent);
                    if (floorPercent >= 100 || (floorPercent - lastSavedPercent >= 5)) {
                        saveProgress(floorPercent);
                    }
                }
            };

            if (!isYouTube) {
                var video = document.getElementById('videotrack-player');
                if (video) {
                    video.addEventListener('seeking', function() {
                        if (isFreeNavigation) return; 
                        if (isForcing || video.duration <= 0) return;
                        
                        var recordedMax = (highestPercent / 100) * video.duration;
                        if (recordedMax > maxAllowedTime) maxAllowedTime = recordedMax;

                        if (video.currentTime > maxAllowedTime + 1) {
                            isForcing = true;
                            video.currentTime = maxAllowedTime;
                            setTimeout(function(){ isForcing = false; }, 50);
                        }
                    });

                    video.addEventListener('timeupdate', function() {
                        if (video.duration <= 0) return;
                        
                        if (isFreeNavigation) {
                            updateUI((video.currentTime / video.duration) * 100);
                            return;
                        }

                        var recordedMax = (highestPercent / 100) * video.duration;
                        if (recordedMax > maxAllowedTime) maxAllowedTime = recordedMax;

                        if (video.currentTime > maxAllowedTime + 1) {
                            isForcing = true;
                            video.currentTime = maxAllowedTime;
                            setTimeout(function(){ isForcing = false; }, 50);
                        } else if (video.currentTime > maxAllowedTime && !video.seeking) {
                            maxAllowedTime = video.currentTime;
                            updateUI((maxAllowedTime / video.duration) * 100);
                        }
                    });

                    // Guardar progreso al pausar
                    video.addEventListener('pause', function() {
                        saveProgress(highestPercent);
                    });

                    // Garantizar 100% y guardar al terminar el video
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

                function onPlayerStateChange(event) {
                    var duration = window.ytPlayer.getDuration();
                    var currentTime = window.ytPlayer.getCurrentTime();
                    
                    if (!isFreeNavigation) {
                        var recordedMax = (highestPercent / 100) * duration;
                        if (recordedMax > maxAllowedTime) maxAllowedTime = recordedMax;

                        if (event.data == YT.PlayerState.BUFFERING || event.data == YT.PlayerState.PLAYING) {
                            if (currentTime > maxAllowedTime + 1.5) {
                                window.ytPlayer.seekTo(maxAllowedTime, true);
                            }
                        }
                    }

                    if (event.data == YT.PlayerState.PLAYING) {
                        if (window.vtCheckTimer) clearInterval(window.vtCheckTimer);
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
                        if (window.vtCheckTimer) clearInterval(window.vtCheckTimer);
                        
                        // Guardar progreso al pausar
                        if (event.data == YT.PlayerState.PAUSED) {
                            saveProgress(highestPercent);
                        }
                    }

                    // Garantizar 100% y guardar al terminar el video de YouTube
                    if (event.data == YT.PlayerState.ENDED || event.data === 0) {
                        updateUI(100);
                        saveProgress(100);
                    }
                }
            }
        }
    };
});