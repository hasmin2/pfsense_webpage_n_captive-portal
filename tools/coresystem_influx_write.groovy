// =============================================================================
// coresystem_influx_write.groovy  —  StreamSets Groovy Evaluator (코어 박스에서 실행)
// -----------------------------------------------------------------------------
// SDC 가 코어 박스(CentOS)에서 로컬 실행된다는 전제.
// CPU 코어 평균온도(sensors)와 시스템 uptime(/proc/uptime 초)을 로컬 InfluxDB 의
// acustatus.coresystem measurement 로 기록한다.
// pfSense runtime API(APISystemGetRuntime.inc)가 이 measurement 를 읽어
// core_temp / core_uptime 을 반환한다. (SSH/sshpass 회피 — FreeBSD 미지원 대응)
//
// 배치당 1회 수집·기록. 입력 레코드는 그대로 통과(trigger 용 dummy 레코드 무관).
// 스케줄(트리거/크론)은 파이프라인 쪽에서 구성.
// 의존: 코어 박스에 lm_sensors(sensors) + 로컬 InfluxDB 8086.
// =============================================================================

import java.net.HttpURLConnection
import java.net.URL

// 코어 박스에서 로컬 실행이므로 loopback. (192.168.209.210 도 동일 호스트라 가능)
def INFLUX_URL = 'http://127.0.0.1:8086/write?db=acustatus&precision=s'

// ── 셸 명령 실행(타임아웃 가드), 실패 시 null ──────────────────────────────
def runCmd = { List cmd, long timeoutMs ->
    try {
        def proc = cmd.execute()
        def out = new StringBuilder()
        def err = new StringBuilder()
        proc.consumeProcessOutput(out, err)
        proc.waitForOrKill(timeoutMs)
        def s = out.toString()
        return s ? s : null
    } catch (e) {
        sdc.log.warn('coresystem: cmd fail {} : {}', cmd, e.message)
        return null
    }
}

// ── sensors → Core N 평균온도(℃). 콜론 뒤 첫 숫자만(정규식 없이 문자 스캔, high/crit 오염 방지) ──
def parseCoreTemp = { txt ->
    if (!txt) return null
    def temps = []
    txt.readLines().each { line ->
        def t = line.trim()
        if (t.startsWith('Core')) {
            def ci = t.indexOf(':')
            if (ci > 0) {
                def rest = t.substring(ci + 1).trim()
                def sb = new StringBuilder()
                boolean started = false
                for (int i = 0; i < rest.length(); i++) {
                    char ch = rest.charAt(i)
                    if (ch == ('+' as char)) { started = true; continue }
                    if (ch == ('-' as char)) { sb.append(ch); started = true; continue }
                    if ((ch >= ('0' as char) && ch <= ('9' as char)) || ch == ('.' as char)) {
                        sb.append(ch); started = true
                    } else if (started) { break }
                }
                if (sb.length() > 0) { try { temps << (sb.toString() as Double) } catch (ig) {} }
            }
        }
    }
    if (temps.isEmpty()) return null
    return (temps.sum() / temps.size())
}

// ── /proc/uptime 첫 필드 → 정수 초 ─────────────────────────────────────────
def readUptimeSec = {
    try {
        def line = new File('/proc/uptime').text
        if (!line || line.trim().isEmpty()) return null
        return (line.trim().tokenize()[0] as Double).longValue()
    } catch (e) {
        sdc.log.warn('coresystem: /proc/uptime read fail: {}', e.message)
        return null
    }
}

// ── InfluxDB line protocol POST (2초 타임아웃, 실패 시 false) ───────────────
def influxWrite = { String line ->
    HttpURLConnection conn = null
    try {
        conn = (HttpURLConnection) new URL(INFLUX_URL).openConnection()
        conn.setRequestMethod('POST')
        conn.setConnectTimeout(2000)
        conn.setReadTimeout(2000)
        conn.setDoOutput(true)
        conn.setRequestProperty('Content-Type', 'text/plain')
        def bytes = line.getBytes('UTF-8')
        conn.setRequestProperty('Content-Length', String.valueOf(bytes.length))
        conn.getOutputStream().write(bytes)
        conn.getOutputStream().flush()
        int code = conn.getResponseCode()
        if (code != 204 && code != 200) {
            sdc.log.warn('coresystem: influx write status={}', code)
            return false
        }
        return true
    } catch (e) {
        sdc.log.warn('coresystem: influx write fail: {}', e.message)
        return false
    } finally {
        try { conn?.disconnect() } catch (ignore) {}
    }
}

// ── 배치당 1회: 수집 → line protocol → write ────────────────────────────────
// timestamp = 현재 시각을 5분(300초) 경계로 내림(precision=s). 같은 5분 버킷은
// 동일 timestamp → InfluxDB 가 같은 포인트로 덮어씀(버킷당 1점).
// core_uptime 은 정수 필드 → 'i' 접미사. 소수점 표기는 Locale.US 고정(콤마 방지).
def collectAndWrite = {
    def temp = parseCoreTemp(runCmd(['bash', '-c', 'sensors'], 5000L))
    def up   = readUptimeSec()

    def fields = []
    if (temp != null) fields << ('core_temp=' + String.format(java.util.Locale.US, '%.2f', temp))
    if (up   != null) fields << ('core_uptime=' + up + 'i')

    if (fields.isEmpty()) {
        sdc.log.warn('coresystem: temp/uptime 모두 실패 - influx 미기록')
        return
    }

    long nowSec = System.currentTimeMillis().intdiv(1000L)   // 현재 epoch 초
    long ts     = nowSec.intdiv(300L) * 300L                 // 5분 경계로 내림
    def line = 'coresystem ' + fields.join(',') + ' ' + ts
    if (influxWrite(line)) {
        sdc.log.info('coresystem write ok: {}', line)
    }
}

collectAndWrite()

// 입력 레코드는 그대로 통과(있으면).
for (record in sdc.records) {
    sdc.output.write(record)
}
