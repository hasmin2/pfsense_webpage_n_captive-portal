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

function popOpenScheduler(target, isDim, data) {
    popOpen(target);

    if (isDim) {
        dimMaker();
    }

    let parsed;
    try {
        parsed = JSON.parse(data);
    } catch (e) {
        alert('JSON parse error: ' + e.message);
        return;
    }

    if (!Array.isArray(parsed) || parsed.length === 0) {
        alert('schedule data is invalid');
        return;
    }
    const first = parsed[0];
    const rest  = parsed.slice(1);

    const el = document.getElementById('userIdHidden');
    if (el) {
        el.value = first?.id ?? '';
    }
    buildScheduleRows(rest);
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
    /*document.getElementById('logoutForm').addEventListener('submit', function (e) {
        // 여기서는 e.preventDefault() 절대 금지 (네비게이션해야 함)
    });*/
});

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


const SCHED_DAYS = [
    { key: 'mon', label: 'Mon' },
    { key: 'tue', label: 'Tue' },
    { key: 'wed', label: 'Wed' },
    { key: 'thu', label: 'Thu' },
    { key: 'fri', label: 'Fri' },
    { key: 'sat', label: 'Sat' },
    { key: 'sun', label: 'Sun' }
    ];

function buildNumberOptions(max, step = 1) {
    let html = '';
    for (let i = 0; i <= max; i += step) {
        const v = String(i).padStart(2, '0');
        html += '<option value="' + v + '">' + v + '</option>';
    }
    return html;
}

function buildDayChips(rowIdx) {
    return SCHED_DAYS.map(day => `
            <span class="sched-day-chip">
                <input type="checkbox" id="d${rowIdx}_${day.key}" name="day_${rowIdx}" value="${day.key}">
                <label for="d${rowIdx}_${day.key}">${day.label}</label>
            </span>
        `).join('');
}

function buildScheduleRows(scheduleRows = []) {
    const tbody = document.getElementById('sched-body');
    if (!tbody) return;

    const hourOptions = buildNumberOptions(23,1);
    const minOptions  = buildNumberOptions(59, 10);

    // 데이터 개수만큼 만들되, 최소 3행은 유지
    const rowCount = Math.max(3, Array.isArray(scheduleRows) ? scheduleRows.length : 0);

    let html = '';
    for (let i = 0; i < rowCount; i++) {
        html += `
            <tr>
                <td><span class="sched-row-num">${i + 1}</span></td>
                    <td>
                        <div class="check v1" style="display:flex; justify-content:center;">
                            <input type="checkbox" class="sched-act-checkbox" name="act_${i}" id="act_${i}">
                            <label for="act_${i}"></label>
                        </div>
                    </td>
                <td>
                    <div class="sched-time-group">
                        <select class="sched-time-select" name="from_hour_${i}" id="from_hour_${i}">
                            ${hourOptions}
                        </select>
                        <span class="sched-time-colon">:</span>
                        <select class="sched-time-select" name="from_min_${i}" id="from_min_${i}">
                            ${minOptions}
                        </select>
                    </div>
                </td>
                <td class="sched-arrow-cell">&#8594;</td>
                <td>
                    <div class="sched-time-group">
                        <select class="sched-time-select" name="to_hour_${i}" id="to_hour_${i}">
                            ${hourOptions}
                        </select>
                        <span class="sched-time-colon">:</span>
                        <select class="sched-time-select" name="to_min_${i}" id="to_min_${i}">
                            ${minOptions}
                        </select>
                    </div>
                </td>
                <td>
                    <div class="sched-day-chips">
                        ${buildDayChips(i)}
                    </div>
                </td>
            </tr>
        `;
    }

    tbody.innerHTML = html;

    // 기본값 먼저 넣기
    for (let i = 0; i < rowCount; i++) {
        document.getElementById(`from_hour_${i}`).value = '00';
        document.getElementById(`from_min_${i}`).value  = '00';
        document.getElementById(`to_hour_${i}`).value   = (i === 0 ? '23' : '12');
        document.getElementById(`to_min_${i}`).value    = '00';
        document.getElementById(`act_${i}`).checked     = false;
    }

    // 받은 데이터 적용
    if (Array.isArray(scheduleRows)) {
        scheduleRows.forEach((row, i) => {
            if (!row) return;

            const from = splitTime(row.from);
            const to   = splitTime(row.to);
            const days = normalizeDays(row.days);

            const actEl      = document.getElementById(`act_${i}`);
            const fromHourEl = document.getElementById(`from_hour_${i}`);
            const fromMinEl  = document.getElementById(`from_min_${i}`);
            const toHourEl   = document.getElementById(`to_hour_${i}`);
            const toMinEl    = document.getElementById(`to_min_${i}`);

            if (actEl)      actEl.checked = !!row.enabled;
            if (fromHourEl) fromHourEl.value = from.hour;
            if (fromMinEl)  fromMinEl.value  = from.min;
            if (toHourEl)   toHourEl.value   = to.hour;
            if (toMinEl)    toMinEl.value    = to.min;

            days.forEach(day => {
                const dayEl = document.getElementById(`d${i}_${day}`);
                if (dayEl) dayEl.checked = true;
            });
        });
    }
}

function splitTime(timeStr) {
    if (!timeStr || typeof timeStr !== 'string' || timeStr.indexOf(':') === -1) {
        return { hour: '00', min: '00' };
    }

    const parts = timeStr.split(':');
    let hour = (parts[0] || '00').padStart(2, '0');
    let min  = (parts[1] || '00').padStart(2, '0');

    if (!/^\d{2}$/.test(hour)) hour = '00';
    if (!/^\d{2}$/.test(min))  min = '00';

    return { hour, min };
}
function normalizeDays(days) {
    if (!Array.isArray(days)) return [];

    return days
        .map(v => String(v || '').toLowerCase().trim())
        .filter(v => ['mon', 'tue', 'wed', 'thu', 'fri', 'sat', 'sun'].includes(v));
}

function submit_crewscheduler() {
    const userid = document.getElementById('userIdHidden').value;
    if (!userid) {
        alert('User ID is missing.');
        return;
    }

    const results = [ { userid: userid }   // 현재 PHP 구조와 맞추기 위해 첫 항목에 userid 포함
    ];

    for (let i = 0; i < 3; i++) {
        const act = document.querySelector('[name="act_' + i + '"]').checked ? 1 : 0;
        const fromHour = document.getElementById('from_hour_' + i).value;
        const fromMin  = document.getElementById('from_min_' + i).value;
        const toHour   = document.getElementById('to_hour_' + i).value;
        const toMin    = document.getElementById('to_min_' + i).value;

        const days = Array.from(
        document.querySelectorAll('[name="day_' + i + '"]:checked')
        ).map(cb => cb.value);

        results.push({
            active: act,
            from_hour: fromHour,
            from_min: fromMin,
            to_hour: toHour,
            to_min: toMin,
            days: days
        });
    }
    document.getElementById('scheduleJsonHidden').value = JSON.stringify(results);
    document.getElementById('crewscheduler').submit();
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