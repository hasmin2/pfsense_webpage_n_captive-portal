// =============================================================================
// cp_tz_grid.inc 생성기 (#29 GPS 기반 로컬 타임존 오프라인 판정)
//
// 입력: @photostructure/tz-lookup (npm) — IANA timezone-boundary-builder 기반
//   위경도→타임존 룩업 테이블(해상은 nautical Etc/GMT±N 포함). 유지보수되는
//   포크라 tzdata 갱신이 따라온다. 생성 시점에 인터넷으로 최신판을 받아
//   리포에 박제 → 선상 런타임은 인터넷/위성통신 사용 0.
// 출력: etc/inc/cp_tz_grid.inc — PHP return array
//   res   : 격자 해상도(도). 0.5° = 셀 ~30nm (시계 표시용으로 충분)
//   zones : IANA zone 이름 배열 (rows 의 인덱스가 가리킴)
//   rows  : 위도 북→남 순서 행 배열. 각 행은 RLE 문자열
//           "zoneIdx(base36):runLength(base36),..." (서→동, 합계 = 720셀)
//
// 사용 (개발 PC, 인터넷 필요):
//   mkdir %TEMP%\tzgrid_build && cd %TEMP%\tzgrid_build
//   npm init -y && npm install @photostructure/tz-lookup
//   set NODE_PATH=%TEMP%\tzgrid_build\node_modules
//   node tools/generate_cp_tz_grid.js [출력경로]
// =============================================================================
'use strict';
const fs = require('fs');

let tzlookup;
try {
    tzlookup = require('@photostructure/tz-lookup');
} catch (e) {
    console.error('@photostructure/tz-lookup 를 찾을 수 없습니다. NODE_PATH 를 확인하세요.');
    process.exit(1);
}
let pkgVersion = 'unknown';
try { pkgVersion = require('@photostructure/tz-lookup/package.json').version; } catch (e) {}

const outPath = process.argv[2] || 'etc/inc/cp_tz_grid.inc';

const RES = 0.5;
const ROWS = Math.round(180 / RES);   // 360
const COLS = Math.round(360 / RES);   // 720

const zones = [];
const zoneIdx = new Map();
function idxOf(zone) {
    let i = zoneIdx.get(zone);
    if (i === undefined) {
        i = zones.length;
        zones.push(zone);
        zoneIdx.set(zone, i);
    }
    return i;
}

// 만일 룩업이 실패하면(정상 좌표에선 없음) nautical 존으로 메움
function nauticalZone(lon) {
    const off = Math.round(lon / 15); // 양수=동경=UTC+N
    if (off === 0) { return 'Etc/GMT'; }
    // IANA Etc/GMT 부호는 POSIX 반전: UTC+9 = Etc/GMT-9
    return 'Etc/GMT' + (off > 0 ? '-' : '+') + Math.abs(off);
}

const rows = [];
for (let r = 0; r < ROWS; r++) {
    const lat = 90 - (r + 0.5) * RES;          // 89.75 → -89.75 (북→남)
    const tokens = [];
    let prev = -1, run = 0;
    for (let c = 0; c < COLS; c++) {
        const lon = -180 + (c + 0.5) * RES;    // -179.75 → 179.75 (서→동)
        let zone;
        try { zone = tzlookup(lat, lon); } catch (e) { zone = null; }
        if (!zone) { zone = nauticalZone(lon); }
        const i = idxOf(zone);
        if (i === prev) { run++; }
        else {
            if (prev >= 0) { tokens.push(prev.toString(36) + ':' + run.toString(36)); }
            prev = i; run = 1;
        }
    }
    tokens.push(prev.toString(36) + ':' + run.toString(36));
    rows.push(tokens.join(','));
}

// 검증: 각 행 run 합계 = COLS
for (let r = 0; r < ROWS; r++) {
    let sum = 0;
    for (const t of rows[r].split(',')) { sum += parseInt(t.split(':')[1], 36); }
    if (sum !== COLS) {
        console.error('행 ' + r + ' run 합계 불일치: ' + sum);
        process.exit(1);
    }
}

function phpStr(s) { return "'" + s.replace(/\\/g, '\\\\').replace(/'/g, "\\'") + "'"; }

let php = '';
php += '<?php\n';
php += '// =============================================================================\n';
php += '// 위경도 -> IANA 타임존 오프라인 격자 (#29) — tools/generate_cp_tz_grid.js 생성물\n';
php += '// 소스: @photostructure/tz-lookup v' + pkgVersion + ' (IANA timezone-boundary-builder)\n';
php += '// 생성: ' + new Date().toISOString().slice(0, 10) + ' / 해상도 ' + RES + '\xb0 / zones ' + zones.length + '개\n';
php += '// 직접 수정 금지 — 갱신은 생성기 재실행 (헤더의 사용법 참고)\n';
php += '// =============================================================================\n';
php += 'return array(\n';
php += "'res' => " + RES + ',\n';
php += "'zones' => array(\n";
for (let i = 0; i < zones.length; i += 8) {
    php += zones.slice(i, i + 8).map(phpStr).join(',') + ',\n';
}
php += '),\n';
php += "'rows' => array(\n";
for (const row of rows) {
    php += phpStr(row) + ',\n';
}
php += '),\n';
php += ');\n';

fs.writeFileSync(outPath, php);
console.log('OK: ' + outPath);
console.log('  zones: ' + zones.length + ', rows: ' + ROWS + ' x ' + COLS + '셀, 파일 ' +
    Math.round(php.length / 1024) + 'KB (tz-lookup v' + pkgVersion + ')');
