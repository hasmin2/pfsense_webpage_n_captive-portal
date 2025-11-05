$(document).ready(function(){
    resizingAct()
    mobileMenu();
    scrollY()
})

// dim 생성
function dimMaker() {
    if($('body').find('.dim').length > 0){
        return;
    }
    $('body').append('<div class="dim"></div>');
    bodyHidden();
}

// dim 제거
function dimRemove() {
    $('.dim').remove();
    bodyAuto();
}

// body scroll hidden
function bodyHidden() {
    $('body').css('overflow', 'hidden');
}

// body scroll auto
function bodyAuto() {
    $('body').css('overflow', '')
}

// 팝업열기
function popOpen(target){
    $("." + target).addClass('on');
    scrollY()
}

// 팝업닫기
function popClose(target) {
    $("." + target).removeClass('on');
    dimRemove();
}

// dim 옵션 팝업 열기
function popOpenAndDim(target, isDim){
    popOpen(target);
    if(isDim){
        dimMaker();
    }
}
function safeRenderScheduleRows(input) {
    const rows = toArray(input);
    // 진단 로그 (필요시)
    // console.log('rows(normalized)=', rows);
    renderScheduleRows(rows); // 이제 rows.map 가능
}
function toArray(input) {
    if (input == null) return [];
    if (Array.isArray(input)) return input;

    // 문자열 JSON으로 온 경우
    if (typeof input === 'string') {
        try { return toArray(JSON.parse(input)); } catch { return []; }
    }

    // NodeList/HTMLCollection 인 경우
    if (typeof input.length === 'number' && typeof input.item === 'function') {
        return Array.from(input);
    }

    // 단일 객체
    return [input];
}
function popOpenScheduler(target, isDim, data){
    popOpen(target);
    if(isDim){
        dimMaker();
    }
    if(data) {
        const { first, rest } = takeFirstAndRest(data);
        const el = document.getElementById('userIdHidden');
        if (el) el.value = first?.id ?? '';
        safeRenderScheduleRows(rest);


    }
}

function takeFirstAndRest(input) {
    let arr = input;
    if (!Array.isArray(arr)) {
        const t = String(input ?? '').trim();
        const json = t.startsWith('[') ? t : `[${t}]`;
        arr = JSON.parse(json);
    }
    const first = arr.shift() ?? null;
    return { first, rest: arr };
}

    document.addEventListener("DOMContentLoaded", function() {
    const gmtElem = document.getElementById("gmt-modify");

    gmtElem.addEventListener("click", function() {
    // 기존 텍스트 값 추출
    const current = gmtElem.textContent.replace("GMT ","").trim();

    // input 박스로 교체
    gmtElem.innerHTML = `
            GMT <input type="text" id="gmt-input" value="${current}" size="5">
            <button id="gmt-save">저장</button>
        `;

    // 저장 버튼 이벤트
    document.getElementById("gmt-save").addEventListener("click", function() {
    const newValue = document.getElementById("gmt-input").value;
    gmtElem.innerHTML = "GMT " + newValue;
});
});
});

const pad2 = n => String(n).padStart(2, '0');

// 요일: 0=일 ~ 6=토 (한국어 라벨)
const DAYS = [
    { v: 1, t: 'Mon' }, { v: 2, t: 'Tue' },
    { v: 3, t: 'Wed' }, { v: 4, t: 'Thu' }, { v: 5, t: 'Fri' }, { v: 6, t: 'Sat' },{ v: 7, t: 'Sun' }
];

// 시간/분 옵션 생성
function buildHourOptions(selected = null) {
    let html = '';
    for (let h = 0; h <= 23; h++) {
        const vv = pad2(h);
        html += `<option value="${vv}" ${vv===selected ? 'selected' : ''}>${vv}</option>`;
    }
    return html;
}
function buildMinOptions(selected=null){
    let html='';
    for(let m=0; m<60; m+=10){
        const vv=pad2(m);
        html+=`<option value="${vv}" ${vv===selected?'selected':''}>${vv}</option>`;
    }
    return html;
}

