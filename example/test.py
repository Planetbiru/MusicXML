import json
# just write a quick script to parse the binary midi file
import struct

def parse_midi(filename):
    with open(filename, 'rb') as f:
        data = f.read()
    
    # search for time signature meta event: FF 58 04 nn dd cc bb
    idx = 0
    while True:
        idx = data.find(b'\xFF\x58\x04', idx)
        if idx == -1:
            break
        nn = data[idx+3]
        dd = data[idx+4]
        print(f"Time Signature: {nn}/{2**dd}")
        idx += 1

parse_midi('example.mid')
