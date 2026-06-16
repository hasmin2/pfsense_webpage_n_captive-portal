// =============================================================================
// cp_searegions.js 생성기 (#28 해역명 표시 데이터)
//
// 입력: Natural Earth 10m geography_marine_polys GeoJSON — 퍼블릭 도메인
//   다운로드: https://raw.githubusercontent.com/nvkelso/natural-earth-vector/master/geojson/ne_10m_geography_marine_polys.geojson
// 방식: 해역 폴리곤 -> 경계상자(bbox) 자동 추출, 면적 오름차순 정렬
//   (작은 해역이 먼저 매칭 = 가장 구체적인 이름 우선). 대양(ocean)급은 제외 —
//   페이지의 대양 폴백(EASTERN PACIFIC 등)이 담당.
// 출력: usr/local/www/js/cp_searegions.js —
//   var CP_SEAREGIONS = [ ['NAME', latMin, latMax, lonMin, lonMax], ... ]
//
// 사용: node tools/generate_cp_searegions.js <marine.geojson> [출력경로]
// =============================================================================
'use strict';
const fs = require('fs');

const inPath = process.argv[2];
const outPath = process.argv[3] || 'usr/local/www/js/cp_searegions.js';
if (!inPath) { console.error('usage: node generate_cp_searegions.js <marine.geojson> [out.js]'); process.exit(1); }

const gj = JSON.parse(fs.readFileSync(inPath, 'utf8'));
const boxes = [];
let skippedOcean = 0, skippedHuge = 0, skippedDateline = 0;

for (const f of gj.features) {
    const props = f.properties || {};
    const cla = String(props.featurecla || '').toLowerCase();
    const name = String(props.name_en || props.name || '').trim();
    if (!name) { continue; }
    if (cla === 'ocean' || /\bocean\b/i.test(name)) { skippedOcean++; continue; }

    const geom = f.geometry;
    if (!geom) { continue; }
    const polys = geom.type === 'Polygon' ? [geom.coordinates]
        : geom.type === 'MultiPolygon' ? geom.coordinates : [];

    for (const poly of polys) {
        // 외곽 링만 사용
        const ring = poly[0];
        let latMin = 90, latMax = -90, lonMin = 180, lonMax = -180;
        for (const pt of ring) {
            const lon = pt[0], lat = pt[1];
            if (lat < latMin) latMin = lat;
            if (lat > latMax) latMax = lat;
            if (lon < lonMin) lonMin = lon;
            if (lon > lonMax) lonMax = lon;
        }
        const w = lonMax - lonMin, h = latMax - latMin;
        if (w > 180) { skippedDateline++; continue; }       // 날짜변경선 횡단 파트 제외
        if (w * h > 3000) { skippedHuge++; continue; }      // 대양급 거대 박스 제외
        if (w * h < 0.02) { continue; }                     // 초소형 슬리버 제외
        boxes.push([
            name.toUpperCase().replace(/'/g, ''),
            Math.round(latMin * 100) / 100, Math.round(latMax * 100) / 100,
            Math.round(lonMin * 100) / 100, Math.round(lonMax * 100) / 100,
            w * h
        ]);
    }
}

// 면적 오름차순 = 구체적(작은) 해역 우선 매칭
boxes.sort((a, b) => a[5] - b[5]);

let js = '// 자동 생성 파일 — 수정 금지. tools/generate_cp_searegions.js 로 재생성.\n';
js += '// 원본: Natural Earth 10m geography_marine_polys (퍼블릭 도메인), bbox 추출/면적 오름차순.\n';
js += '// 생성: ' + new Date().toISOString().slice(0, 10) + ', ' + boxes.length + ' boxes\n';
js += 'var CP_SEAREGIONS = [\n';
js += boxes.map(b => "['" + b[0] + "'," + b[1] + ',' + b[2] + ',' + b[3] + ',' + b[4] + ']').join(',\n');
js += '\n];\n';
fs.writeFileSync(outPath, js, 'utf8');
console.log('OK: ' + boxes.length + ' boxes -> ' + outPath +
    ' (skip: ocean ' + skippedOcean + ', huge ' + skippedHuge + ', dateline ' + skippedDateline + ')');
