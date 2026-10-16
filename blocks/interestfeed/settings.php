<?php
// This file is part of Moodle - http://moodle.org/

/**
 * Site administration settings for block_interestfeed.
 *
 * @package    block_interestfeed
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
defined('MOODLE_INTERNAL') || die();

if ($ADMIN->fulltree) {

    $settings->add(new admin_setting_configselect(
        'block_interestfeed/layout',
        get_string('layout', 'block_interestfeed'),
        get_string('layout_desc', 'block_interestfeed'),
        'tiles',
        [
            'tiles' => get_string('layouttiles', 'block_interestfeed'),
            'list'  => get_string('layoutlist', 'block_interestfeed'),
        ]
    ));

    $settings->add(new admin_setting_configtext(
        'block_interestfeed/lookbackdays',
        get_string('lookbackdays', 'block_interestfeed'),
        get_string('lookbackdays_desc', 'block_interestfeed'),
        14,
        PARAM_INT
    ));

    $settings->add(new admin_setting_configtext(
        'block_interestfeed/maxitems',
        get_string('maxitems', 'block_interestfeed'),
        get_string('maxitems_desc', 'block_interestfeed'),
        10,
        PARAM_INT
    ));

    $settings->add(new admin_setting_configcheckbox(
        'block_interestfeed/includeupdated',
        get_string('includeupdated', 'block_interestfeed'),
        get_string('includeupdated_desc', 'block_interestfeed'),
        1
    ));

    // Choices are loaded lazily so the module list is only read when the page is shown.
    $settings->add(new admin_setting_configmulticheckbox(
        'block_interestfeed/ignoredmodules',
        get_string('ignoredmodules', 'block_interestfeed'),
        get_string('ignoredmodules_desc', 'block_interestfeed'),
        ['subsection' => 1, 'qbank' => 1],
        function(): array {
            global $DB;
            $choices = [];
            foreach ($DB->get_fieldset_select('modules', 'name', '1 = 1') as $name) {
                $choices[$name] = get_string_manager()->string_exists('modulename', 'mod_' . $name)
                    ? get_string('modulename', 'mod_' . $name)
                    : $name;
            }
            core_collator::asort($choices);
            return $choices;
        }
    ));
}
