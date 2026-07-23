<?php
/**
 * Vocal Training – Standalone Example
 *
 * Reads example.mid directly from disk (no database required).
 * Uses the MIDIjs CDN (midijs.net) for in-browser playback.
 * All UI strings are in English.
 */

use Midi\MidiFilter;
use MusicXML\MusicConverter;

// ── Autoload ──────────────────────────────────────────────────────────────────
// Try several possible paths for the Composer autoloader
$autoloadCandidates = [
    __DIR__ . '/../vendor/autoload.php',   // MusicXML/vendor/autoload.php  ← typical
    __DIR__ . '/vendor/autoload.php',
    __DIR__ . '/inc.lib/vendor/autoload.php',
];
foreach ($autoloadCandidates as $candidate) {
    if (file_exists($candidate)) {
        require_once $candidate;
        break;
    }
}

// ── Request parameters ────────────────────────────────────────────────────────
$transpose   = isset($_GET['transpose'])     ? (int)$_GET['transpose']  : 0;
$midiTrackId = isset($_GET['midi_track_id']) ? $_GET['midi_track_id']   : null;

// ── Song meta (hard-coded for the example) ────────────────────────────────────
$song_name    = 'Example Song';
$composerName = '';

// ── Read MIDI file from disk ──────────────────────────────────────────────────
$midiFilePath     = __DIR__ . '/example.mid';
$midiData         = file_exists($midiFilePath) ? file_get_contents($midiFilePath) : '';
$filteredMidiData = '';
$trackOnlyMidi    = '';
$trackList        = [];
$svgData          = '';

if ($midiData && class_exists('Midi\MidiFilter')) {
    $midiFilter = new MidiFilter();

    $midiFilter->loadMidi($midiData);

    // Apply transpose (no channel muting)

    $filteredMidiData = $midiFilter->transposeAndMute($midiData, $transpose, []);

    // Enumerate tracks so the <select> can be populated
    $trackList = $midiFilter->getAllTracks();


    // Default to the first track when none is specified in the URL
    if ($midiTrackId === null && !empty($trackList)) {
        $midiTrackId = $trackList[0]['index'];
    }

    // Extract only the chosen track
    $trackOnlyMidi = $midiFilter->filterByGetTrack($filteredMidiData, $midiTrackId);

    // Convert to SVG for display
    if (isset($midiTrackId) && class_exists('MusicXML\MusicConverter')) {
        try {
            $converter = new MusicConverter(false, false, true, 7);
            $svgData   = $converter->midiToSVG($trackOnlyMidi, $song_name, $composerName, 'example', 4, true);
        } catch (Exception $e) {
            // Silently ignore render errors
        }
    }
}

// Base64 payloads passed to JavaScript
$midi_base64_full  = $filteredMidiData ? base64_encode($filteredMidiData) : '';
$midi_base64_track = $trackOnlyMidi    ? base64_encode($trackOnlyMidi)    : '';
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>Vocal Training – <?php echo htmlspecialchars($song_name); ?></title>
    <meta name="description" content="Vocal training tool with MIDI playback and score synchronization.">
    <link rel="stylesheet" href="vocal-training.css">
</head>

<body>
    <div class="app-container">
        <div id="loading-progress-bar"></div>

        <!-- ── Playback controls ─────────────────────────────────────────── -->
        <div class="controls">
            <!-- Track selector -->
            <div class="control-group">
                <label for="track-select">Track:</label>
                <select id="track-select">
                    <?php foreach ($trackList as $trackData) :
                        $idx      = $trackData['index'];
                        $name     = htmlspecialchars($trackData['name']);
                        $selected = ($idx == $midiTrackId) ? 'selected' : '';
                    ?>
                        <option value="<?php echo $idx; ?>" <?php echo $selected; ?>><?php echo $name; ?></option>
                    <?php endforeach; ?>
                </select>
            </div>

            <!-- Transpose selector -->
            <div class="control-group">
                <label for="transpose-select">Transpose:</label>
                <select id="transpose-select">
                    <?php for ($i = -12; $i <= 12; $i++) :
                        $selected = ($i === $transpose) ? 'selected' : '';
                        $label    = ($i > 0) ? "+$i" : $i;
                    ?>
                        <option value="<?php echo $i; ?>" <?php echo $selected; ?>><?php echo $label; ?></option>
                    <?php endfor; ?>
                </select>
            </div>

            <!-- Transport buttons -->
            <div class="control-group transport">
                <button id="play-btn" title="Play / Stop">
                    <svg viewBox="0 0 24 24" width="24" height="24">
                        <path fill="currentColor" d="M8,5.14V19.14L19,12.14L8,5.14Z"/>
                    </svg>
                </button>
            </div>

            <!-- Seeker -->
            <div class="control-group seeker-group">
                <span id="time-display">00:00</span>
                <input type="range" id="seeker" min="0" value="0" step="1" data-second="0">
                <span id="duration-display">00:00</span>
            </div>

            <!-- Compensation -->
            <div class="control-group compensation-group">
                <label for="compensation-control">Compensation:</label>
                <input type="range" id="compensation-control" min="-2" max="2" step="0.01" value="0">
                <button id="compensation-reset-btn" title="Reset compensation to 0">0s</button>
            </div>
        </div>

        <!-- ── Metronome ─────────────────────────────────────────────────── -->
        <div id="metronome-container" class="metronome-container">
            <div class="metronome-wrapper">
                <div class="metronome-beat" data-beat="1">1</div>
                <div class="metronome-beat" data-beat="2">2</div>
                <div class="metronome-beat" data-beat="3">3</div>
                <div class="metronome-beat" data-beat="4">4</div>
            </div>
        </div>

        <!-- ── Score ─────────────────────────────────────────────────────── -->
        <main id="score-container">
            <div id="score-content">
                <?php if ($svgData): ?>
                    <?php echo $svgData; ?>
                <?php elseif (!$midiData): ?>
                    <p style="padding:2rem;color:#888;">
                        Could not read <code>example.mid</code>. Make sure the file exists in the same directory.
                    </p>
                <?php else: ?>
                    <p style="padding:2rem;color:#888;">
                        No score to display. The MIDI processing classes may not be loaded.
                    </p>
                <?php endif; ?>
            </div>
        </main>
    </div><!-- /.app-container -->

    <!-- ── Inline data passed to JavaScript ─────────────────────────────── -->
    <script>
        // Full (filtered + transposed) MIDI, used for playback
        const MIDI_BASE64_STRING = '<?php echo $midi_base64_full; ?>';
        // Track-only MIDI (for SVG sync reference)
        const TRACK_BASE64_STRING = '<?php echo $midi_base64_track; ?>';
        // Current track & transpose (used by loadScore())
        const CURRENT_MIDI_TRACK_ID = '<?php echo isset($midiTrackId) ? htmlspecialchars($midiTrackId) : ''; ?>';
        const CURRENT_TRANSPOSE     = <?php echo $transpose; ?>;
    </script>

    <!-- ── MIDIjs CDN ────────────────────────────────────────────────────── -->
    <script type="text/javascript" src="//www.midijs.net/lib/midi.js"></script>

    <!-- ── Application scripts ──────────────────────────────────────────── -->
    <script src="midi-parser.js"></script>
    <script src="vocal-training.js"></script>
</body>

</html>