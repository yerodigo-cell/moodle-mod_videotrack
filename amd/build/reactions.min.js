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
 * AMD module to handle video reactions.
 *
 * @module     mod_videotrack/reactions
 * @copyright  2026 EduPlugins Studio
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
define(['jquery', 'core/ajax', 'core/notification'], function($, ajax, notification) {
    var reactionEmojis = {
        'like': '👍',
        'heart': '❤️',
        'laugh': '😂',
        'wow': '😲',
        'clap': '👏🏼',
        'ok': '👌🏼',
        'raisedhands': '🙌🏼',
        'thinking': '🤔',
        'rocket': '🚀'
    };

    return {
        init: function(cmid, isYouTube) {
            var loadedReactions = [];
            var displayedReactions = {}; // To prevent showing the same reaction multiple times

            // Fetch existing reactions from server
            ajax.call([{
                methodname: 'mod_videotrack_get_reactions',
                args: {cmid: cmid}
            }])[0].done(function(result) {
                if (result.reactions) {
                    loadedReactions = result.reactions;
                }
            }).fail(notification.exception);

            var getCurrentTime = function() {
                if (isYouTube && window.ytPlayer && window.ytPlayer.getCurrentTime) {
                    return Math.floor(window.ytPlayer.getCurrentTime());
                } else {
                    var video = document.getElementById('videotrack-player');
                    if (video) {
                        return Math.floor(video.currentTime);
                    }
                }
                return 0;
            };

            var getDuration = function() {
                if (isYouTube && window.ytPlayer && window.ytPlayer.getDuration) {
                    return window.ytPlayer.getDuration();
                } else {
                    var video = document.getElementById('videotrack-player');
                    if (video && video.duration) {
                        return video.duration;
                    }
                }
                return 0;
            };

            var displayFloatingReaction = function(reactionCode) {
                var emoji = reactionEmojis[reactionCode] || '👍';
                var container = $('#vt-floating-reactions');

                // Add a random slight horizontal offset
                var rightOffset = 20 + Math.random() * 40;

                var el = $('<div class="vt-floating-emoji" ' +
                    'style="right: ' + rightOffset + 'px; z-index: 9999;">' + emoji + '</div>');
                container.append(el);

                // Remove the element after animation completes (3 seconds)
                setTimeout(function() {
                    el.remove();
                }, 3000);
            };

            window.videotrackReact = function(reactionCode) {
                var currentTime = 0;
                try {
                    currentTime = getCurrentTime();
                } catch (err) {
                    // eslint-disable-next-line no-console
                    console.log('Error getting current time:', err);
                }

                // Display immediately for immediate feedback
                displayFloatingReaction(reactionCode);

                // Save to server
                ajax.call([{
                    methodname: 'mod_videotrack_save_reaction',
                    args: {
                        cmid: cmid,
                        reaction: reactionCode,
                        timeoffset: currentTime
                    }
                }])[0].done(function(response) {
                    // Add it to local array so it can be re-played if user seeks back
                    loadedReactions.push({
                        id: response.reactionid,
                        reaction: reactionCode,
                        timeoffset: currentTime
                    });
                }).fail(function(ex) {
                    // eslint-disable-next-line no-console
                    console.log('Error saving reaction:', ex);
                });
            };

            // Handle user clicking on reaction bar using event delegation
            $(document).on('click', '.vt-react-btn', function(e) {
                e.preventDefault();
                var btn = $(this);
                var reactionCode = btn.data('reaction');
                window.videotrackReact(reactionCode);
            });

            // Check loop for displaying existing reactions
            setInterval(function() {
                var currentTime = getCurrentTime();
                var duration = getDuration();

                var isPaused = false;
                if (isYouTube && window.ytPlayer && window.ytPlayer.getPlayerState) {
                    isPaused = (window.ytPlayer.getPlayerState() !== 1);
                } else if (!isYouTube) {
                    var video = document.getElementById('videotrack-player');
                    if (video) {
                        isPaused = video.paused;
                    }
                }
                if (isPaused) {
                    $('.vt-player-wrapper').addClass('vt-is-paused');
                } else {
                    $('.vt-player-wrapper').removeClass('vt-is-paused');
                }

                // Reset displayed states if user seeks back 2 seconds or more
                if (window.lastReactionTime && currentTime < window.lastReactionTime - 2) {
                    displayedReactions = {};
                }
                window.lastReactionTime = currentTime;

                for (var i = 0; i < loadedReactions.length; i++) {
                    var r = loadedReactions[i];

                    // Draw timeline marker if duration is known and marker not drawn
                    if (duration > 0 && !r.markerDrawn) {
                        var percentage = (r.timeoffset / duration) * 100;
                        var emoji = reactionEmojis[r.reaction] || '👍';
                        var markerId = 'vt-marker-' + r.id;
                        // Unified offset for both HTML5 and YouTube since we use a custom timeline
                        var verticalOffset = '0px';
                        if ($('#' + markerId).length === 0) {
                            var markerHtml = '<div id="' + markerId + '" ' +
                                'style="position: absolute; left: ' + percentage + '%; bottom: 0px; ' +
                                'font-size: 24px; --vt-y-offset: ' + verticalOffset + '; ' +
                                'transform: translate(-50%, ' + verticalOffset + '); z-index: 5; ' +
                                'text-shadow: 0 2px 4px rgba(0,0,0,0.5); ' +
                                'animation: vt-pop-in 0.4s ease-out forwards;">' + emoji + '</div>';
                            var marker = $(markerHtml);
                            $('#vt-reaction-markers').append(marker);
                        }
                        r.markerDrawn = true;
                    }

                    // If the reaction offset matches the current time and hasn't been displayed recently
                    if (r.timeoffset === currentTime && !displayedReactions[r.id]) {
                        displayFloatingReaction(r.reaction);
                        displayedReactions[r.id] = true;
                    }
                }
            }, 500); // Check every half a second
        }
    };
});
