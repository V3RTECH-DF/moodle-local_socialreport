# Changes

## 1.1.1 (2026-10-31)

- Documentation only: this file now says that 1.0.0 was an internal version that was never published.
- No change in behaviour, in the database or in the settings: upgrading from 1.1.0 needs no extra step.

## 1.1.0 (2026-10-29)

- The Matomo tracker is now **served by the Moodle itself**: the pages load it from `local/socialreport/s.php` and send the
  visits to `local/socialreport/h.php`, and the Moodle server fetches `matomo.js` from the Matomo and passes the visits on to
  `matomo.php`, so ad blockers no longer hide the visits. Not an open proxy: the destination is the Matomo of the saved
  tracker code only, only the parameters of the standard tracker are passed on, the site id is forced, requests, bulk size,
  tracker size and times are limited, no redirect is followed, no cookie goes either way, and visitors who send Do Not Track
  or Global Privacy Control are dropped on the server too.
- Optional Matomo write token on the plugin page (kept on the server, never shown or written in a page) so that the Matomo
  trusts the real IP of the visitor; the IP also goes in `X-Forwarded-For`.
- Outgoing call to the Matomo declared in the Privacy API and in the README. The tracker script is kept in a new application
  cache definition.

## 1.0.0 (2026-10-29)

Internal version, never published. Kept here only as history of the code; the first public release is 1.1.1.

- Web service "SocialReport (local_socialreport)" with two functions and nothing else:
  - `local_socialreport_get_counts`: enrolments and course completions per course and per day, for three date ranges
    (current, previous, same range one year before) and a 90-day cohort. Counts only: no id, name or e-mail of a person
    leaves Moodle, and the answer has nowhere to put one.
  - `local_socialreport_get_info`: version of the plugin, time zone of the site and state of the tracker.
- System role "SocialReport" with only `webservice/rest:use` and `local/socialreport:viewcounts` (no
  `moodle/backup:userinfo`, no report builder, no reading of courses, users or grades).
- Admin page (Site administration > Plugins > Local plugins > SocialReport connection): on the click of a site administrator,
  turns web services and REST on, creates the integration account, authorises it, creates the token and shows a **connection
  code** (site address and token, in one piece) to copy and paste in the SocialReport. Revokes the key, removes the account.
  The plugin makes no call to the outside.
- Optional Matomo tracker for the pages of Moodle, from a **tracker code** (address of the Matomo and site id, not a secret)
  pasted on the same page. No cookies; not loaded for visitors who send Do Not Track or Global Privacy Control; not
  loaded for site administrators nor for the administration pages. The plugin never uses a Matomo token.
- Moodle 4.1 to 5.3 (the tracker uses the hook API from 4.3 and the legacy callback before).
- English and Brazilian Portuguese strings.
