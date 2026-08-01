import sys

with open('D:/MagicServer/www/MusicXML/example/midi-parser.js', 'r', encoding='utf-8') as f:
    content = f.read()
    
# Let's check if there's any logic combining lyrics
print(content.find('lyrics.push'))
