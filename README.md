# SocialReport companion plugin for Moodle (local_socialreport)

Lets a **SocialReport** installation read, once a month, how many people enrolled in and completed the courses of this
Moodle site, per course and per day, as **counts only**, and (optionally) adds the Matomo tracker to the pages of the site.

## Description

The SocialReport is a monthly report tool for organisations that run an online learning platform. Its "Courses" chapter shows
enrolments and completions per course. **This plugin is useful only together with the SocialReport**: it does nothing until a
SocialReport installation calls it with the web service token that this plugin generates on the click of a site administrator.

What it adds:

1. **A web service with two read-only functions** (and no core function):
   - `local_socialreport_get_counts(current_start, current_end, previous_start, previous_end, lastyear_start, lastyear_end,
     cohort_cutoff)` returns, summed inside the Moodle database, for each course: enrolments, completions, enrolments with
     no completion record, active enrolments not completed, the 90-day cohort and its completions, the day of the first
     enrolment, and the enrolments and completions inside each of the three ranges; and, for each day of the current range,
     the enrolments and completions of the whole site. Days are days in the time zone of the site.
   - `local_socialreport_get_info` returns the version of the plugin, the time zone of the site and the state of the tracker.
2. **A system role "SocialReport"** with exactly two capabilities: `webservice/rest:use` and
   `local/socialreport:viewcounts` (this plugin's). Nothing else: no `moodle/backup:userinfo`, no report builder, no
   permission to read a course, a user or a grade.
3. **A page with the connection code** (Site administration > Plugins > Local plugins > SocialReport connection). On
   the click of a site administrator it turns web services and the REST protocol on, creates the account
   `local_socialreport` (web service authentication: nobody signs in with it; fictitious e-mail
   `local_socialreport@example.invalid`, with e-mail stopped), gives it the role at site level, authorises it in the service,
   creates the token and shows a **connection code**: the site address and the token in one piece, to copy and paste in the
   SocialReport. It is shown once; generating another makes the old key stop working. The page also revokes the key or
   deletes the account.
4. **An optional Matomo tracker, served by your own Moodle.** The SocialReport shows where the enrolments came from using the
   Matomo of the organisation, which Moodle does not know. On the same page the administrator pastes a **tracker code** made by
   the SocialReport (the address of the Matomo and the id of the site there: nothing secret) and the plugin adds the standard
   asynchronous Matomo tracker to the head of the pages. The visitor's browser never talks to the Matomo: the pages load the
   tracker from `/local/socialreport/s.php` and send each visit to `/local/socialreport/h.php`, and **the Moodle server** fetches
   the tracker from the Matomo and passes the visits on, so ad blockers (which stop by the address of the Matomo) do not hide the
   visits. The plugin page also takes an **optional Matomo write token**, kept on the server only, so that the real IP address of
   each visitor is trusted by the Matomo (without it the real IP still goes in an `X-Forwarded-For` header).

## Privacy

- **No data of a person leaves Moodle.** The answer of `local_socialreport_get_counts` is a fixed structure of whole
  numbers, course short names and dates. It has no id, name, e-mail or row per person, and the SocialReport refuses an
  answer with any extra field. The plugin stores no personal data of its own (it keeps the ids of the role and of the account
  it created, and the two settings of the tracker).
- **Outgoing calls: only to the Matomo you configured, and only if you turn the tracker on.** Without a tracker code the
  plugin makes no call to the outside; it never connects to the SocialReport: the administrator copies the connection code from
  the page and pastes it in the SocialReport, and the SocialReport is who calls Moodle. With the tracker on, this Moodle calls
  the Matomo address saved on the plugin page (and nothing else): `GET <matomo>/matomo.js` (the tracker, kept for 12 hours in the
  Moodle application cache) and `POST <matomo>/matomo.php` (each visit). A visit carries the page address, the referrer, the
  visitor's IP address, User-Agent and language, the page title and the usual tracker data; no cookie, no Moodle user, no name or
  e-mail. The destination is never taken from a request, so the two scripts are not an open proxy; only the parameters of the
  standard tracker are passed on, requests over 64 KB are refused, at most 50 visits per bulk request are passed on, the tracker
  fetch is limited to 512 KB and 8 s and each visit to 4 s, no redirect is followed, and the answer of the Matomo is never copied
  to the browser. Declared in the Privacy API of the plugin.
- **The tracker** runs without cookies, is not loaded when the visitor's browser sends Do Not Track or Global Privacy
  Control (the browser decides, so it also holds for pages served from a cache; the Moodle drops such a visit on the
  server too), and is not loaded for site administrators nor for the administration pages. The visits end at the Matomo of
  the organisation, which is where the organisation's own privacy notice applies. The optional write token is stored in the
  plugin settings of the Moodle database (like any plugin setting) and is never written in a page or script. Remove the
  tracker code on the plugin page and the pages stop carrying it at once.

## Installation

1. Site administration > Plugins > Install plugins: upload the zip, or copy the folder to `local/socialreport`, and finish
   the installation in Notifications.
2. Site administration > Plugins > Local plugins > SocialReport connection > **Generate the connection code**.
3. Copy the code and paste it in the SocialReport, in the registration of the client, block "Moodle access".
4. (Optional) In the SocialReport click "Copy the tracker code" and paste it on the same Moodle page, then "Save and turn on".
   The Moodle server must be able to reach the Matomo over HTTPS (the usual Moodle blocked hosts and allowed ports apply).

Moodle 4.1 to 5.3. The tracker uses the hook API from Moodle 4.3 and the legacy page callback in 4.1 and 4.2.

## Uninstall

Uninstalling the plugin removes the service and its tokens (Moodle does it), the integration account, the role and the
tracker settings.

## Testing without the SocialReport

The functions are plain Moodle web services. With the token of the connection code (the part after `srmoodle1.` is
base64url of `{"u": address, "t": token}`):

    curl -s -X POST "https://your.moodle/webservice/rest/server.php" -d wstoken=TOKEN \
      -d wsfunction=local_socialreport_get_counts -d moodlewsrestformat=json \
      -d current_start=2026-10-01 -d current_end=2026-10-28 -d previous_start=2026-09-01 -d previous_end=2026-09-30 \
      -d lastyear_start=2025-10-01 -d lastyear_end=2025-10-28 -d cohort_cutoff=2026-07-30

## Development

PHPUnit tests are in `tests/`; the scripts in `integracoes/moodle/testes` of the SocialReport repository run them in
disposable Moodle containers.

## License

GNU GPL v3 or later. Copyright 2026 V3RTECH TECNOLOGIA, CONSULTORIA E INOVACAO LTDA.
