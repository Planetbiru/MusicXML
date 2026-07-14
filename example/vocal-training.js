/**
 * vocal-training.js
 * 
 * Handles MIDI playback (via the MIDIjs CDN: midijs.net) and real-time
 * synchronisation of the SVG score with the audio position.
 *
 * MIDIjs CDN API reference (midijs.net):
 *   MIDIjs.play(url)          – start / resume playback from a data-URI or URL
 *   MIDIjs.stop()             – stop and reset
 *   MIDIjs.get_duration(url, cb(seconds))  – async: total duration in seconds
 *   MIDIjs.player_callback    – function(e) called ~10× per second; e.time = seconds elapsed
 *   MIDIjs.message_callback   – function(msg) for status/error messages
 */

// ─── Playback state ──────────────────────────────────────────────────────────
let TICKS_PER_BEAT        = 512;   // overridden from MIDI header
let tempoMap              = [];
let midiDuration          = 0;     // total duration in seconds
let midiDurationTicks     = 0;
let isPlaying             = false;
let isSeeking             = false;
let playbackStartOffsetSec = 0;    // seek offset when play was last started
let playbackStartWallTime  = 0;    // performance.now() snapshot at play start
let lastScrolledSystem     = null;
let playbackCompensation   = 0;
let pausedAtSec            = 0;    // seconds position when pause was requested

const COMPENSATION_STORAGE_KEY = 'vocalTrainingCompensation';

// ─── DOM references ──────────────────────────────────────────────────────────
let trackSelect, transposeSelect, playBtn, stopBtn, seeker;
let timeDisplay, metronomeContainer, durationDisplay;
let compensationControl, compensationDisplay;
let scoreContent, scoreContainer, svgElement, playheadElement;
let systemCount = 0;

// ─── Parsed MIDI data ────────────────────────────────────────────────────────
let midi;
let maxTicks   = 0;

// ─── Utility ─────────────────────────────────────────────────────────────────
function formatTime(seconds) {
    const min = Math.floor(seconds / 60);
    const sec = Math.floor(seconds % 60);
    return `${String(min).padStart(2, '0')}:${String(sec).padStart(2, '0')}`;
}

// ─── Playhead SVG element ────────────────────────────────────────────────────
function createOrUpdatePlayhead() {
    const svg = scoreContent.querySelector('svg');
    if (!svg) return;

    playheadElement = svg.querySelector('#playhead-line');
    if (!playheadElement) {
        playheadElement = document.createElementNS('http://www.w3.org/2000/svg', 'line');
        playheadElement.setAttribute('id', 'playhead-line');
        playheadElement.setAttribute('stroke', 'rgba(255,0,0,0.7)');
        playheadElement.setAttribute('stroke-width', '2');
        playheadElement.style.pointerEvents = 'none';
        svg.appendChild(playheadElement);
    }
}

function syncScoreInfo() {
    if (scoreContent.querySelector('svg')) {
        seeker.max = maxTicks;
    }
    createOrUpdatePlayhead();
}

// ─── Metronome ────────────────────────────────────────────────────────────────
function updateMetronome(currentTick) {
    if (!metronomeContainer || !midi) return;

    const pos             = midi.header.tickToPosition(currentTick);
    const beatsPerMeasure = pos.numerator;
    const currentBeat     = pos.beat;
    const progressInBeat  = pos.tickInBeat / pos.ticksPerBeat;

    metronomeContainer.querySelectorAll('.metronome-beat').forEach(el => {
        const beat = parseInt(el.dataset.beat);
        const isActive = beat === currentBeat;
        el.classList.toggle('active', isActive);
        if (isActive) el.style.setProperty('--beat-progress', progressInBeat);
        el.style.display = beat > beatsPerMeasure ? 'none' : 'flex';
    });
}

// ─── Note highlighting ───────────────────────────────────────────────────────
function selectByTick(targetTick) {
    const numerator = midi.header.timeSignatures[0]?.numerator ?? 4;
    const nTPB      = midi.header.ppq / numerator;
    targetTick      = targetTick / nTPB + nTPB / 8;

    return Array.from(document.querySelectorAll('g[data-element="true"]')).filter(el => {
        const start = parseFloat(el.getAttribute('data-start-tick'));
        const end   = parseFloat(el.getAttribute('data-end-tick'));
        return targetTick >= start && targetTick <= end;
    });
}

// ─── Main sync function ──────────────────────────────────────────────────────
/**
 * @param {number} currentTime  – seconds since playback started (raw audio time)
 * @param {boolean} updateSeeker – whether to update the range input
 */
