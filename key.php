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
 * The page of the SocialReport connection: shows what is in place and, on the click of a site administrator, generates the
 * connection code (account, role, authorisation in the service and token, in one piece to copy), revokes the key or removes the
 * account. The same page takes the tracker code of the SocialReport, for the Matomo tracker in the pages.
 *
 * @package    local_socialreport
 * @copyright  2026 V3RTECH TECNOLOGIA, CONSULTORIA E INOVACAO LTDA <suporte@v3rtech.com.br>
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(__DIR__ . '/../../config.php');
require_once($CFG->libdir . '/adminlib.php');

use local_socialreport\local\provisioning;
use local_socialreport\local\tracker;

admin_externalpage_setup('local_socialreport_key');
$context = context_system::instance();
require_capability('moodle/site:config', $context);

$action = optional_param('action', '', PARAM_ALPHA);
$confirm = optional_param('confirm', 0, PARAM_BOOL);
$url = new moodle_url('/local/socialreport/key.php');
$PAGE->set_url($url);
$PAGE->set_title(get_string('keypage', 'local_socialreport'));
$PAGE->set_heading(get_string('keypage', 'local_socialreport'));

$status = provisioning::status();
$code = null;
$message = null;
$error = null;

if ($action !== '') {
    require_sesskey();
    $allowed = [
        'generate', 'revoke', 'remove', 'trackersave', 'trackerclear', 'trackeron', 'trackeroff', 'tokensave', 'tokenclear',
    ];
    if (!in_array($action, $allowed)) {
        throw new moodle_exception('invalidparameter', 'debug');
    }
    $needsconfirm = ($action === 'generate' && $status['tokens'] > 0) || $action === 'revoke' || $action === 'remove';
    if ($needsconfirm && !$confirm) {
        echo $OUTPUT->header();
        $yes = new moodle_url($url, ['action' => $action, 'confirm' => 1, 'sesskey' => sesskey()]);
        echo $OUTPUT->confirm(
            get_string('confirm' . $action, 'local_socialreport'),
            new single_button($yes, get_string('continue'), 'post'),
            new single_button($url, get_string('cancel'), 'get')
        );
        echo $OUTPUT->footer();
        exit;
    }
    if ($action === 'generate') {
        $code = provisioning::connection_code(provisioning::generate_key());
    } else if ($action === 'revoke') {
        $message = get_string('revoked', 'local_socialreport', provisioning::revoke_key());
    } else if ($action === 'remove') {
        $message = get_string(provisioning::remove_account() ? 'removed' : 'noaccount', 'local_socialreport');
    } else if ($action === 'trackersave') {
        $pasted = optional_param('trackercode', '', PARAM_RAW_TRIMMED);
        if (tracker::save_code($pasted)) {
            $message = get_string('trackersaved', 'local_socialreport');
        } else {
            $error = get_string('error:invalidtrackercode', 'local_socialreport');
        }
    } else if ($action === 'tokensave') {
        tracker::save_token(optional_param('matomotoken', '', PARAM_RAW_TRIMMED));
        $message = get_string(tracker::token() !== '' ? 'tokensaved' : 'tokencleared', 'local_socialreport');
    } else if ($action === 'tokenclear') {
        tracker::save_token('');
        $message = get_string('tokencleared', 'local_socialreport');
    } else if ($action === 'trackerclear') {
        tracker::clear();
        $message = get_string('trackercleared', 'local_socialreport');
    } else {
        tracker::set_enabled($action === 'trackeron');
    }
    $status = provisioning::status();
}

echo $OUTPUT->header();
echo html_writer::tag('p', get_string('keyintro', 'local_socialreport'));

if ($code !== null) {
    echo $OUTPUT->notification(get_string('keygenerated', 'local_socialreport'), \core\output\notification::NOTIFY_SUCCESS);
    echo html_writer::tag('label', get_string('connectioncode', 'local_socialreport'), ['for' => 'local_socialreport_code']);
    echo html_writer::tag('textarea', s($code), [
        'id' => 'local_socialreport_code', 'readonly' => 'readonly', 'rows' => 4, 'class' => 'form-control mb-2',
        'onclick' => 'this.select()',
    ]);
    echo html_writer::tag('button', get_string('copy', 'local_socialreport'), [
        'type' => 'button', 'class' => 'btn btn-secondary mb-2',
        'onclick' => "var t=document.getElementById('local_socialreport_code');t.select();" .
            "if(navigator.clipboard){navigator.clipboard.writeText(t.value);}else{document.execCommand('copy');}" .
            "this.textContent=" . json_encode(get_string('copied', 'local_socialreport')) . ";",
    ]);
    echo html_writer::tag('p', get_string('connectioncodehelp', 'local_socialreport'));
    echo html_writer::tag('p', get_string('keyonce', 'local_socialreport'));
} else if ($message !== null) {
    echo $OUTPUT->notification($message, \core\output\notification::NOTIFY_INFO);
}
if ($error !== null) {
    echo $OUTPUT->notification($error, \core\output\notification::NOTIFY_ERROR);
}

