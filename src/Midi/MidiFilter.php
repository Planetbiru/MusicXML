<?php

namespace Midi;

use Exception;

class MidiFilter
{
    /**
     * @var array
     */
    private $lockedChannels = array();

    /**
     * @var array
     */
    private $skippedChannels = array();

    /**
     * @var array
     */
    private $allTracks = array();

    /**
     * Constructor for the MidiFilter class.
     */
    public function __construct()
    {
        
    }

    /**
     * Loads MIDI data and extracts basic information like track names and channels.
     * This method populates the `allTracks` property.
     *
     * @param string $midiData The binary data of the MIDI file.
     * @return void
     * @throws Exception If the MIDI data is invalid.
     */
    public function loadMidi($midiData)
    {
        $this->allTracks = array(); // Reset

        if (substr($midiData, 0, 4) !== 'MThd') {
            throw new Exception("Not a valid MIDI file.");
        }

        $headerLength = unpack('N', substr($midiData, 4, 4))[1];
        $offset = 8 + $headerLength;
        $totalLength = strlen($midiData);
        $currentTrackIndex = 0;

        while ($offset < $totalLength) {
            if ($offset + 8 > $totalLength || substr($midiData, $offset, 4) !== 'MTrk') {
                break;
            }

            $trackLength = unpack('N', substr($midiData, $offset + 4, 4))[1];
            $trackBody = substr($midiData, $offset + 8, $trackLength);

            $trackName = $this->detectTrackName($trackBody);
            if ($trackName === null) {
                $trackName = "Track " . ($currentTrackIndex + 1);
            }
            $trackChannel = $this->detectChannelFromTrack($trackBody);
            $channelNumber = $trackChannel !== null ? $trackChannel + 1 : null;
            
            $this->allTracks[] = array('index' => $currentTrackIndex, 'name' => $trackName, 'channel' => $channelNumber);

            $offset += 8 + $trackLength;
            $currentTrackIndex++;
        }
    }

    /**
     * Transposes all notes, except for those on the drum channel (channel 10), by a specified number of semitones.
     * It also effectively mutes specified channels by setting their volume control (CC #7) to 0.
     * This function ensures notes remain in the MIDI data for sheet music printing but prevents them from being audible on the muted channels.
     * 
     * @param string $midiData The binary data of the MIDI file.
     * @param int $semitones The number of semitones to transpose. Positive values transpose up, negative values transpose down.
     * @param int[] $muteChannels An array of channel numbers (1-16) to mute by setting their volume to 0.
     * @return string
     */
    public function transposeAndMute($midiData, $semitones = 0, $muteChannels = []) {
        if (substr($midiData, 0, 4) !== 'MThd') {
            throw new Exception("Not a valid MIDI file.");
        }

        $headerLength = unpack('N', substr($midiData, 4, 4))[1];
        $headerChunk = substr($midiData, 0, 8 + $headerLength);

        $modifiedTracksData = '';
        $offset = 8 + $headerLength;
        $totalLength = strlen($midiData);

        while ($offset < $totalLength) {
            if ($offset + 8 > $totalLength || substr($midiData, $offset, 4) !== 'MTrk') {
                // If we don't find an MTrk header, something is wrong, but we can try to stop gracefully.
                break;
            }

            $trackLength = unpack('N', substr($midiData, $offset + 4, 4))[1];
            $trackBody = substr($midiData, $offset + 8, $trackLength);

            $newTrackBody = '';
            $p = 0;
            $bodyLen = strlen($trackBody);
            $runningStatus = 0;

            while ($p < $bodyLen) {
                // Read and preserve delta-time
                $dtStart = $p;
                $this->readVarLen($trackBody, $p);
                $dtBytes = substr($trackBody, $dtStart, $p - $dtStart);

                if ($p >= $bodyLen) {
                    // Append trailing delta-time if any
                    $newTrackBody .= $dtBytes;
                    break;
                }

                $statusByte = ord($trackBody[$p]);
                $isDataByte = ($statusByte & 0x80) === 0;
                $eventStart = $p;

                // Handle running status
                if ($isDataByte) {
                    $statusByte = $runningStatus;
                } else {
                    $runningStatus = $statusByte;
                    $eventStart = $p;
                    $p++;
                }

                $messageType = $statusByte & 0xF0;
                $channel = ($statusByte & 0x0F) + 1;

                // Note On / Note Off
                if (($messageType === 0x90 || $messageType === 0x80) && $channel != 10 && $semitones != 0) {
                    $note = ord($trackBody[$p]);
                    $newNote = max(0, min(127, $note + $semitones));
                    $newTrackBody .= $dtBytes . chr($statusByte) . chr($newNote) . $trackBody[$p + 1];
                    $p += 2;
                }
                // Control Change (Volume)
                else if ($messageType === 0xB0) {
                    $controller = ord($trackBody[$p]);
                    if ($controller === 7 && in_array($channel, $muteChannels)) {
                        $newTrackBody .= $dtBytes . chr($statusByte) . $trackBody[$p] . chr(0);
                    } else {
                        $newTrackBody .= $dtBytes . chr($statusByte) . $trackBody[$p] . $trackBody[$p + 1];
                    }
                    $p += 2;
                }
                // Other events (copy as is)
                else {
                    $p_before_len = $p;
                    $dataLen = $this->getEventLength($statusByte, $trackBody, $p); // p will be advanced by this call
                    
                    // Copy the original event data, including status byte if it was present
                    $newTrackBody .= $dtBytes . substr($trackBody, $eventStart, $p - $eventStart);
                }
            }
            $modifiedTracksData .= 'MTrk' . pack('N', strlen($newTrackBody)) . $newTrackBody;
            $offset += 8 + $trackLength;
        }

        return $headerChunk . $modifiedTracksData;
    }

