const fs = require('fs');
const path = require('path');
const { Resvg } = require('@resvg/resvg-js');

const root = path.join(__dirname, '..');
const svgPath = path.join(root, 'src', 'logo.svg');
const buildDir = path.join(root, 'build');
const pngPath = path.join(buildDir, 'icon.png');
const icoPath = path.join(buildDir, 'icon.ico');

function pngToIco(png) {
    const header = Buffer.alloc(6);
    header.writeUInt16LE(0, 0);
    header.writeUInt16LE(1, 2);
    header.writeUInt16LE(1, 4);

    const entry = Buffer.alloc(16);
    entry.writeUInt8(0, 0);
    entry.writeUInt8(0, 1);
    entry.writeUInt8(0, 2);
    entry.writeUInt8(0, 3);
    entry.writeUInt16LE(1, 4);
    entry.writeUInt16LE(32, 6);
    entry.writeUInt32LE(png.length, 8);
    entry.writeUInt32LE(header.length + entry.length, 12);

    return Buffer.concat([header, entry, png]);
}

fs.mkdirSync(buildDir, { recursive: true });

const svg = fs.readFileSync(svgPath);
const png = Buffer.from(
    new Resvg(svg, {
        fitTo: { mode: 'width', value: 256 },
    }).render().asPng(),
);

fs.writeFileSync(pngPath, png);
fs.writeFileSync(icoPath, pngToIco(png));

console.log(`Wrote ${pngPath}`);
console.log(`Wrote ${icoPath}`);
