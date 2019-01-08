Voiceep = {
    _initialized: false,
    _api_url: 'https://voiceep.com/api/_PROJECT_ID_/',
    _project_id: '_PROJECT_ID_',
    _options: {
        callback: function(){}
    },
    _players: {},
    _active_player: null,
    _active_volume_wrapper: null,
    _active_volume_slider_wrapper: null,
    _active_volume_handle: null,
    _page_scroll_offset: 0,

    init: function(options) {
        if (!Voiceep._initialized) {
            Voiceep.includeCSS();
            Voiceep.initVolumeDragAndDrop();
            Voiceep._initialized = true;
        }

        if (typeof options == "undefined") {
            throw new Error("A selector must be specified when initializing Voiceep.");
        }

        if (typeof options == "string") {
            options = { content_selector: options };
        } else if (typeof options != "object") {
            throw new Error("The options parameter passed when initializing Voiceep must be an object.");
        }

        Voiceep.loadPlayer(options);
    },

    setPageScrollOffset: function(offset) {
        Voiceep._page_scroll_offset = offset;
    },

    includeCSS: function() {
        let head = document.querySelector('head');
        head.innerHTML = '<link rel="stylesheet" href="https://voiceep.com/embed.css" type="text/css" />' + head.innerHTML;
    },

    getCurrentUrl: function() {
        let canonicalMeta = document.querySelector('link[rel="canonical"][href]');
        let url = '';

        if (canonicalMeta && canonicalMeta.getAttribute('href')) {
            url = canonicalMeta.getAttribute('href');
        } else if (typeof location.protocol != 'undefined' && typeof location.host != 'undefined' && typeof location.pathname != 'undefined') {
            url = location.protocol + "//" + location.host + location.pathname;
        }

        if (!url) {
            url = window.location.href.replace(/^https?:\/\//g, '').split('#')[0];
            url = url.indexOf('?') != -1 ? url.substr(0, url.indexOf('?')) : url;
        }

        return url;
    },

    loadPlayer: function(options) {
        let url = (typeof options.url != "undefined" && options.url) ? options.url : Voiceep.getCurrentUrl();
        let lang = (typeof options.lang != "undefined" && options.lang) ? options.lang : null;
        let type = (typeof options.type != "undefined" && options.type) ? options.type : null;
        let text_to_speech_fallback = (typeof options.text_to_speech_fallback != "undefined" && options.text_to_speech_fallback) ? options.text_to_speech_fallback : false;
        let title = Voiceep.getTitle();

        if (!lang) {
            let html = document.querySelector('html');
            if (html) {
                lang = html.getAttribute('lang');
            }
        }

        if (lang.indexOf('-') == -1) {
            lang = null;
        }

        Voiceep.getText(options, function(text){
            let payload = {
                url: url,
                text: text,
                title: title,
                lang: lang,
                type: type,
                text_to_speech_fallback: text_to_speech_fallback
            };

            Voiceep.renderPlayer(options, function(playerId) {
                let player = Voiceep.getPlayer(playerId);
                Voiceep.fetchAudio(payload, playerId, function(audioFile){
                    if (typeof audioFile != "object") {
                        throw new Error("An error occured while fetching or generating the audio file.");
                    }

                    let audioElement = document.createElement('audio');
                    audioElement.setAttribute('class', 'voiceep-audio');
                    audioElement.setAttribute('ontimeupdate', 'Voiceep.updatePlayer("' + playerId + '")');
                    audioElement.innerHTML = '<source src="' + audioFile.url + '" type="audio/' + audioFile.format + '">';
                    player.player.appendChild(audioElement);

                    Voiceep.initPlayer(playerId);
                });
            });
        });
    },

    getTitle: function() {
        let title = null;
        let element = document.querySelector("head meta[property='og:title']");

        if (!element || !(title = element.getAttribute('content'))) {
            element = document.querySelector("head title");
            if (!element || !(title = element.text)) {
                element = document.querySelector('h1');
                title = element.text();
            }
        }

        return title;
    },

    getText: function(options, callback) {
        if (typeof options.text != "undefined" && options.text) {
            callback(options.text);
        } else if (typeof options.type != "undefined" && options.type == "manual" && (typeof options.text_to_speech_fallback == 'undefined' || !options.text_to_speech_fallback)) {
            callback('');
        } else {
            if (typeof options.content_selector == "undefined") {
                throw new Error("No selector were specified to fetch the text from. You must define one of the following options: content_selector, text.")
            }

            let element = document.querySelector(options.content_selector);

            if (!element) {
                throw new Error("There are no elements in the DOM matching the provided selector: \"" + options.content_selector + "\"");
            }

            let text = element.innerText.trim();

            if (!text.length) {
                throw new Error("No text could be found within the specified element.");
            }

            callback(text);
        }
    },

    fetchAudio: function(payload, playerId, callback) {
        let xhr = new XMLHttpRequest();
        xhr.open('POST', Voiceep._api_url + 'audio');
        xhr.setRequestHeader('Content-Type', 'application/json;charset=UTF-8');
        xhr.onload = function() {
            if (xhr.status === 200) {
                if (xhr.responseText.length && xhr.responseText.substr(0, 1) == '{') {
                    let response = JSON.parse(xhr.responseText);
                    if (typeof response.status != 'undefined') {
                        if (response.status == 'failed') {
                            console.error("An error occured while converting your text into an audio file on Voiceep.");
                        } else if (response.status == 'completed') {
                            callback(response.audio);
                        } else {
                            if (payload.type != "polly" && payload.text_to_speech_fallback) {
                                payload.type = "polly";
                            }

                            if (payload.type == "polly") {
                                setTimeout(function(){
                                    Voiceep.fetchAudio(payload, playerId, callback);
                                }, 1000);
                            } else {
                                Voiceep.removePlayer(playerId);
                            }
                        }
                    } else {
                        if (typeof response.error != "undefined") {
                            console.error("Voiceep error: " + response.error);
                        } else {
                            console.error("Something went wrong while attempting to fetch the audio file from Voiceep.");
                        }
                        Voiceep.removePlayer(playerId);
                    }
                } else {
                    console.error("Something went wrong while attempting to fetch the audio file from Voiceep.");
                    console.log(xhr.responseText);
                    Voiceep.removePlayer(playerId);
                }
            }
            else if (xhr.status !== 200) {
                console.error("Something went wrong while attempting to fetch the audio file from Voiceep.");
                Voiceep.removePlayer(playerId);
            }
        };
        xhr.send(JSON.stringify(payload));
    },

    removePlayer: function(playerId) {
        let player = Voiceep.getPlayer(playerId);

        Voiceep.triggerEvent(player.player.parentNode, 'voiceep_removed');

        player.player.remove();
    },

    renderPlayer: function(options, callback) {
        let playerId = Math.random().toString(36).substring(2, 15);
        let html = '<div class="voiceep-player loading" data-voiceep-id="' + playerId + '" tabindex="0">' +
                    '<a href="#" class="voiceep-play-button">' +
                        '<img class="voiceep-icon-play" src="data:image/svg+xml;base64,PHN2ZyBhcmlhLWhpZGRlbj0idHJ1ZSIgZGF0YS1wcmVmaXg9ImZhcyIgZGF0YS1pY29uPSJwbGF5IiBjbGFzcz0ic3ZnLWlubGluZS0tZmEgZmEtcGxheSBmYS13LTE0IiByb2xlPSJpbWciIHhtbG5zPSJodHRwOi8vd3d3LnczLm9yZy8yMDAwL3N2ZyIgdmlld0JveD0iMCAwIDQ0OCA1MTIiPjxwYXRoIGZpbGw9ImN1cnJlbnRDb2xvciIgZD0iTTQyNC40IDIxNC43TDcyLjQgNi42QzQzLjgtMTAuMyAwIDYuMSAwIDQ3LjlWNDY0YzAgMzcuNSA0MC43IDYwLjEgNzIuNCA0MS4zbDM1Mi0yMDhjMzEuNC0xOC41IDMxLjUtNjQuMSAwLTgyLjZ6Ij48L3BhdGg+PC9zdmc+" width="21" height="24">' +
                        '<img class="voiceep-icon-pause" src="data:image/svg+xml;base64,PHN2ZyBhcmlhLWhpZGRlbj0idHJ1ZSIgZGF0YS1wcmVmaXg9ImZhcyIgZGF0YS1pY29uPSJwYXVzZSIgY2xhc3M9InN2Zy1pbmxpbmUtLWZhIGZhLXBhdXNlIGZhLXctMTQiIHJvbGU9ImltZyIgeG1sbnM9Imh0dHA6Ly93d3cudzMub3JnLzIwMDAvc3ZnIiB2aWV3Qm94PSIwIDAgNDQ4IDUxMiI+PHBhdGggZmlsbD0iY3VycmVudENvbG9yIiBkPSJNMTQ0IDQ3OUg0OGMtMjYuNSAwLTQ4LTIxLjUtNDgtNDhWNzljMC0yNi41IDIxLjUtNDggNDgtNDhoOTZjMjYuNSAwIDQ4IDIxLjUgNDggNDh2MzUyYzAgMjYuNS0yMS41IDQ4LTQ4IDQ4em0zMDQtNDhWNzljMC0yNi41LTIxLjUtNDgtNDgtNDhoLTk2Yy0yNi41IDAtNDggMjEuNS00OCA0OHYzNTJjMCAyNi41IDIxLjUgNDggNDggNDhoOTZjMjYuNSAwIDQ4LTIxLjUgNDgtNDh6Ij48L3BhdGg+PC9zdmc+" width="21" height="24">' +
                    '</a>' +
                    '<div class="voiceep-progress-wrapper">' +
                        '<div class="voiceep-time-indicator">0:00</div>' +
                        '<div class="voiceep-total-time"></div>' +
                        '<div class="voiceep-progress-bar">' +
                            '<div class="voiceep-progress-bar-progress"></div>' +
                            '<div class="voiceep-progress-bar-handle"></div>' +
                        '</div>' +
                    '</div>' +
                    '<div class="voiceep-volume-wrapper" data-volume-level="3">' +
                        '<svg version="1.1" class="voiceep-volume-toggle" xmlns="http://www.w3.org/2000/svg" xmlns:xlink="http://www.w3.org/1999/xlink" width="30px" x="0px" y="0px" viewBox="-191 225 576 512" fill="currentColor" xml:space="preserve"><path class="volume-indicator" d="M24,296l-89,89H-167c-13.3,0-24,10.7-24,24v144c0,13.2,10.7,24,24,24h102.1l89,89c15,15,41,4.5,41-17V313 C65,291.6,39,281,24,296z"/><path class="volume-bar-1 volume-bar-2 volume-bar-3" d="M147.2,404.1c-11.6-6.3-26.2-2.2-32.6,9.4c-6.4,11.6-2.2,26.2,9.5,32.6C137,453.3,145,466.6,145,481 c0,14.4-8,27.7-20.9,34.8c-11.6,6.4-15.8,21-9.5,32.6c6.4,11.7,21,15.8,32.6,9.5c28.2-15.5,45.8-45,45.8-76.9 S175.5,419.7,147.2,404.1L147.2,404.1z"/><path class="volume-bar-2 volume-bar-3" d="M289,481c0-63.5-32.1-121.9-85.8-156.2c-11.2-7.1-26-3.8-33.1,7.5s-3.8,26.2,7.4,33.4C217.3,391,241,434.1,241,481 s-23.7,90-63.5,115.4c-11.2,7.1-14.5,22.1-7.4,33.4c6.5,10.4,21.1,15.1,33.1,7.5C256.9,602.9,289,544.5,289,481z"/><path class="volume-bar-3" d="M257.4,245c-11.2-7.3-26.2-4.2-33.5,6.9c-7.3,11.2-4.2,26.2,7,33.5C297.1,328.9,336.6,402,336.6,481 s-39.5,152.1-105.8,195.6c-11.2,7.3-14.3,22.3-7,33.5c7,10.7,21.9,14.6,33.5,7C337.3,664.6,385,576.3,385,481S337.3,297.4,257.4,245 z"/><path class="mute-icon" d="M267.6,475l45.6-45.6c6.3-6.3,6.3-16.5,0-22.8l-22.8-22.8c-6.3-6.3-16.5-6.3-22.8,0L222,429.3l-45.6-45.6 c-6.3-6.3-16.5-6.3-22.8,0l-22.8,22.8c-6.3,6.3-6.3,16.5,0,22.8l45.6,45.6l-45.6,45.6c-6.3,6.3-6.3,16.5,0,22.8l22.8,22.8 c6.3,6.3,16.5,6.3,22.8,0l45.6-45.6l45.6,45.6c6.3,6.3,16.5,6.3,22.8,0l22.8-22.8c6.3-6.3,6.3-16.5,0-22.8L267.6,475z"/></svg>' +
                        '<div class="voiceep-volume-slider-wrapper">' +
                            '<div class="voiceep-volume-slider">' +
                                '<div class="voiceep-volume-slider-handle"></div>' +
                            '</div>' +
                        '</div>' +
                    '</div>' +
                '</div>';

        let wrapper = document.querySelector(options.player_selector ? options.player_selector : options.content_selector);

        if (!wrapper) {
            throw new Error("Voiceep couldn't figure out where to render the player. Make sure you specify at least one of the following parameters: player_selector, content_selector.")
        }

        wrapper.innerHTML += html;

        callback(playerId);
    },

    getPlayer: function(playerId) {
        if (typeof Voiceep._players[playerId] != 'undefined') {
            if (Voiceep._players[playerId].audio === null) {
                Voiceep._players[playerId].audio = Voiceep._players[playerId].player.querySelector('audio');
            }
            return Voiceep._players[playerId];
        }

        let player = document.querySelector('[data-voiceep-id="' + playerId + '"]');

        Voiceep._players[playerId] = {
            player: player,
            audio: null,
            progress_bar: player.querySelector('.voiceep-progress-bar'),
            progress_bar_progress: player.querySelector('.voiceep-progress-bar-progress'),
            progress_bar_handle: player.querySelector('.voiceep-progress-bar-handle'),
            time_indicator: player.querySelector('.voiceep-time-indicator'),
            total_time_indicator: player.querySelector('.voiceep-total-time'),
            play_button: player.querySelector('.voiceep-play-button'),
            volume_toggle: player.querySelector('.voiceep-volume-toggle'),
            volume_wrapper: player.querySelector('.voiceep-volume-wrapper'),
            volume_slider: player.querySelector('.voiceep-volume-slider'),
            volume_slider_wrapper: player.querySelector('.voiceep-volume-slider-wrapper'),
            volume_slider_handle: player.querySelector('.voiceep-volume-slider-handle'),
            _initialized: false
        };

        return Voiceep._players[playerId];
    },

    initPlayer: function(playerId) {
        let player = Voiceep.getPlayer(playerId);

        player.player.addEventListener("keydown", function(e){
            if (e.keyCode == 32) {
                e.preventDefault();
                player.play_button.click();
            }
        });

        player.audio.oncanplay = function(){
            if (!player._initialized) {
                player._initialized = true;
                player.player.classList.remove('loading');

                let length = player.audio.duration;
                player.total_time_indicator.innerText = Voiceep.formatTime(length);

                // Initialize play/pause button events
                player.play_button.addEventListener('click', function(e){
                    e.preventDefault();
                    e.stopPropagation();
                    e.stopImmediatePropagation();

                    if (player.play_button.className.indexOf('pause') == -1) {
                        // Stop all currently playing Voiceep players
                        Object.keys(Voiceep._players).forEach(function(playerId) {
                            if (Voiceep._players[playerId].play_button.className.indexOf('pause') != -1) {
                                Voiceep._players[playerId].play_button.click();
                            }
                        });

                        player.audio.play();
                        player.play_button.classList.add('pause');
                    } else {
                        player.audio.pause();
                        player.play_button.classList.remove('pause');
                    }
                });

                // Initialize progress bar seeking
                player.progress_bar.addEventListener('click', function(e){
                    let percent = e.offsetX / this.offsetWidth;
                    player.audio.currentTime = percent * length;
                    player.time_indicator.innerText = Voiceep.formatTime(player.audio.currentTime);
                    player.progress_bar_progress.style.width = (percent * 100) + '%';
                    player.progress_bar_handle.style.left = (percent * 100) + '%';
                });

                // Initialize volume drag & drop
                player.volume_slider_handle.ondragstart = function() { return false; };
                player.volume_slider.addEventListener('mousedown', function(e){
                    Voiceep._active_player = Voiceep.getPlayer(Voiceep.findAncestor(this, '.voiceep-player').getAttribute('data-voiceep-id'));

                    Voiceep._active_player.volume_wrapper.classList.add('open');

                    if (e.target != Voiceep._active_player.volume_slider_handle) {
                        let percentage = 100 - ((e.offsetY / this.offsetHeight) * 100);
                        Voiceep.adjustActiveVolumeToPercentage(percentage);
                    }
                });
            }
        };

        player.audio.load();
    },

    updatePlayer: function(playerId) {
        let player = Voiceep.getPlayer(playerId);

        let length = player.audio.duration;
        let currentTime = player.audio.currentTime;
        let viewedPercentage = (currentTime / length) * 100;

        player.time_indicator.innerText = Voiceep.formatTime(currentTime);
        player.progress_bar_progress.style.width = viewedPercentage + '%';
        player.progress_bar_handle.style.left = viewedPercentage + '%';

        if (currentTime >= length) {
            player.play_button.classList.remove('pause');
            player.audio.currentTime = 0;
        }
    },

    initVolumeDragAndDrop: function() {
        document.addEventListener('mousemove', function(e){
            if (Voiceep._active_player !== null) {
                let offset = e.pageY + Voiceep._page_scroll_offset;
                let min = Voiceep.getRealOffsetTop(Voiceep._active_player.volume_slider_wrapper);
                let max = min + Voiceep._active_player.volume_slider_wrapper.offsetHeight;

                // Take HTML margin-top into account, mostly for Wordpress sites with admin bar
                let htmlElement = document.querySelector('html');
                let htmlStyle = htmlElement.currentStyle || window.getComputedStyle(htmlElement);
                let htmlMarginTop = htmlStyle.marginTop.indexOf('px') ? parseInt(htmlStyle.marginTop) : 0;

                offset -= htmlMarginTop;

                if (offset < min) {
                    offset = min;
                } else if (offset > max) {
                    offset = max;
                }

                let percentage = 100 - (((offset - min) / (max - min)) * 100);
                Voiceep.adjustActiveVolumeToPercentage(percentage);
            }
        });

        document.addEventListener('mouseup', function(e){
            if (Voiceep._active_player !== null) {
                Voiceep._active_player.volume_wrapper.classList.remove('open');
                Voiceep._active_player = null;
            }
        });
    },

    adjustActiveVolumeToPercentage: function(percentage) {
        Voiceep._active_player.volume_slider_handle.style.top = (100 - percentage) + '%';

        if (percentage < 5) {
            percentage = 0;
        } else if (percentage > 95) {
            percentage = 100;
        }

        let volumeLevel = 0;
        if (percentage > 80) {
            volumeLevel = 3;
        } else if (percentage > 40) {
            volumeLevel = 2;
        } else if (percentage > 0) {
            volumeLevel = 1;
        }
        Voiceep._active_player.volume_wrapper.setAttribute('data-volume-level', volumeLevel);

        Voiceep._active_player.audio.volume = percentage / 100;
    },

    formatTime: function(seconds) {
        seconds = Math.floor(seconds);
        return Math.floor(seconds / 60) + ':' + (seconds % 60).toString().padStart(2, '0');
    },

    findAncestor: function(el, sel) {
        while ((el = el.parentElement) && !((el.matches || el.matchesSelector).call(el,sel)));
        return el;
    },

    getRealOffsetTop: function(el) {
        let target = el,
        target_top = target.offsetTop,
        gtop = 0;

        let moonwalk = function(parent) {
            if (!!parent) {
                gtop += parent.offsetTop;
                moonwalk(parent.offsetParent);
            } else {
                return target_top = target.offsetTop + gtop;
            }
        };
        moonwalk(target.offsetParent);
        return target_top;
    },

    triggerEvent: function(element, eventName) {
        var event = new Event(eventName);
        element.dispatchEvent(event);
    }
};
