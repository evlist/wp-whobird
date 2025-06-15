// Improved sync script: handles plugin title line and parses header fields properly

const fs = require('fs');
const path = require('path');

const HEADER_FIELDS = [
  'Plugin Name',
  'Plugin URI',
  'Description',
  'Version',
  'Requires at least',
  'Tested up to',
  'Requires PHP',
  'Author',
  'Author URI',
  'License',
  'License URI',
  'License File',
  'Stable tag',
  'Contributors',
  'Tags'
];

// Helper: Converts readme.txt field names to PHP header keys if needed
function toPhpHeaderKey(key) {
  if (key === 'Stable tag') return 'Version';
  return key;
}

function parseReadmeHeader(readme) {
  const lines = readme.split('\n');
  let header = {};
  let idx = 0;

  // If the first line is the plugin name/title, use it
  if (lines[0].startsWith('===')) {
    header['Plugin Name'] = lines[0].replace(/=/g, '').trim();
    idx = 1;
  }

  for (; idx < lines.length; idx++) {
    const line = lines[idx];
    if (line.trim().startsWith('==')) break; // Stop at first section
    const match = line.match(/^([A-Za-z ]+):\s*(.*)$/);
    if (match && HEADER_FIELDS.includes(match[1])) {
      header[match[1]] = match[2].trim();
    }
  }
  return header;
}

function generatePhpHeader(header) {
  const lines = [];
  lines.push('/**');
  HEADER_FIELDS.forEach(key => {
    if (header[key]) {
      lines.push(` * ${toPhpHeaderKey(key)}: ${header[key]}`);
    }
  });
  lines.push(' */');
  return lines.join('\n');
}

// --- Main ---
const readmePath = path.join(process.cwd(), 'readme.txt');
if (!fs.existsSync(readmePath)) {
  console.error('Could not find readme.txt');
  process.exit(1);
}
const readme = fs.readFileSync(readmePath, 'utf8');
const header = parseReadmeHeader(readme);
const phpHeader = generatePhpHeader(header);

console.log(phpHeader);