function buildDayOptions(selectedArr = []) {
    let html = '';
    const set = new Set((selectedArr || []).map(String)); // ["0","1",...]
    DAYS.forEach(d => {
        const sel = set.has(String(d.v)) ? 'selected' : '';
        html += `<option value="${d.v}" ${sel}>${d.t}</option>`;
    });
    return html;
}
document.addEventListener('DOMContentLoaded', function () {
    var field = document.getElementById('__csrf_magic');
    if (field && typeof window.csrfMagicToken === 'string') {
        field.value = window.csrfMagicToken; // "sid:...,timestamp" 형식 그대로
    }

    // 혹시라도 csrf-magic.js가 form submit을 XHR로 바꾸는 경우를 피하려면,
    // 명시적으로 기본 submit을 트리거(네이티브 네비게이션)하면 됩니다.
    document.getElementById('logoutForm').addEventListener('submit', function (e) {
        // 여기서는 e.preventDefault() 절대 금지 (네비게이션해야 함)
    });
});

function buildRow(data = {}) {
    const enabled = !!data.enabled;
    const [fh = '00', fm = '00'] = (data.from || '00:00').split(':');
    const [th = '00', tm = '00'] = (data.to   || '00:00').split(':');
    const days = Array.isArray(data.days) ? data.days : [];

    return `
    <tr>
      <td style="text-align:center;">
        <!--input type="checkbox" name="isenabledlist[]" value = "1" ${enabled ? 'checked' : ''}-->
        <input type="checkbox" class="chk-enable select v1" name="isenabledlist[]" value = "1" ${enabled ? 'checked' : ''} style="appearance:auto;display:inline-block;width:16px;height:16px;opacity:1;visibility:visible;">
      </td>
      <td style="text-align:center;">
        <select class="sel-from-hour select v1">${buildHourOptions(fh)}</select>
      </td>
      <td style="text-align:center;">
        <select class="sel-from-min  select v1">${buildMinOptions(fm)}</select>
      </td>
      <td style="text-align:center;">
        <select class="sel-to-hour   select v1">${buildHourOptions(th)}</select>
      </td>
      <td style="text-align:center;">
        <select class="sel-to-min    select v1">${buildMinOptions(tm)}</select>
      </td>
      <td style="text-align:center;">
        <!-- 멀티 선택 드롭다운 -->
        <select class="sel-days select v1" multiple size="4" title="Ctrl/⌘로 다중선택">
          ${buildDayOptions(days)}
        </select>
      </td>
    </tr>
  `;
}

document.addEventListener("DOMContentLoaded", function() {
    const gmtText = document.getElementById("gmt-modify");
    const gmtForm = document.getElementById("gmtForm");
    const gmtVal  = document.getElementById("gmtVal");

    gmtText.addEventListener("click", function() {
        let current = parseInt(gmtText.innerText.replace("GMT", "").trim()) || 0;
        let input = prompt("Please input time difference regarding current location (-11 ~ 12):", current);

        if (input !== null) {
            let val = parseInt(input, 10);
            if (!isNaN(val) && val >= -11 && val <= 12) {
                gmtText.innerText = "GMT " + val;
                gmtVal.value = val;      // hidden input 값 세팅
                gmtForm.submit();        // form 전송 (페이지 이동 발생)
            } else {
                alert("Please input correct range of number (-11 ~ 12)");
            }
        }
    });
});

// 표에 행들 추가
function renderScheduleRows(rows = []) {
    const tbody = document.getElementById('sched-body');
    tbody.innerHTML = rows.map(r => buildRow(r)).join('');
}

// 현재 표의 값을 JSON으로 수집 (서브밋 전에 호출)
// 반환 예: [{enabled:true, from:"08:00", to:"17:30", days:[1,2,3]} , ...]
function collectSchedule() {
    const out = [];
    const id = document.getElementById('userIdHidden')?.value ?? '';
    out.push({userid: id});
    document.querySelectorAll('#sched-body tr').forEach(tr => {
        const en = tr.querySelector('.chk-enable').checked;
        const fh = tr.querySelector('.sel-from-hour').value;
        const fm = tr.querySelector('.sel-from-min').value;
        const th = tr.querySelector('.sel-to-hour').value;
        const tm = tr.querySelector('.sel-to-min').value;
        const sel = tr.querySelector('.sel-days');
        const days = Array.from(sel.selectedOptions).map(o => Number(o.value));
        out.push({
            enabled: en,
            from: `${fh}:${fm}`,
            to:   `${th}:${tm}`,
            days
        });
    });
    return out;
}

