SynerSAT Crew WiFi Console — Release Notes
Platform: pfSense 2.5.2 · PHP 7.4 · nginx + PHP-FPM · FreeRADIUS · Captive Portal
Maintainer: SynerSAT Korea
Channels: Beta (develop) · Stable (main)


1.1.38 (2026-06-15)
Beta: 1.1.38-Beta (develop) · Stable: 1.1.2-Stable (main)

- NEW: Antenna tracking compass on Main Panel — VSAT/FBB look-angle
  (azimuth / elevation / relative azimuth) computed offline from
  GPS + satellite longitude, with live heading dial.
- NEW: 3D antenna sky dome modal (Canvas 2D, zero external libraries);
  drag-to-rotate hemisphere; satellites plotted by az/el with
  pointing vectors; world-map textured floor centred on vessel;
  below-horizon / blockage shown in red.
- NEW: Offline port minimap on Position tile — 544 ports, 292 sea
  regions, nearest-3 ports with bearing/distance, sea-region zone
  plate, local-time clock badge, zoom controls; GPS-loss
  graceful greyscale.
- NEW: Satellite coverage modal — OneWeb / Global Xpress / FleetBroadband
  overlays with vessel position; Leaflet + tiles loaded only after
  data-usage consent (first internet-dependent feature).
  APPROXIMATE / INDICATIVE ONLY — not for operational planning.
- NEW: Offline timezone auto-detection — GMT offset derived on-box from
  GPS via embedded DST-aware timezone grid; external push API removed.
- NEW: Captive portal i18n — login page in 7 languages (en/ko/tl/vi/id/zh/my)
  with auto-detect and language selector.
- NEW: CNA (OS captive mini-browser) login retains standard form with
  optional "Copy address" helper.
- CHANGED: Remote voucher REST API — create / update / delete now match
  web admin (multi-user batch, parameter alignment).
- CHANGED: Crew Account toolbar collapsed to a single row.
- CHANGED: Sidebar footer credit (SynerSAT Korea · Powered by pfSense)
  added to all custom pages.
- CHANGED: Responsive Position minimap; no longer overflows narrow tiles
  at 100% browser zoom; pixel-independent map panning.
- REMOVED: Unimplemented "Time limit" option from Modify Voucher.
- FIXED: Captive-portal infinite self-redirect loop that grew a 25 GB log,
  filled the ZFS pool, and caused 502 / OOM outage; unbounded
  request logging capped; per-minute crons protected with flock.
- FIXED: Crew routing tables (cp_gw_*) now re-synced every minute and
  instantly on gateway alarms, closing a traffic mis-attribution
  leak; gateway-usage checks moved to local vnstat.
- FIXED: Gateway flapping no longer mass-logs-out the crew zone; only
  genuinely blocked terminals are disconnected.
- FIXED: Password changes randomly failing to apply — three-layer fix:
  writer-cron lost-update guards, shared config lock with re-read,
  reload via service restart (HUP did not reload rlm_files).
  FreeRADIUS radcheck/SQL migration tooling added (flag-gated, off).
- FIXED: Mass-disconnect bug — stale widget write re-flagging all users
  (varusersmodified) causing simultaneous disconnect and permanent
  kick of non-captive accounts.
- FIXED: Login / logout latency reduced from ~19 s to immediate
  (deferred pf state-kill after HTTP response; RADIUS maxtries=1).
- FIXED: "Unknown error" on second login — stale session files no longer
  inflate quota glob; quota verified directly in PHP.
- FIXED: Unintended session KICK — zero-Stop / zero-Interim no longer
  force-disconnect.
- FIXED: Auto-login on IP change — same MAC with new DHCP IP migrates
  session instead of re-prompting; quota-exceeded devices re-auth.
- FIXED: Suspend-schedule false positive (null/empty login handling,
  schedule normalisation).
- FIXED: "User is removed!" false positive and forced disconnect
  (username carried via flash; passthrough guests handled separately).
- FIXED: Interim accounting losses — non-blocking InfluxDB/MySQL export,
  monotonic high-water-mark on session counters, threshold-based
  interim send, glob-prefix over-count fix.
- FIXED: commit_change_pw PHP fatal on null username / undefined function.
- FIXED: Phantom empty captive-portal zones on deploy (2–3 zones);
  global flags moved to $config['system'] with self-heal.
- FIXED: network_usage no longer aborts cron on vnstat read-only error.
- FIXED: Legacy per-user firewall rules purged (DHCP mis-routing risk).
- FIXED: Voucher data-reset now immediate (logout + zero usage); daily /
  half-monthly resets restored; self-healing date-key catch-up for
  missed reset crons.
- FIXED: Voucher API — create issimplefied parameter + defensive bool;
  update timeperiod decomposition + multi-user; delete reuses
  canonical function; weekiy → weekly typo fixed (weekly usage
  files now actually cleaned); stray debug echo removed.
- FIXED: Intermittent vlanstate.sh failure (timeout headroom + parser fix).
- FIXED: Random password changes not applying (per-entry config lock +
  re-read; graceful reload).
- FIXED: Disconnect-cause diagnosis — random-MAC advisory on login page;
  blank-username short-circuit stops log floods.