    /**
     * Filters MIDI data to only include a specific track by its index.
     * This function also collects metadata from all tracks (name, channel) into the `$allTracks` property.
     *
     * @param string $midiData The binary data of the MIDI file.
     * @param int $targetTrackIndex The 0-based index of the track to keep. Other note-containing tracks will be removed.
     * @return string The filtered binary MIDI data containing only the target track and meta tracks (tempo, lyrics).
     * @throws Exception If the MIDI data is invalid (e.g., does not have an 'MThd' header).
     */
    public function filterByGetTrack($midiData, $targetTrackIndex)
    {
        $this->allTracks = array(); // Reset daftar trek setiap kali fungsi dipanggil

        if (substr($midiData, 0, 4) !== 'MThd') 
        {
            throw new Exception("Not a valid MIDI file.");
        }

        $headerLength = unpack('N', substr($midiData, 4, 4))[1];
        $headerData = substr($midiData, 8, $headerLength);

        $timeDivision = substr($headerData, 4, 2);

        $keptTracksData = '';
        $keptTrackCount = 0;
        $offset = 8 + $headerLength;
        $totalLength = strlen($midiData);
        $currentTrackIndex = 0;

        while ($offset < $totalLength) 
        {
            if (substr($midiData, $offset, 4) !== 'MTrk') 
            {
                $offset++;
                continue;
            }

            $trackLength = unpack('N', substr($midiData, $offset + 4, 4))[1];
            $fullTrackChunk = substr($midiData, $offset, 8 + $trackLength);
            $trackBody = substr($midiData, $offset + 8, $trackLength);

            // Cek apakah track ini berisi event nada atau hanya meta event
            $isNoteTrack = $this->isNoteTrack($trackBody);

            $keepThisTrack = false;
            if ($currentTrackIndex == $targetTrackIndex) 
            {
                // Selalu pertahankan track yang dipilih
                $keepThisTrack = true;
            } 
            else if (!$isNoteTrack) 
            {
                // Pertahankan juga track yang tidak berisi nada (misal: lirik, tempo)
                $keepThisTrack = true;
            }

            // Ambil nama dan channel dari setiap trek untuk disimpan
            $trackName = $this->detectTrackName($trackBody);
            if ($trackName === null) {
                $trackName = "Track " . ($currentTrackIndex + 1);
            }
            $trackChannel = $this->detectChannelFromTrack($trackBody);
            $channelNumber = $trackChannel !== null ? $trackChannel + 1 : null;
            
            // 'skipped' di sini berarti "tidak dipilih", bukan "terkunci"
            $isSkipped = ($currentTrackIndex != $targetTrackIndex) && $isNoteTrack;
            $this->allTracks[] = array('index' => $currentTrackIndex, 'name' => $trackName, 'channel' => $channelNumber, 'skipped' => $isSkipped);

            if ($keepThisTrack) 
            {
                $keptTracksData .= $fullTrackChunk;
                $keptTrackCount++;
            }

            $offset += 8 + $trackLength;
            $currentTrackIndex++;
        }

        // Jika tidak ada track yang dipertahankan (misal, trackId tidak valid), kembalikan MIDI kosong
        if ($keptTrackCount == 0) {
            $newHeader = 'MThd' . pack('N', 6) . pack('n', 0) . pack('n', 0) . $timeDivision;
            return $newHeader;
        }

        // Bangun ulang header dengan jumlah track yang benar.
        // Jika hasil akhirnya hanya 1 track, gunakan Format 0. Jika lebih, gunakan Format 1.
        $newFormat = ($keptTrackCount > 1) ? 1 : 0;
        $newHeader = 'MThd'
            . pack('N', 6)
            . pack('n', $newFormat)
            . pack('n', $keptTrackCount)
            . $timeDivision;

        return $newHeader . $keptTracksData;
    }

