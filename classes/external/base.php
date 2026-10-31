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

namespace local_socialreport\external;

defined('MOODLE_INTERNAL') || die();

// Moodle 4.2 moved the external API to the core_external namespace; Moodle 4.1 has it in lib/externallib.php
// under global names. The functions of this plugin use the names below, aliased to whichever exists.
// lib/externallib.php is only loaded on 4.1: from 4.2 on it is deprecated.
if (!class_exists(\core_external\external_api::class)) {
    global $CFG;
    require_once($CFG->libdir . '/externallib.php');
}
foreach (
    [
        'external_api',
        'external_value',
        'external_single_structure',
        'external_multiple_structure',
        'external_function_parameters',
    ] as $localsocialreportname
) {
    if (!class_exists(__NAMESPACE__ . '\\' . $localsocialreportname, false)) {
        $localsocialreportsource = class_exists('core_external\\' . $localsocialreportname)
            ? 'core_external\\' . $localsocialreportname : $localsocialreportname;
        class_alias($localsocialreportsource, __NAMESPACE__ . '\\' . $localsocialreportname);
    }
}
unset($localsocialreportname, $localsocialreportsource);

/**
 * Base of the web service functions of the plugin, on the external API of the running Moodle.
 *
 * @package    local_socialreport
 * @copyright  2026 V3RTECH TECNOLOGIA, CONSULTORIA E INOVACAO LTDA <suporte@v3rtech.com.br>
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
abstract class base extends external_api {
    /**
     * The system context, validated, and the capability of the plugin required in it.
     *
     * @return \context_system
     */
    protected static function require_viewcounts(): \context_system {
        $context = \context_system::instance();
        self::validate_context($context);
        require_capability('local/socialreport:viewcounts', $context);
        return $context;
    }
}