function updatePlayheadPos(currentTime, updateSeeker = true) {
    const visualTime = Math.max(0, currentTime + playbackCompensation);
    const currentTick = midi.header.secondsToTicks(visualTime);

    updateMetronome(currentTick);

    // Clear previous highlights
    document.querySelectorAll('g[data-element="true"].active')
        .forEach(el => el.classList.remove('active'));

    // Highlight current notes
    selectByTick(currentTick).forEach(el => {
        if (el instanceof SVGGElement) el.classList.add('active');
    });

    // Playhead line position
    const pos = midi.header.tickToPosition(currentTick);
    const currentMeasureNum = pos.measure;
    const activeMeasure = svgElement?.querySelector(`g[data-measure-number="${currentMeasureNum}"]`);

    if (activeMeasure) {
        const activeSystem = activeMeasure.closest('g[data-system-number]');
        const systemNumber = parseInt(activeSystem.dataset.systemNumber);

        const xOffset     = systemNumber === 1 ? 23.2 : 17;
        const systemX     = parseFloat(activeSystem.getAttribute('x')) + xOffset;
        const systemY     = parseFloat(activeSystem.getAttribute('y'));
        const systemWidth = parseFloat(activeSystem.getAttribute('width')) - xOffset;
        const systemHeight = parseFloat(activeSystem.getAttribute('height'));

        const numerator = midi.header.timeSignatures[0]?.numerator ?? 4;
        const factor    = midi.header.ppq / numerator;
        const sysTick0  = parseFloat(activeSystem.dataset.startTick) * factor;
        const sysTick1  = parseFloat(activeSystem.dataset.endTick)   * factor;
        const progress  = Math.abs(currentTick - sysTick0) / Math.abs(sysTick1 - sysTick0);

        if (playheadElement) {
            const xPos = systemX + progress * systemWidth;
            playheadElement.setAttribute('x1', xPos);
            playheadElement.setAttribute('x2', xPos);
            playheadElement.setAttribute('y1', systemY);
            playheadElement.setAttribute('y2', systemY + systemHeight);
        }

        // Auto-scroll
        if (activeSystem !== lastScrolledSystem) {
            if (systemCount > 2) {
                const scrollable = scoreContainer.scrollHeight - scoreContainer.clientHeight;
                const target     = scrollable * (systemNumber - 1) / (systemCount - 1);
                scoreContainer.scrollTo({ top: target, behavior: 'smooth' });
            }
            lastScrolledSystem = activeSystem;
        }
    }

    // Time display
    timeDisplay.textContent = formatTime(currentTime);

    // Seeker (only while user isn't dragging it)
    if (updateSeeker && !isSeeking) {
        seeker.value = midi.header.secondsToTicks(currentTime);
    }
}

// ─── On-ended handler ────────────────────────────────────────────────────────
function handlePlaybackEnded() {
    isPlaying     = false;
    pausedAtSec   = 0;
    playbackStartOffsetSec = 0;
    lastScrolledSystem     = null;
    playBtn.innerHTML = playIcon();
    seeker.value = 0;
    timeDisplay.textContent = '00:00';
    updatePlayheadPos(0, true);
    if (scoreContainer) scoreContainer.scrollTo({ top: 0, behavior: 'smooth' });
}

// ─── SVG icon helpers ────────────────────────────────────────────────────────
function playIcon()  { return '<svg viewBox="0 0 24 24" width="24" height="24"><path fill="currentColor" d="M8,5.14V19.14L19,12.14L8,5.14Z"/></svg>'; }
function pauseIcon() { return '<svg viewBox="0 0 24 24" width="24" height="24"><path fill="currentColor" d="M14,19H18V5H14M6,19H10V5H6V19Z"/></svg>'; }
function stopIcon() { return '<svg viewBox="0 0 24 24" width="24" height="24"><path fill="currentColor" d="M18,18H6V6H18V18Z"/></svg>'; }