// (선택) crewscheduler 폼 제출 시 스케줄 값을 숨은 필드에 넣어 전송
/*function submit_crewscheduler() {
    const form = document.getElementById('crewscheduler');
    // hidden input 없으면 생성
    let hidden = form.querySelector('input[name="schedule_json"]');
    if (!hidden) {
        hidden = document.createElement('input');
        hidden.type = 'hidden';
        hidden.name = 'schedule_json';
        form.appendChild(hidden);
    }
    const schedule = collectSchedule();
    hidden.value = JSON.stringify(schedule);
    const diff = diffMinutes(schedule.value.from, schedule.value.to);
    if(diff<0){
        alert ("To time should be greater than from time");
        return false;
    }
    else{
        form.submit();
    }

}
function diffMinutes(from, to) {
    const [fh, fm] = from.split(':').map(Number);
    const [th, tm] = to.split(':').map(Number);

    const fromTotal = fh * 60 + fm;
    const toTotal   = th * 60 + tm;

    if (toTotal <= fromTotal) return null; // 잘못된 시간

    return toTotal - fromTotal;
}*/
function extractFromTo(item) {
    if (!item) return { from: null, to: null };

    if (Array.isArray(item)) {
        // ["09:00","17:30", ...]
        return { from: item[0] ?? null, to: item[1] ?? null };
    }

    // 객체형
    const from = item.from ?? item.start ?? item.startTime ?? item.fromTime ?? null;
    const to   = item.to   ?? item.end   ?? item.endTime   ?? item.toTime   ?? null;
    return { from, to };
}

// "HH:MM" 형식 시간 차이를 분으로 반환 (to <= from 이면 null)
function diffMinutes(from, to) {
    if (typeof from !== 'string' || typeof to !== 'string') return null;

    const [fh, fm] = from.split(':').map(Number);
    const [th, tm] = to.split(':').map(Number);

    if (
        Number.isNaN(fh) || Number.isNaN(fm) || Number.isNaN(th) || Number.isNaN(tm) ||
        fh < 0 || fh > 23 || th < 0 || th > 23 ||
        fm < 0 || fm > 59 || tm < 0 || tm > 59
    ) return null;

    const fromTotal = fh * 60 + fm;
    const toTotal   = th * 60 + tm;
    return (toTotal > fromTotal) ? (toTotal - fromTotal) : null; // 동일/역전 → null
}

// crewscheduler 폼 제출: 스케줄 JSON 저장 + 시간 유효성 검사
function submit_crewscheduler() {
    const form = document.getElementById('crewscheduler');

    // hidden input 준비
    let hidden = form.querySelector('input[name="schedule_json"]');
    if (!hidden) {
        hidden = document.createElement('input');
        hidden.type = 'hidden';
        hidden.name = 'schedule_json';
        form.appendChild(hidden);
    }

    // 스케줄 수집
    const schedule = collectSchedule();
    const list = Array.isArray(schedule) ? schedule : [schedule];

    if (!list.length) {
        alert('Empty schedule');
        return false;
    }

    // from < to 검증
    for (let i = 0; i < list.length; i++) {
        const { from, to } = extractFromTo(list[i]);
        if (typeof from !== 'string' || typeof to !== 'string'){
            continue;
        }
        const d = diffMinutes(from, to);
        if (d === null) {
            alert("From time is larger than to time, please verify.");
            return false; // 제출 중단
        }
    }

    // 통과 시 JSON 저장 후 제출
    hidden.value = JSON.stringify(schedule);
    form.submit();
    return true;
}

function resizingAct(){
    $(window).resize(function(){
        let windowWidth = $(window).width()
        
        if(windowWidth > 1440) {
            dimRemove();
            closeMenu();
            $('.popup').removeClass('on')
        }
    })
}

function mobileMenu(){
    openMenuAct();
    closeMenuAct()
}

// 모바일 메뉴 열기
function openMenuAct(){
    $('#wrapper #sidebar .brand .btn-menu-open').click(function(){
        openMenu()
    })
}
function openMenu(){
    $('#wrapper #sidebar #lnb').addClass('on');
    dimMaker()
}

// 모바일 메뉴 열기
function closeMenuAct(){
    $('#wrapper #sidebar #lnb .btn-menu-close').click(function(){
        closeMenu()
    })
}

function closeMenu(){
    $('#wrapper #sidebar #lnb').removeClass('on');
    dimRemove()
}

function scrollY(){
    $('.scroll-y').each(function(){
        $(this).mCustomScrollbar();
    })
}