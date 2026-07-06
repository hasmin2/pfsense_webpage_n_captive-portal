BlueWave Link — Release Notes

Developed by SynerSAT Korea

2026-07-06 Update

- NEW: Account history now has Login and Usage tabs. The per-account "History"
  view on the Crew Accounts page has three tabs — Change, Login and Usage — all
  using the same date-range picker (1 / 7 / 30 days, all time, or a custom
  range). Login lists every login and logout for that account, with the IP,
  MAC address and the reason for each logout (idle timeout, session timeout,
  quota exceeded, admin action, etc). Usage lists the data used in each
  completed session — duration, data in, data out and total — once the
  session has ended. Both tabs support the same paging and CSV export as the
  Change tab.

2026-07-03 Update
Beta 1.1.53-Beta · Stable: 1.1.4-Stable

- FIXED: FBB antenna signal now displays whenever a valid signal is present,
  even if the FBB reports a satellite name the console doesn't recognize.
  Previously the signal was hidden as "No Signal" in that case even though a
  good signal was coming in.
- CHANGED: The antenna (VSAT/ACU) compass now shows "Comm. Error" when the ACU
  reports a communication error, instead of grouping it with "Searching".
- NEW: Timezone (GMT) change history — the sidebar "GMT" now has a small
  "history" button that opens a log of every timezone-offset change over the
  last 1, 7 or 30 days, or a custom date range. Each row shows the time (UTC),
  the change (e.g. GMT 9 → GMT 9.5), how it changed (a manual change from an
  operator's IP address, an automatic GPS-based change, or a remote API change),
  and the vessel's GPS position at that moment. Long histories are paged 10
  entries at a time with Prev / Next controls. The list can be exported to CSV.
- NEW: Account change history — every crew Wi-Fi account change is now recorded
  to the database: account creation, deletion, modification, password reset,
  data-usage reset, quota top-up, description edit and duty-schedule change,
  whether made from the account page, the dashboard widget or the remote API.
  Each record notes who made the change and when. Prepaid (crewpay-) account
  changes are marked "(CREWPAY)". Actual passwords are never written to the log.
- NEW: Per-account history view — each row in the Crew Accounts page now has a
  "History" button that opens the change log for that specific account (password
  and data-usage resets, modifications, top-ups, description and schedule edits),
  filtered to that account. Choose the last 1 / 7 / 30 days, all time, or a
  custom date range, and export the list to CSV. Long histories are paged 10
  entries at a time with Prev / Next controls.
- CHANGED: Crew Wi-Fi devices can no longer reach the firewall itself except for
  what they actually need — DNS, DHCP and the login portal. Access to the router's
  admin interfaces (web console, SSH, etc.) from the crew network is now blocked.
  This closes a gap where the per-user routing rules also let crew devices reach
  the firewall's management services.
- FIXED: The theme (System / GPS / Light / Dark) toggle now remembers your last
  choice. It previously reset to the default on some consoles every time the page
  was reopened; the setting is now saved in a cookie so it persists.
- CHANGED: The "SET RANDOM PW" button is now available to customer logins on the
  Crew Accounts page (previously only admins could see it).
- FIXED: OpenVPN auto-restart no longer misses a dead tunnel. The watchdog was
  checking reachability to the VPN server's public address, which stayed reachable
  over the satellite link even when the tunnel itself was down — so a broken tunnel
  looked healthy and was never restarted (observed staying down for 6+ hours until
  a manual restart). It now checks the tunnel's internal gateway, and runs every
  minute again, so a dead tunnel is detected and restarted within a few minutes.

2026-07-02 Update
Beta 1.1.49-Beta · Stable: 1.1.3-Stable

- NEW: Dark mode — a sidebar toggle cycles System / GPS / Light / Dark. "System"
  follows your device's light/dark setting; "GPS" automatically switches to dark
  between dusk and dawn at the vessel's current position (computed offline, no
  internet). Applies across all console pages.
- NEW: Daily internet usage graph — the "Internet usage" tile has a "Daily usage"
  button showing a per-gateway daily bar chart (This month by default, plus 7 / 14 /
  30-day ranges) with a usage scale on the left.
- CHANGED: Timezone (GMT) selection now opens an in-page dialog matching the theme
  and supports 30-minute (half-hour) offsets (e.g. GMT +9.5). The automatic GPS-based
  timezone no longer overrides a manually set half-hour offset.
- FIXED: Saving a timezone no longer leaves a stray numbered folder in the system
  web directory (an internal config dump was being written there).
- NEW: System runtime API — GET /api/v1/system/runtime returns the firewall
  uptime (seconds since last boot).
- FIXED: Renaming a gateway now automatically re-syncs the internal captive-portal
  routing rules to the new name (old-name rules removed, new-name rules created)
  when the gateway is saved, instead of leaving stale rules behind.

1.1.4 (2026-06-30)
Beta (develop) · Stable: pending

- FIXED: OpenVPN auto-restart reliability — the per-minute watchdog now
  evaluates each VPN client independently (previously only the last
  client was checked), recovers from a stuck previous run instead of
  stalling indefinitely, and tolerates satellite packet loss (restarts
  only after sustained failure, not a single dropped ping). Uplink-switch
  (route-change) restarts remain immediate.
- CHANGED: When an account is pinned to an antenna/gateway that no longer
  exists (disabled, renamed, or removed), login is now blocked with
  "The antenna is offline, please try later." instead of silently
  connecting with no traffic. Auto and valid-gateway users are unaffected.
- CHANGED: On IP change the portal now shows the login page (logging back
  in restores your own session) rather than auto-migrating by MAC. This
  stops session hijack / ping-pong behind shared-NAT or MAC-clone routers;
  routers are best used in bridge / access-point mode.

1.1.3 (2026-06-21)
Beta 1.1.40-Beta Stable: 1.1.3-Stable

- CHANGED: Satellite coverage map now gated by antenna type — the world
  map still opens on Position-minimap click, but coverage overlays are
  shown only when a NexusWave gateway is present; other vessels see the
  world map with a notice that only NexusWave currently supports the
  coverage map.
- FIXED: 3D antenna sky-dome floor (world map) now rotates together with
  the dome (wireframe / satellites / vessel / compass labels) when
  dragging or auto-orbiting; previously the textured floor stayed fixed.


1.1.2 (2026-06-15)
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