<?php
// This file is part of Moodle - https://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <https://www.gnu.org/licenses/>.

/**
 * English strings of the SocialReport companion plugin.
 *
 * @package    local_socialreport
 * @copyright  2026 V3RTECH TECNOLOGIA, CONSULTORIA E INOVACAO LTDA <suporte@v3rtech.com.br>
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

$string['accountdescription'] = 'Account of the SocialReport connection, created by the SocialReport plugin. Nobody signs in with it: it only answers the web service calls of the SocialReport.';
$string['accountfirstname'] = 'SocialReport';
$string['accountlastname'] = 'Integration';
$string['cachedef_script'] = 'The copy of the Matomo tracker served by this Moodle';
$string['confirmgenerate'] = 'Generate a new connection code? The current key stops working at once: paste the new code in the SocialReport, in the registration of the client.';
$string['confirmremove'] = 'Delete the SocialReport account? Its key stops working and the SocialReport loses the connection until a new code is generated.';
$string['confirmrevoke'] = 'Revoke the SocialReport key? The SocialReport loses the connection until a new code is generated. The account and its role stay.';
$string['connectioncode'] = 'Connection code';
$string['connectioncodehelp'] = 'Copy this code and paste it in the SocialReport, in the registration of the client, block "Moodle access". It holds the site address and the key in one piece.';
$string['copied'] = 'Copied';
$string['copy'] = 'Copy';
$string['error:invalidtrackercode'] = 'That is not a valid tracker code. Copy it again from the SocialReport, in the registration of the client ("Copy the tracker code").';
$string['error:noservice'] = 'The "SocialReport (local_socialreport)" external service is missing and could not be created again from the plugin. Open Notifications, in Site administration, and try again.';
$string['error:rolenameinuse'] = 'There is already a role with the short name "{$a}" that the plugin did not create. Rename it (Define roles) and try again.';
$string['error:usernameinuse'] = 'There is already an account with the user name "{$a}" that the plugin did not create. Rename it or delete it and try again.';
$string['generate'] = 'Generate the connection code';
$string['generatehelp'] = 'Generating the code turns on web services and the REST protocol, creates the account "local_socialreport" (it cannot sign in; its e-mail address is fictitious, local_socialreport@example.invalid, and never receives messages), gives it the role "SocialReport" at site level, authorises it in the "SocialReport (local_socialreport)" external service and creates its key.';
$string['keygenerated'] = 'The connection code is ready. Copy it now and paste it in the SocialReport: it is shown only this once.';
$string['keyintro'] = 'The SocialReport reads from this site, once a month, how many people enrolled and how many completed each course, per course and per day. The plugin sums those numbers inside Moodle: nothing about a person leaves it (no name, no e-mail, no id, no row per person). It makes no call to the outside: you copy the connection code from here and paste it in the SocialReport. The account and its key are created only here, on your click.';
$string['keyonce'] = 'The code is shown only now. If it is lost, generate a new one: the old key stops working.';
$string['keypage'] = 'SocialReport connection';
$string['noaccount'] = 'There was no SocialReport account to delete.';
$string['pluginname'] = 'SocialReport';
$string['privacy:metadata'] = 'The SocialReport plugin stores no personal data: it returns counts only (enrolments and completions per course and per day), never a name, an e-mail, an id or a row per person. The account of the connection, when a site administrator generates it, is an ordinary Moodle account of the site, with a fictitious e-mail address; the plugin keeps only its id. The Matomo tracker, when the administrator turns it on, is served by this Moodle and runs without cookies; it does not measure visitors who send Do Not Track or Global Privacy Control, nor site administrators. Each visit it measures is passed on by this Moodle to the Matomo of the organisation (the address saved on the plugin page), and nothing about it is kept by the plugin.';
$string['privacy:metadata:matomo'] = 'Matomo of the organisation (the address saved on the plugin page)';
$string['privacy:metadata:matomo:ipaddress'] = 'The IP address of the visitor, so that the Matomo can place the visit (the Matomo anonymises it as the organisation configured).';
$string['privacy:metadata:matomo:language'] = 'The language of the browser of the visitor.';
$string['privacy:metadata:matomo:pageurl'] = 'The address of the page visited.';
$string['privacy:metadata:matomo:referrer'] = 'The address of the page the visitor came from.';
$string['privacy:metadata:matomo:useragent'] = 'The browser and system of the visitor (User-Agent).';
$string['regenerate'] = 'Generate a new connection code';
$string['remove'] = 'Delete the SocialReport account';
$string['removed'] = 'The SocialReport account was deleted.';
$string['revoke'] = 'Revoke the key';
$string['revoked'] = 'Keys revoked: {$a}.';
$string['roledescription'] = 'Role of the SocialReport connection, created by the SocialReport plugin: only the capability to read counts, and the REST protocol. Given at site level to the SocialReport account.';
$string['rolename'] = 'SocialReport';
$string['siteaddress'] = 'Site address';
$string['socialreport:viewcounts'] = 'Read the aggregated counts of enrolments and completions per course and per day (never data of a person)';
$string['statusaccount'] = 'SocialReport account';
$string['statusauth'] = 'Web service authentication enabled';
$string['statuskey'] = 'Key generated';
$string['statusrole'] = 'Role "SocialReport"';
$string['statusservice'] = 'Service "SocialReport (local_socialreport)" enabled';
$string['statuswebservices'] = 'Web services and REST on';
$string['tokenclear'] = 'Remove the token';
$string['tokencleared'] = 'The token was removed.';
$string['tokenhelp'] = 'Optional. Without it the Matomo sees all visits coming from the IP address of this Moodle server (it still receives the real one in a header, which a Matomo may be configured to trust). With it, the real IP of each visitor reaches the Matomo. Use a token with the least rights that allow recording visits. It is kept on the server, never shown again and never written in a page or script.';
$string['tokenlabel'] = 'Matomo write token (optional)';
$string['tokensave'] = 'Save the token';
$string['tokensaved'] = 'The token was saved.';
$string['tokenstatusoff'] = 'No token';
$string['tokenstatuson'] = 'Token saved';
$string['trackerclear'] = 'Remove the tracker';
$string['trackercleared'] = 'The tracker was removed: the pages no longer carry it.';
$string['trackercode'] = 'Tracker code';
$string['trackerhelp'] = 'The SocialReport shows the origin of the enrolments (where the visitors came from) using the Matomo of your organisation, which needs a small tracker in the pages of this site. In the SocialReport, in the registration of the client, click "Copy the tracker code" and paste it here. The code holds only the address of the Matomo and the id of the site there, nothing secret.';
$string['trackerintro'] = 'Matomo tracker';
$string['trackermatomo'] = 'Matomo address';
$string['trackerprivacy'] = 'The tracker runs without cookies. It does not measure visitors who send Do Not Track or Global Privacy Control, nor site administrators, nor the administration pages.';
$string['trackerrelay'] = 'The browser of the visitor does not talk to the Matomo: this Moodle serves the tracker and passes each visit on to the Matomo (a call to the outside, to the address above only). Blockers of advertisements therefore do not hide the visits.';
$string['trackersave'] = 'Save and turn on';
$string['trackersaved'] = 'The tracker is on: the pages of the site now carry it.';
$string['trackersiteid'] = 'Site id in the Matomo';
$string['trackerstatus'] = 'Tracker';
$string['trackerstatusoff'] = 'Off';
$string['trackerstatuson'] = 'On';
$string['trackerturnoff'] = 'Turn off';
$string['trackerturnon'] = 'Turn on';
