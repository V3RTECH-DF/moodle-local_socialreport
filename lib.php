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
 * Callbacks of the SocialReport companion plugin for Moodle 4.1 and 4.2 (the hook API of 4.3 and later uses db/hooks.php).
 *
 * @package    local_socialreport
 * @copyright  2026 V3RTECH TECNOLOGIA, CONSULTORIA E INOVACAO LTDA <suporte@v3rtech.com.br>
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

/**
 * Legacy callback: the Matomo tracker in the head of the page. From Moodle 4.3 the hook does it, so this answers nothing there
 * (the tracker would otherwise be injected twice).
 *
 * @return string
 */
function local_socialreport_before_standard_html_head() {
    if (class_exists(\core\hook\output\before_standard_head_html_generation::class)) {
        return '';
    }
    return \local_socialreport\local\tracker::html_for_current_page();
}