    /**
     * Checks if a MIDI track body contains any channel voice messages (like Note On/Off).
     *
     * @param string $trackBody The binary data of the track chunk body ('MTrk').
     * @return bool Returns `true` if the track contains note events, `false` if it likely contains only meta events (tempo, lyrics, etc.).
     */
    private function isNoteTrack($trackBody)
    {
        $i = 0;
        $length = strlen($trackBody);
        $runningStatus = 0;

        while ($i < $length) {
            // Lewati delta-time
            while ($i < $length && (ord($trackBody[$i]) & 0x80)) {
                $i++;
            }
            $i++;

            if ($i >= $length) break;

            $statusByte = ord($trackBody[$i]);

            // Handle running status
            if (($statusByte & 0x80) === 0) {
                $statusByte = $runningStatus;
                $i--; // Ulangi pembacaan dari byte ini sebagai data
            } else {
                $runningStatus = $statusByte;
            }

            $messageType = $statusByte & 0xF0;

            // Jika ini adalah channel voice message (Note On, Note Off, Poly Aftertouch, CC, Program Change, Channel Aftertouch, Pitch Bend)
            if ($messageType >= 0x80 && $messageType <= 0xEF) {
                return true; // Ditemukan event nada, ini adalah track nada
            }

            // Lewati sisa event
            if ($statusByte === 0xFF) { // Meta Event
                $i += 2; // Lewati status dan tipe meta
                $len = 0;
                while (ord($trackBody[$i]) & 0x80) { $len = ($len << 7) | (ord($trackBody[$i]) & 0x7F); $i++; }
                $len = ($len << 7) | (ord($trackBody[$i]) & 0x7F); $i++;
                $i += $len;
            } else if ($statusByte === 0xF0 || $statusByte === 0xF7) { // SysEx
                $i++;
                while ($i < $length && ord($trackBody[$i]) !== 0xF7) { $i++; }
                $i++;
            } else {
                // Lewati byte data untuk event yang tidak kita proses secara detail di sini
                $i++;
            }
        }

        return false; // Tidak ada event nada yang ditemukan
    }

    /**
     * Returns a list of channels that were skipped during the filtering process.
     *
     * @return int[] An array of unique skipped channel numbers.
     */
    public function getSkippedChannels()
    {
        return array_values(array_unique($this->skippedChannels));
    }

    /**
     * Scans MIDI data and returns an array of all MIDI channels (0-15) that have events.
     * This operation is read-only and does not modify the MIDI data.
     *
     * @param string $midiData The binary data of the MIDI file.
     * @return int[] An array of unique channel numbers used in the file.
     */
    public function getUsedChannels($midiData)
    {
        $usedChannels = array();
        if (substr($midiData, 0, 4) !== 'MThd') {
            return array();
        }

        $headerLength = unpack('N', substr($midiData, 4, 4))[1];
        $offset = 8 + $headerLength;
        $totalLength = strlen($midiData);

        while ($offset < $totalLength) {
            if (substr($midiData, $offset, 4) !== 'MTrk') {
                $offset++;
                continue;
            }

            $trackLength = unpack('N', substr($midiData, $offset + 4, 4))[1];
            $trackBody = substr($midiData, $offset + 8, $trackLength);
            
            $i = 0;
            $bodyLength = strlen($trackBody);
            $runningStatus = 0;
            while ($i < $bodyLength) {
                // Skip delta-time
                while (ord($trackBody[$i]) & 0x80) { $i++; } $i++;
                if ($i >= $bodyLength) break;

                $statusByte = ord($trackBody[$i]);
                if (($statusByte & 0x80) === 0) { $statusByte = $runningStatus; $i--; } 
                else { $runningStatus = $statusByte; }

                $messageType = $statusByte & 0xF0;
                if ($messageType >= 0x80 && $messageType <= 0xEF) {
                    $usedChannels[$statusByte & 0x0F] = true;
                }
                $i += ($messageType === 0xC0 || $messageType === 0xD0) ? 2 : 3;
            }
            $offset += 8 + $trackLength;
        }
        return array_keys($usedChannels);
    }


