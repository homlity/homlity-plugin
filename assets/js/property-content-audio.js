(function () {
    'use strict';

    // Split text into sentences to work around Chrome's ~15s SpeechSynthesis cutoff.
    function splitSentences(text) {
        var parts = text.match(/[^.!?…\n]+[.!?…\n]+\s*/g) || [];
        var joined = parts.join('');
        var leftover = text.slice(joined.length).trim();
        if (leftover) parts.push(leftover);
        return parts.filter(function (s) { return s.trim().length > 0; });
    }

    function formatTime(seconds) {
        var m = Math.floor(seconds / 60);
        var s = seconds % 60;
        return (m < 10 ? '0' : '') + m + ':' + (s < 10 ? '0' : '') + s;
    }

    function estimateDurationSeconds(text, rate) {
        var words = (text.match(/\S+/g) || []).length;
        var baseWpm = 170;
        var safeRate = rate > 0 ? rate : 1;
        var seconds = Math.round((words / (baseWpm * safeRate)) * 60);
        return Math.max(1, seconds);
    }

    function HomlityAudioPlayer(bar) {
        this.bar        = bar;
        this.text       = bar.getAttribute('data-text') || '';
        this.sentences  = splitSentences(this.text);
        this.total      = this.sentences.length;
        this.index      = 0;
        this.playing    = false;
        this.paused     = false;
        this.kaTimer    = null;
        this.clockTimer = null;
        this.elapsed    = 0;

        this.progressEl = bar.querySelector('.property-content-audio-bar__progress');
        this.timeEl     = bar.querySelector('.property-content-audio-bar__time');
        this.playBtn    = bar.querySelector('[data-audio="play-pause"]');
        this.rateSelect = bar.querySelector('[data-audio="rate"]');

        var defaultRate = parseFloat(bar.getAttribute('data-rate') || '1') || 1;
        this.rate = defaultRate;
        this.estimatedDuration = estimateDurationSeconds(this.text, this.rate);
        if (this.rateSelect) {
            this.rateSelect.value = String(defaultRate);
        }

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
            }
        });

        if (this.rateSelect) {
            this.rateSelect.addEventListener('change', function () {
                self.rate = parseFloat(self.rateSelect.value) || 1;
                self.estimatedDuration = estimateDurationSeconds(self.text, self.rate);
                self._renderTime();
                self._updateProgress();
                if (self.playing && !self.paused) { self.speakCurrent(); }
            });
        }
    };

    HomlityAudioPlayer.prototype._renderTime = function () {
        if (!this.timeEl) { return; }
        this.timeEl.textContent = formatTime(this.elapsed) + ' / ' + formatTime(this.estimatedDuration);
    };

    HomlityAudioPlayer.prototype._startClock = function () {
        var self = this;
        clearInterval(this.clockTimer);
        this.clockTimer = setInterval(function () {
            self.elapsed++;
            self._renderTime();
            self._updateProgress();
        }, 1000);
    };

    HomlityAudioPlayer.prototype._pauseClock = function () {
        clearInterval(this.clockTimer);
    };

    HomlityAudioPlayer.prototype._resetClock = function () {
        clearInterval(this.clockTimer);
        this.elapsed = 0;
        this._renderTime();
    };

    HomlityAudioPlayer.prototype._setPlayState = function (isPlaying, isPaused) {
        this.playing = isPlaying;
        this.paused  = isPaused;
        if (this.playBtn) {
            this.playBtn.classList.toggle('is-playing', isPlaying && !isPaused);
            var label = !isPlaying ? 'Reproducir' : (isPaused ? 'Reanudar' : 'Pausar');
            this.playBtn.setAttribute('aria-label', label);
        }
    };

    HomlityAudioPlayer.prototype._updateProgress = function () {
        var pct = this.estimatedDuration > 0 ? Math.round((this.elapsed / this.estimatedDuration) * 100) : 0;
        if (pct > 100) { pct = 100; }
        if (this.progressEl) {
            this.progressEl.style.width = pct + '%';
        }
    };

    HomlityAudioPlayer.prototype._startKeepAlive = function () {
        clearInterval(this.kaTimer);
        this.kaTimer = setInterval(function () {
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
            self._resetClock();
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
            self._pauseClock();
            self._setPlayState(false, false);
        };

        self._startKeepAlive();
        window.speechSynthesis.speak(utt);
    };

    HomlityAudioPlayer.prototype.play = function () {
        this.index = 0;
        this._resetClock();
        this._setPlayState(true, false);
        this._updateProgress();
        this._startClock();
        this.speakCurrent();
    };

    HomlityAudioPlayer.prototype.pause = function () {
        clearInterval(this.kaTimer);
        this._pauseClock();
        window.speechSynthesis.pause();
        this._setPlayState(true, true);
    };

    HomlityAudioPlayer.prototype.resume = function () {
        window.speechSynthesis.resume();
        this._setPlayState(true, false);
        this._startKeepAlive();
        this._startClock();
    };

    HomlityAudioPlayer.prototype.stop = function () {
        clearInterval(this.kaTimer);
        this._resetClock();
        window.speechSynthesis.cancel();
        this._setPlayState(false, false);
        this.index = 0;
        this._updateProgress();
    };

    // ─── Bootstrap ─────────────────────────────────────────────────────────────

    function init() {
        if (!('speechSynthesis' in window)) { return; }

        document.querySelectorAll('.property-content-audio-bar').forEach(function (bar) {
            if (bar.dataset.hcaReady) { return; }
            bar.dataset.hcaReady = '1';
            new HomlityAudioPlayer(bar);
        });
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }

    document.addEventListener('elementor/frontend/init', function () {
        if (window.elementorFrontend && window.elementorFrontend.hooks) {
            window.elementorFrontend.hooks.addAction(
                'frontend/element_ready/property_content.default',
                init
            );
        }
    });
})();
