import sys

with open('D:/MagicServer/www/MusicXML/example/midi-parser.js', 'r', encoding='utf-8') as f:
    content = f.read()

old_code = '''        // Always normalize globally by default if options.normalize is not explicitly false
        if (options.normalize !== false && globalFirstTick !== Infinity && globalFirstTick > 0) {
            tracks.forEach(t => {
                t.notes.forEach(n => n.ticks -= globalFirstTick);
                t.lyrics.forEach(l => l.ticks -= globalFirstTick);'''

new_code = '''        // Combine lyrics that fall within the same note's duration
        tracks.forEach(t => {
            const mergedLyrics = [];
            if (t.lyrics.length > 0 && t.notes.length > 0) {
                // Ensure sorted
                t.notes.sort((a, b) => a.ticks - b.ticks);
                t.lyrics.sort((a, b) => a.ticks - b.ticks);
                
                let lyricIdx = 0;
                t.notes.forEach(note => {
                    const nStart = note.ticks;
                    const nEnd = note.ticks + note.durationTicks;
                    
                    while (lyricIdx < t.lyrics.length && t.lyrics[lyricIdx].ticks < nStart) {
                        lyricIdx++;
                    }
                    
                    let combined = "";
                    while (lyricIdx < t.lyrics.length && t.lyrics[lyricIdx].ticks >= nStart && t.lyrics[lyricIdx].ticks < nEnd) {
                        combined += (combined ? " " : "") + t.lyrics[lyricIdx].text;
                        lyricIdx++;
                    }
                    if (combined) {
                        mergedLyrics.push({ ticks: nStart, text: combined });
                    }
                });
                t.lyrics = mergedLyrics;
            }
        });

        // Always normalize globally by default if options.normalize is not explicitly false
        if (options.normalize !== false && globalFirstTick !== Infinity && globalFirstTick > 0) {
            tracks.forEach(t => {
                t.notes.forEach(n => n.ticks -= globalFirstTick);
                t.lyrics.forEach(l => l.ticks -= globalFirstTick);'''

content = content.replace(old_code, new_code)

with open('D:/MagicServer/www/MusicXML/example/midi-parser.js', 'w', encoding='utf-8') as f:
    f.write(content)
print("Patched midi-parser.js lyrics merging")