    /**
     * Detects the track name from a 'Track Name' meta event (0xFF 0x03) within the track body.
     *
     * @param string $trackBody The binary data of the track chunk.
     * @return string|null The track name if found, or null otherwise.
     */
    private function detectTrackName($trackBody)
    {
        $length = strlen($trackBody);
        $i = 0;
        while ($i < $length) {
            // skip delta time
            while ($i < $length && (ord($trackBody[$i]) & 0x80)) {
                $i++;
            }
            $i++;
            if ($i >= $length) break;

            $statusByte = ord($trackBody[$i]);
            if ($statusByte === 0xFF) {
                $metaType = ord($trackBody[$i+1]);
                $i += 2;
                $metaLen = 0;
                while (ord($trackBody[$i]) & 0x80) {
                    $metaLen = ($metaLen << 7) | (ord($trackBody[$i]) & 0x7F);
                    $i++;
                }
                $metaLen = ($metaLen << 7) | (ord($trackBody[$i]) & 0x7F);
                $i++;
                if ($metaType === 0x03) {
                    return substr($trackBody, $i, $metaLen);
                }
                $i += $metaLen;
            } else {
                $i++;
            }
        }
        return null;
    }

    /**
     * Returns a list of all tracks detected from the last `filterByGetTrack` call.
     * Each array element contains 'index', 'name', 'channel', and 'skipped'.
     *
     * @return array A list of all track metadata.
     */
    public function getAllTracks()
    {
        return $this->allTracks;
    }

    /**
     * Filters MIDI data by removing tracks that use locked channels.
     * Meta tracks (without a channel) will always be kept.
     * 
     * @param string $midiData Binary string dari file MIDI
     * @param array $lockedChannels Channel list to skip (1-16)
     * @return string Filtered MIDI data as binary string
     */
    public function filter($midiData, $lockedChannels)
    {
        $this->lockedChannels = $lockedChannels;
        if (substr($midiData, 0, 4) !== 'MThd') {
            throw new Exception("Not a valid MIDI file.");
        }

        $headerLength = unpack('N', substr($midiData, 4, 4))[1];
        $headerData = substr($midiData, 8, $headerLength);

        $format = unpack('n', substr($headerData, 0, 2))[1];
        $timeDivision = substr($headerData, 4, 2);

        $filteredTracksData = '';
        $acceptedTrackCount = 0;

        $offset = 8 + $headerLength;
        $totalLength = strlen($midiData);

        $trackIndexCounter = 0;
        while ($offset < $totalLength) {

            if (substr($midiData, $offset, 4) !== 'MTrk') {
                $offset++;
                continue;
            }

            $trackLength = unpack('N', substr($midiData, $offset + 4, 4))[1];
            $trackBody = substr($midiData, $offset + 8, $trackLength);

            $trackChannel = $this->detectChannelFromTrack($trackBody);

            // Always keep the first track (index 0) as it contains crucial meta-events like tempo.
            // Also keep tracks that have no channel (pure meta tracks).
            if ($trackIndexCounter === 0 || $trackChannel === null) {
                $filteredTracksData .= 'MTrk' . pack('N', $trackLength) . $trackBody;
                $acceptedTrackCount++;
            } else {
                $channelNumber = $trackChannel + 1; // Convert 0-based to 1-based for checking

                if (in_array($channelNumber, $this->lockedChannels)) {
                    // ✅ track di-skip
                    $this->skippedChannels[] = $channelNumber;
                } else {
                    // ✅ track diterima
                    $filteredTracksData .= 'MTrk' . pack('N', $trackLength) . $trackBody;
                    $acceptedTrackCount++;
                }
            }

            $offset += 8 + $trackLength;
            $trackIndexCounter++;
        }

        $newHeader = 'MThd'
            . pack('N', 6)
            . pack('n', $format)
            . pack('n', $acceptedTrackCount)
            . $timeDivision;

        return $newHeader . $filteredTracksData;
    }

