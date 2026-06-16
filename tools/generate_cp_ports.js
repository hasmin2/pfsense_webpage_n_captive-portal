// =============================================================================
// cp_ports.js 생성기 (#28 항구 미니맵 데이터)
//
// 입력: NGA World Port Index (Pub 150) CSV — 퍼블릭 도메인
//   다운로드: https://msi.nga.mil/api/publications/download?key=16920959/SFH00000/UpdatedPub150.csv
// 필터: Harbor Size = Large/Medium (주요 항구만 — "최근접 3개" 표시가 소형
//   부두로 채워지지 않도록. A안 결정, CLAUDE.md #28 참고)
// 출력: usr/local/www/js/cp_ports.js — var CP_PORTS = [ ['NAME', lat, lon], ... ]
//
// 사용: node tools/generate_cp_ports.js <WPI.csv> [출력경로]
// =============================================================================
'use strict';
const fs = require('fs');

const inPath = process.argv[2];
const outPath = process.argv[3] || 'usr/local/www/js/cp_ports.js';
if (!inPath) { console.error('usage: node generate_cp_ports.js <WPI.csv> [out.js]'); process.exit(1); }

// 따옴표 필드 지원 CSV 파서 (WPI 는 필드 내 콤마/따옴표 포함)
function parseCsv(text) {
    const rows = []; let row = [], field = '', inQ = false;
    for (let i = 0; i < text.length; i++) {
        const c = text[i];
        if (inQ) {
            if (c === '"') {
                if (text[i + 1] === '"') { field += '"'; i++; }
                else { inQ = false; }
            } else { field += c; }
        } else if (c === '"') { inQ = true; }
        else if (c === ',') { row.push(field); field = ''; }
        else if (c === '\n') { row.push(field.replace(/\r$/, '')); rows.push(row); row = []; field = ''; }
        else { field += c; }
    }
    if (field !== '' || row.length) { row.push(field.replace(/\r$/, '')); rows.push(row); }
    return rows;
}

const rows = parseCsv(fs.readFileSync(inPath, 'utf8'));
const header = rows[0];
function col(name) {
    const i = header.findIndex(h => h.trim() === name);
    if (i < 0) { console.error('column not found: ' + name); process.exit(1); }
    return i;
}
const cName = col('Main Port Name');
const cSize = col('Harbor Size');
const cLat = col('Latitude');
const cLon = col('Longitude');

const seen = new Set();
const ports = [];
for (let r = 1; r < rows.length; r++) {
    const row = rows[r];
    if (row.length < header.length - 2) { continue; }
    const size = (row[cSize] || '').trim();
    if (size !== 'Large' && size !== 'Medium') { continue; }
    const name = (row[cName] || '').trim().toUpperCase().replace(/'/g, '');
    const lat = parseFloat(row[cLat]);
    const lon = parseFloat(row[cLon]);
    if (!name || !isFinite(lat) || !isFinite(lon) || (lat === 0 && lon === 0)) { continue; }
    const key = name + '|' + lat.toFixed(2) + '|' + lon.toFixed(2);
    if (seen.has(key)) { continue; }
    seen.add(key);
    ports.push([name, Math.round(lat * 1000) / 1000, Math.round(lon * 1000) / 1000]);
}
ports.sort((a, b) => a[0].localeCompare(b[0]));

let js = '// 자동 생성 파일 — 수정 금지. tools/generate_cp_ports.js 로 재생성.\n';
js += '// 원본: NGA World Port Index (Pub 150, 퍼블릭 도메인), Harbor Size=Large/Medium 필터.\n';
js += '// 생성: ' + new Date().toISOString().slice(0, 10) + ', ' + ports.length + ' ports\n';
js += 'var CP_PORTS = [\n';
js += ports.map(p => "['" + p[0].replace(/'/g, '') + "'," + p[1] + ',' + p[2] + ']').join(',\n');
js += '\n];\n';
fs.writeFileSync(outPath, js, 'utf8');
console.log('OK: ' + ports.length + ' ports -> ' + outPath);