// ─── DOMContentLoaded ────────────────────────────────────────────────────────
document.addEventListener('DOMContentLoaded', () => {

    // Cache DOM references
    trackSelect          = document.getElementById('track-select');
    transposeSelect      = document.getElementById('transpose-select');
    playBtn              = document.getElementById('play-btn');
    stopBtn              = document.getElementById('stop-btn');
    seeker               = document.getElementById('seeker');
    timeDisplay          = document.getElementById('time-display');
    metronomeContainer   = document.getElementById('metronome-container');
    durationDisplay      = document.getElementById('duration-display');
    compensationControl  = document.getElementById('compensation-control');
    compensationDisplay  = document.getElementById('compensation-reset-btn');
    scoreContent         = document.getElementById('score-content');
    scoreContainer       = document.getElementById('score-container');
    svgElement           = scoreContent?.querySelector('svg');
    systemCount          = document.querySelectorAll('g[data-system-number]')?.length ?? 0;

    // ── Parse MIDI for tempo map / metronome ─────────────────────────────
    if (!MIDI_BASE64_STRING) return;

    try {
        const binary = atob(MIDI_BASE64_STRING);
        const bytes  = new Uint8Array(binary.length);
        for (let i = 0; i < binary.length; i++) bytes[i] = binary.charCodeAt(i);

        midi          = MidiParser.parse(bytes.buffer);
        TICKS_PER_BEAT = midi.header.ppq || 512;
        tempoMap      = midi.header.tempos.sort((a, b) => a.ticks - b.ticks);
        if (tempoMap.length === 0) tempoMap.push({ ticks: 0, bpm: 120 });

        maxTicks   = midi.header.maxTicks;
        seeker.max = maxTicks;

        // Update metronome beat count from time signature
        const metronomeWrapper = document.querySelector('.metronome-wrapper');
        if (metronomeWrapper && midi.header.timeSignatures.length > 0) {
            const numerator = midi.header.timeSignatures[0].numerator;
            metronomeWrapper.innerHTML = '';
            for (let i = 1; i <= numerator; i++) {
                const el      = document.createElement('div');
                el.className  = 'metronome-beat';
                el.dataset.beat = i;
                el.textContent  = i;
                metronomeWrapper.appendChild(el);
            }
        }

        syncScoreInfo();

        // Restore compensation setting
        const saved = localStorage.getItem(COMPENSATION_STORAGE_KEY);
        if (saved !== null) {
            playbackCompensation       = parseFloat(saved);
            compensationControl.value  = playbackCompensation;
            compensationDisplay.textContent = `${playbackCompensation.toFixed(2)}s`;
        }

        // ── Get total duration from MIDIjs ────────────────────────────────
        const midiDataURI = 'data:audio/midi;base64,' + MIDI_BASE64_STRING;

        MIDIjs.get_duration(midiDataURI, (dur) => {
            midiDuration = dur;
            durationDisplay.textContent = formatTime(midiDuration);
        });

        // ── MIDIjs player_callback (fires ~10× per second during playback) ─
        MIDIjs.player_callback = (e) => {
            if (!isPlaying) return;

            const elapsed = e.time; // seconds since play() was called

            // Detect natural end of file
            if (midiDuration > 0 && elapsed >= midiDuration - 0.1) {
                handlePlaybackEnded();
                return;
            }

            updatePlayheadPos(playbackStartOffsetSec + elapsed, true);
        };

        // ── Status / error messages ───────────────────────────────────────
        MIDIjs.message_callback = (msg) => {
            // Uncomment for debugging:
            // console.log('[MIDIjs]', msg);
        };

        // ── Show loading bar while instruments load ───────────────────────
        const progressBar = document.getElementById('loading-progress-bar');
        if (progressBar) {
            progressBar.style.opacity = '1';
            progressBar.style.width   = '60%';
            // MIDIjs doesn't expose fine-grained progress; fade it out after a moment
            setTimeout(() => {
                progressBar.style.width   = '100%';
                setTimeout(() => { progressBar.style.opacity = '0'; }, 400);
            }, 1200);
        }

    } catch (err) {
        console.error('Failed to parse MIDI data:', err);
    }

    // ── Compensation control ─────────────────────────────────────────────
    compensationControl.addEventListener('input', (e) => {
        playbackCompensation = parseFloat(e.target.value);
        compensationDisplay.textContent = `${playbackCompensation.toFixed(2)}s`;
        localStorage.setItem(COMPENSATION_STORAGE_KEY, playbackCompensation);
    });

    document.getElementById('compensation-reset-btn').addEventListener('click', () => {
        playbackCompensation  = 0;
        compensationControl.value = 0;
        compensationDisplay.textContent = '0.00s';
        localStorage.setItem(COMPENSATION_STORAGE_KEY, 0);
    });

    // ── Track & transpose selectors ──────────────────────────────────────
    trackSelect.addEventListener('change', () => loadScore(trackSelect.value));
    transposeSelect.addEventListener('change', () => loadScore(trackSelect.value));

    // ── Seeker ───────────────────────────────────────────────────────────
    seeker.addEventListener('mousedown',  () => { isSeeking = true;  });
    seeker.addEventListener('touchstart', () => { isSeeking = true;  }, { passive: true });
    seeker.addEventListener('mouseup',    () => { isSeeking = false; });
    seeker.addEventListener('touchend',   () => { isSeeking = false; });

    // ── Play / Stop button ──────────────────────────────────────────────
    playBtn.addEventListener('click', () => {
        const midiDataURI = 'data:audio/midi;base64,' + MIDI_BASE64_STRING;

        if (isPlaying) {
            // ── PAUSE: MIDIjs has no pause(); simulate by stopping and
            //    recording the elapsed time so we can resume later.
            const elapsedOnStop = (typeof MIDIjs.player_callback._lastTime !== 'undefined')
                ? MIDIjs.player_callback._lastTime
                : 0;
            MIDIjs.stop();
            isPlaying      = false;
            pausedAtSec    = playbackStartOffsetSec + elapsedOnStop;
            playBtn.innerHTML = playIcon();

        } else {
            playbackStartOffsetSec = 0;
            MIDIjs.play(midiDataURI);
            isPlaying = true;
            playBtn.innerHTML = stopIcon();

        }
    });

    // Wrap player_callback to track the last reported time (for pause support)
    const _originalCallback = MIDIjs.player_callback;
    MIDIjs.player_callback = (e) => {
        MIDIjs.player_callback._lastTime = e.time;
        if (_originalCallback) _originalCallback(e);
    };
});

// ─── Load score (page reload with new track/transpose) ───────────────────────
function loadScore(midiTrackId) {
    const transpose = transposeSelect ? transposeSelect.value : 0;
    window.location.href =
        `vocal-training.php?midi_track_id=${encodeURIComponent(midiTrackId)}&transpose=${transpose}`;
}