    /**
     * Filters MIDI data by removing events on locked channels from each track,
     * rather than removing entire tracks. This is a more granular and safer approach.
     *
     * @param string $midiData Binary string of the MIDI file.
     * @param int[] $lockedChannels Array of channel numbers (1-16) to filter out.
     * @return string The filtered MIDI data as a binary string.
     * @throws Exception If the MIDI data is invalid.
     */
    public function filterEvents($midiData, $lockedChannels)
    {
        $this->lockedChannels = array_map(function($ch) { return $ch - 1; }, $lockedChannels); // Convert to 0-15
        $this->skippedChannels = [];

        if (substr($midiData, 0, 4) !== 'MThd') {
            throw new Exception("Not a valid MIDI file.");
        }

        $headerLength = unpack('N', substr($midiData, 4, 4))[1];
        $headerChunk = substr($midiData, 0, 8 + $headerLength);

        $filteredTracksData = '';
        $offset = 8 + $headerLength;
        $totalLength = strlen($midiData);

        while ($offset < $totalLength) {
            if ($offset + 8 > $totalLength || substr($midiData, $offset, 4) !== 'MTrk') {
                break;
            }

            $trackLength = unpack('N', substr($midiData, $offset + 4, 4))[1];
            $trackBody = substr($midiData, $offset + 8, $trackLength);

            $newTrackBody = '';
            $p = 0;
            $bodyLen = strlen($trackBody);
            $runningStatus = 0;

            while ($p < $bodyLen) {
                // Read delta-time
                $dtBytes = '';
                while (ord($trackBody[$p]) & 0x80) {
                    $dtBytes .= $trackBody[$p];
                    $p++;
                }
                $dtBytes .= $trackBody[$p];
                $p++;

                $statusByte = ord($trackBody[$p]);

                // Handle running status
                if (($statusByte & 0x80) === 0) {
                    $eventData = $trackBody[$p];
                    $p++;
                    $statusByte = $runningStatus;
                } else {
                    $eventData = '';
                    $runningStatus = $statusByte;
                    $p++;
                }

                $messageType = $statusByte & 0xF0;
                $channel = $statusByte & 0x0F;

                // Determine event length
                $eventLen = 0;
                if ($messageType === 0xC0 || $messageType === 0xD0) {
                    $eventLen = 1;
                } else if ($messageType >= 0x80 && $messageType <= 0xEF) {
                    $eventLen = 2;
                } else if ($statusByte === 0xFF) { // Meta Event
                    $p++; // Skip meta type
                    $len = $this->readVarLen($trackBody, $p);
                    $p += $len;
                    $newTrackBody .= $dtBytes . substr($trackBody, $p - $len - 2, $len + 2);
                    continue;
                }

                $eventData .= substr($trackBody, $p, $eventLen);
                $p += $eventLen;

                // Keep event if channel is not locked
                if (!in_array($channel, $this->lockedChannels)) {
                    $newTrackBody .= $dtBytes . chr($statusByte) . $eventData;
                } else {
                    $this->skippedChannels[] = $channel + 1;
                }
            }
            $filteredTracksData .= 'MTrk' . pack('N', strlen($newTrackBody)) . $newTrackBody;
            $offset += 8 + $trackLength;
        }

        return $headerChunk . $filteredTracksData;
    }