$yesno = function (bool $ok): string {
    return get_string($ok ? 'yes' : 'no');
};
$table = new html_table();
$table->attributes['class'] = 'generaltable';
$table->data = [
    [get_string('statuswebservices', 'local_socialreport'), $yesno($status['webservices'] && $status['rest'])],
    [get_string('statusauth', 'local_socialreport'), $yesno($status['auth'])],
    [get_string('statusservice', 'local_socialreport'),
        $status['service'] === null ? get_string('no') : $yesno($status['service'])],
    [get_string('statusrole', 'local_socialreport'), $yesno($status['role'])],
    [get_string('statusaccount', 'local_socialreport'), $status['account'] === null ? get_string('no') : s($status['account'])],
    [get_string('statuskey', 'local_socialreport'), $yesno($status['tokens'] > 0)],
];
echo html_writer::table($table);

$button = function (string $action, string $label, string $class = 'btn btn-secondary', array $extra = []) use ($url): string {
    $fields = html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'action', 'value' => $action]) .
        html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'sesskey', 'value' => sesskey()]);
    foreach ($extra as $name => $value) {
        $fields .= $value;
    }
    $fields .= html_writer::tag('button', $label, ['type' => 'submit', 'class' => $class]);
    $attributes = ['method' => 'post', 'action' => $url->out(false), 'class' => 'd-inline-block me-2 mr-2 mb-2'];
    return html_writer::tag('form', $fields, $attributes);
};
echo html_writer::tag('p', get_string('generatehelp', 'local_socialreport'));
echo $button('generate', get_string($status['tokens'] > 0 ? 'regenerate' : 'generate', 'local_socialreport'), 'btn btn-primary');
if ($status['tokens'] > 0) {
    echo $button('revoke', get_string('revoke', 'local_socialreport'));
}
if ($status['account'] !== null) {
    echo $button('remove', get_string('remove', 'local_socialreport'));
}

// The Matomo tracker.
$config = tracker::config();
echo html_writer::tag('h3', get_string('trackerintro', 'local_socialreport'), ['class' => 'mt-4']);
echo html_writer::tag('p', get_string('trackerhelp', 'local_socialreport'));
if (tracker::is_configured($config)) {
    $rows = [
        [get_string('trackermatomo', 'local_socialreport'), s($config['url'])],
        [get_string('trackersiteid', 'local_socialreport'), (int) $config['siteid']],
        [get_string('trackerstatus', 'local_socialreport'),
            get_string($config['enabled'] ? 'trackerstatuson' : 'trackerstatusoff', 'local_socialreport')],
        [get_string('tokenlabel', 'local_socialreport'),
            get_string(tracker::token() !== '' ? 'tokenstatuson' : 'tokenstatusoff', 'local_socialreport')],
    ];
    $trackertable = new html_table();
    $trackertable->attributes['class'] = 'generaltable';
    $trackertable->data = $rows;
    echo html_writer::table($trackertable);
    $toggle = $config['enabled'] ? ['trackeroff', 'trackerturnoff'] : ['trackeron', 'trackerturnon'];
    echo $button($toggle[0], get_string($toggle[1], 'local_socialreport'));
    echo $button('trackerclear', get_string('trackerclear', 'local_socialreport'));
}
$field = html_writer::tag('label', get_string('trackercode', 'local_socialreport'), ['for' => 'local_socialreport_trackercode']) .
    html_writer::empty_tag('input', [
        'type' => 'text', 'name' => 'trackercode', 'id' => 'local_socialreport_trackercode', 'class' => 'form-control mb-2',
        'autocomplete' => 'off', 'maxlength' => 2000,
    ]);
echo $button('trackersave', get_string('trackersave', 'local_socialreport'), 'btn btn-primary', ['field' => $field]);
echo html_writer::tag('p', get_string('trackerprivacy', 'local_socialreport'));
echo html_writer::tag('p', get_string('trackerrelay', 'local_socialreport'));
if (tracker::is_configured($config)) {
    // The optional write token: a password field that is never filled back, so the token is never in the page.
    $tokenfield = html_writer::tag('label', get_string('tokenlabel', 'local_socialreport'), [
            'for' => 'local_socialreport_matomotoken',
        ]) .
        html_writer::empty_tag('input', [
            'type' => 'password', 'name' => 'matomotoken', 'id' => 'local_socialreport_matomotoken', 'class' => 'form-control mb-2',
            'autocomplete' => 'new-password', 'maxlength' => 200, 'value' => '',
        ]);
    echo html_writer::tag('p', get_string('tokenhelp', 'local_socialreport'));
    echo $button('tokensave', get_string('tokensave', 'local_socialreport'), 'btn btn-secondary', ['field' => $tokenfield]);
    if (tracker::token() !== '') {
        echo $button('tokenclear', get_string('tokenclear', 'local_socialreport'));
    }
}
echo $OUTPUT->footer();
