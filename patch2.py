import sys

with open('D:/MagicServer/www/MusicXML/example/midi-parser.js', 'r', encoding='utf-8') as f:
    content = f.read()

# Fix 1: Global normalization
old_norm = '''            // Calculate the start tick of the first musical note in this track
            let firstNoteTick = Infinity;
            for (const n of notes) {
                if (n.ticks < firstNoteTick) firstNoteTick = n.ticks;
            }

            if (options.normalize && firstNoteTick !== Infinity) {
                for (const n of notes) n.ticks -= firstNoteTick;
                for (const l of lyrics) l.ticks -= firstNoteTick;
            }'''

new_norm = '''            // Calculate the start tick of the first musical note in this track
            let firstNoteTick = Infinity;
            for (const n of notes) {
                if (n.ticks < firstNoteTick) firstNoteTick = n.ticks;
            }
            // (Normalization moved to global scope below)'''

content = content.replace(old_norm, new_norm)

old_global = '''        // Find the earliest note tick across all tracks
        let globalFirstTick = Infinity;
        tracks.forEach(t => {
            if (t.startTick < globalFirstTick) globalFirstTick = t.startTick;
        });'''

new_global = '''        // Find the earliest note tick across all tracks
        let globalFirstTick = Infinity;
        tracks.forEach(t => {
            if (t.startTick < globalFirstTick) globalFirstTick = t.startTick;
        });

        // Always normalize globally by default if options.normalize is not explicitly false
        if (options.normalize !== false && globalFirstTick !== Infinity && globalFirstTick > 0) {
            tracks.forEach(t => {
                t.notes.forEach(n => n.ticks -= globalFirstTick);
                t.lyrics.forEach(l => l.ticks -= globalFirstTick);
                t.controllers.forEach(c => c.ticks = Math.max(0, c.ticks - globalFirstTick));
                t.pitchBends.forEach(pb => pb.ticks = Math.max(0, pb.ticks - globalFirstTick));
                t.startTick -= globalFirstTick;
            });
            timeSignatures.forEach(ts => ts.ticks = Math.max(0, ts.ticks - globalFirstTick));
            keySignatures.forEach(ks => ks.ticks = Math.max(0, ks.ticks - globalFirstTick));
            tempos.forEach(tmp => tmp.ticks = Math.max(0, tmp.ticks - globalFirstTick));
            channelProgramChanges.forEach(changes => changes.forEach(pc => pc.ticks = Math.max(0, pc.ticks - globalFirstTick)));
            channelBankChanges.forEach(changes => changes.forEach(bc => bc.ticks = Math.max(0, bc.ticks - globalFirstTick)));
            maxTicks -= globalFirstTick;
        }'''

content = content.replace(old_global, new_global)

with open('D:/MagicServer/www/MusicXML/example/midi-parser.js', 'w', encoding='utf-8') as f:
    f.write(content)
print("Patched midi-parser.js")