    /**
     * Detects the MIDI channel used by a track.
     * This function will return the first channel found from a voice event (0x8n - 0xEn).
     * 
     * @param string $trackBody Binary string from track chunk
     * @return int|null
     */
    private function detectChannelFromTrack($trackBody)
    {
        $length = strlen($trackBody);
        $i = 0;
        $runningStatus = 0;

        while ($i < $length) {

            // Skip delta time
            while (ord($trackBody[$i]) & 0x80) {
                $i++;
            }
            $i++;

            if ($i >= $length) break;

            $statusByte = ord($trackBody[$i]);

            // Meta event
            if ($statusByte === 0xFF) {
                $i++;
                $metaLen = 0;

                while (ord($trackBody[$i]) & 0x80) {
                    $metaLen = ($metaLen << 7) | (ord($trackBody[$i]) & 0x7F);
                    $i++;
                }

                $metaLen = ($metaLen << 7) | (ord($trackBody[$i]) & 0x7F);
                $i++;
                $i += $metaLen;
                continue;
            }

            // SysEx
            if ($statusByte === 0xF0 || $statusByte === 0xF7) {
                while (ord($trackBody[$i]) !== 0xF7 && $i < $length) {
                    $i++;
                }
                $i++;
                continue;
            }

            // Running status
            if (($statusByte & 0x80) === 0) {
                $statusByte = $runningStatus;
                $i--;
            } else {
                $runningStatus = $statusByte;
            }

            $messageType = $statusByte & 0xF0;

            if ($messageType >= 0x80 && $messageType <= 0xEF) {
                return $statusByte & 0x0F;
            }

            if ($messageType === 0xC0 || $messageType === 0xD0) {
                $i += 2;
            } else {
                $i += 3;
            }
        }

        return null;
    }

    /**
     * Gets the length of a MIDI event in bytes, including the status byte.
     *
     * @param int $statusByte The status byte of the event.
     * @param string $body The track body data.
     * @param int &$p The current position pointer.
     * @return int The length of the event.
     */
    private function getEventLength($statusByte, $body, &$p)
    {
        $type = $statusByte & 0xF0;
        if ($type === 0xC0 || $type === 0xD0) {
            $len = 1;
            $p += $len;
            return $len;
        }
        if ($type >= 0x80 && $type <= 0xEF) {
            $len = 2;
            $p += $len;
            return $len;
        }
        if ($statusByte === 0xFF) { // Meta
            $p++; // skip meta type
            $len = $this->readVarLen($body, $p);
            $p += $len;
            return 0; // Length is variable, pointer is already advanced, so we don't need to return a length to skip
        }
        if ($statusByte === 0xF0 || $statusByte === 0xF7) { // SysEx
            // $p is at start of data for F0, or it's just F7
            if ($statusByte === 0xF7) {
                return 0; // F7 is a single byte event
            }
            $len = $this->readVarLen($body, $p);
            $p += $len;
            return 0; // Length is variable, pointer is already advanced
        }
        return 0; // Should not happen for valid MIDI
    }

    /**
     * Reads a variable-length quantity from a string and advances the pointer.
     *
     * @param string $str The binary string to read from.
     * @param int &$pos The current position pointer in the string.
     * @return int The decoded integer value.
     */
    private function readVarLen($str, &$pos)
    {
        $value = 0;
        if (isset($str[$pos]) && ($value = ord($str[$pos++])) & 0x80) {
            $value &= 0x7F;
            do {
                $value = ($value << 7) + (($c = ord($str[$pos++])) & 0x7F);
            } while ($c & 0x80);
        }
        return $value;
    }

    /**
     * Sets channel volume without adding a TrkEnd event.
     * This is a modified version of MidiVolume::setChannelVolume to avoid duplicate TrkEnd events
     * when used with the updated Midi::getMid() method.
     *
     * @param MidiVolume $midi The MIDI object instance.
     * @param int $chan The channel number (1-16).
     * @param int $vol The volume value (0-127).
     * @return void
     */
    private function setChannelVolume($midi, $chan, $vol)
    {
        $tracks = $midi->getTracks();
        $track = isset($tracks[0]) ? $tracks[0] : array();
        $i = 0;
        $cnt = count($track);
        while ($i < $cnt) {
            $msg = explode(" ", $track[$i]);
            if ($msg[0] != 0 || $msg[1] == 'On') break;
            // remove existing volume controller for specified channel
            if ($msg[1] == 'Par' && isset($msg[2]) && $msg[2] == "ch=$chan" && isset($msg[3]) && $msg[3] == 'c=7')
                array_splice($track, $i, 1);
            else $i++;
        }
        array_splice($track, $i, 0, "0 Par ch=$chan c=7 v=$vol");
        $midi->setTrack(0, $track);
    }
}