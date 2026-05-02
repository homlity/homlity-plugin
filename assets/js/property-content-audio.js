(function () {
    'use strict';

    // Split text into sentences to work around Chrome's ~15s SpeechSynthesis cutoff.
    // Speaking one sentence at a time via onend avoids the bug entirely.
    function splitSentences(text) {
        var parts = text.match(/[^.!?…\n]+[.!?…\n]+\s*/g) || [];
        var joined = parts.join('');
        var leftover = text.slice(joined.length).trim();
        if (leftover) parts.push(leftover);
        return parts.filter(function (s) { return s.trim().length > 0; });
    }

    function HomlityAudioPlayer(bar) {
        this.bar        = bar;
        this.text       = bar.getAttribute('data-text') || '';
        this.sentences  = splitSentences(this.text);
        this.total      = this.sentences.length;
        this.index      = 0;
        this.playing    = false;
        this.paused     = false;
        this.kaTimer    = null; // keep-alive timer (Chrome fallback)

        this.progressEl   = bar.querySelector('.property-content-audio-bar__progress');
        this.playPauseBtn = bar.querySelector('[data-audio="play-pause"]');
        this.iconPlay     = bar.querySelector('.hca-icon-play');
        this.iconPause    = bar.querySelector('.hca-icon-pause');
        this.rateSelect   = bar.querySelector('[data-audio="rate"]');

        var defaultRate = parseFloat(bar.getAttribute('data-rate') || '1') || 1;
        this.rate = defaultRate;
        if (this.rateSelect) {
            this.rateSelect.value = String(defaultRate);
        }
        if (this.iconPause) this.iconPause.setAttribute('aria-hidden', 'true');
        if (this.iconPause) this.iconPause.style.display = 'none';

        this._bindEvents();
    }

    HomlityAudioPlayer.prototype._bindEvents = function () {
        var self = this;

        this.bar.addEventListener('click', function (e) {
            var btn = e.target.closest('[data-audio]');
            if (!btn) return;
            var action = btn.getAttribute('data-audio');
            if (action === 'play-pause') {
                if (!self.playing)        { self.play(); }
                else if (self.paused)     { self.resume(); }
                else                      { self.pause(); }
            } else if (action === 'rewind')  { self.seek(-15); }
            else if (action === 'forward')   { self.seek(15); }
            else if (action === 'close')     { self.stop(); self.hide(); }
        });

        if (this.rateSelect) {
            this.rateSelect.addEventListener('change', function () {
                self.rate = parseFloat(self.rateSelect.value) || 1;
                if (self.playing && !self.paused) { self.speakCurrent(); }
            });
        }
    };

    HomlityAudioPlayer.prototype.show = function () {
        this.bar.hidden = false;
        // rAF so the display:none→block transition fires
        var bar = this.bar;
        requestAnimationFrame(function () {
            requestAnimationFrame(function () { bar.classList.add('is-visible'); });
        });
    };

    HomlityAudioPlayer.prototype.hide = function () {
        var bar = this.bar;
        bar.classList.remove('is-visible');
        setTimeout(function () { bar.hidden = true; }, 350);
    };

    HomlityAudioPlayer.prototype._setPlayState = function (isPlaying, isPaused) {
        this.playing = isPlaying;
        this.paused  = isPaused;
        var showPlay  = !isPlaying || isPaused;
        if (this.iconPlay)  { this.iconPlay.style.display  = showPlay ? '' : 'none'; }
        if (this.iconPause) { this.iconPause.style.display = showPlay ? 'none' : ''; }
        if (this.playPauseBtn) {
            var label = !isPlaying ? 'Reproducir' : (isPaused ? 'Reanudar' : 'Pausar');
            this.playPauseBtn.setAttribute('aria-label', label);
        }
    };

    HomlityAudioPlayer.prototype._updateProgress = function () {
        var pct = this.total > 0 ? Math.round((this.index / this.total) * 100) : 0;
        if (this.progressEl) { this.progressEl.style.width = pct + '%'; }
    };

    HomlityAudioPlayer.prototype._startKeepAlive = function () {
        clearInterval(this.kaTimer);
        this.kaTimer = setInterval(function () {
            // Chrome workaround: prod the synth so it doesn't silently die
            if (window.speechSynthesis.speaking && !window.speechSynthesis.paused) {
                window.speechSynthesis.pause();
                window.speechSynthesis.resume();
            }
        }, 10000);
    };

    HomlityAudioPlayer.prototype.speakCurrent = function () {
        var self = this;
        window.speechSynthesis.cancel();
        clearInterval(self.kaTimer);

        if (self.index >= self.total) {
            self._setPlayState(false, false);
            self.index = 0;
            self._updateProgress();
            return;
        }

        var utt = new SpeechSynthesisUtterance(self.sentences[self.index]);
        utt.lang   = 'es-ES';
        utt.rate   = self.rate;
        utt.volume = 1;

        utt.onend = function () {
            clearInterval(self.kaTimer);
            if (!self.playing) { return; }
            self.index++;
            self._updateProgress();
            self.speakCurrent();
        };

        utt.onerror = function (e) {
            if (e.error === 'interrupted' || e.error === 'canceled') { return; }
            clearInterval(self.kaTimer);
            self._setPlayState(false, false);
        };

        self._startKeepAlive();
        window.speechSynthesis.speak(utt);
    };

    HomlityAudioPlayer.prototype.play = function () {
        this.index = 0;
        this._setPlayState(true, false);
        this._updateProgress();
        this.speakCurrent();
    };

    HomlityAudioPlayer.prototype.pause = function () {
        clearInterval(this.kaTimer);
        window.speechSynthesis.pause();
        this._setPlayState(true, true);
    };

    HomlityAudioPlayer.prototype.resume = function () {
        window.speechSynthesis.resume();
        this._setPlayState(true, false);
        this._startKeepAlive();
    };

    HomlityAudioPlayer.prototype.stop = function () {
        clearInterval(this.kaTimer);
        window.speechSynthesis.cancel();
        this._setPlayState(false, false);
        this.index = 0;
        this._updateProgress();
    };

    HomlityAudioPlayer.prototype.seek = function (seconds) {
        // Estimate jump in sentences from elapsed seconds
        var words           = this.text.split(/\s+/).length;
        var avgWordsPerSent = words / Math.max(1, this.total);
        var wordsPerSec     = this.rate * 2.5;
        var jump            = Math.round((seconds * wordsPerSec) / avgWordsPerSent);
        this.index = Math.max(0, Math.min(this.total - 1, this.index + jump));
        if (this.playing && !this.paused) { this.speakCurrent(); }
        this._updateProgress();
    };

    // ─── Bootstrap ─────────────────────────────────────────────────────────────

    function init() {
        if (!('speechSynthesis' in window)) { return; }

        document.querySelectorAll('.property-content-audio-trigger').forEach(function (trigger) {
            if (trigger.dataset.hcaReady) { return; }
            trigger.dataset.hcaReady = '1';

            var playerId = trigger.getAttribute('data-player');
            var bar      = playerId ? document.getElementById(playerId) : null;
            if (!bar) { return; }

            var player = new HomlityAudioPlayer(bar);

            trigger.addEventListener('click', function () {
                player.show();
                if (!player.playing) { player.play(); }
            });
        });
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }

    // Re-init after Elementor re-renders a widget in the editor
    document.addEventListener('elementor/frontend/init', function () {
        if (window.elementorFrontend && window.elementorFrontend.hooks) {
            window.elementorFrontend.hooks.addAction(
                'frontend/element_ready/property_content.default',
                init
            );
        }
    });
})();
