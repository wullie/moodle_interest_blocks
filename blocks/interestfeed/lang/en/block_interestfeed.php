<?php
// This file is part of Moodle - http://moodle.org/

/**
 * English strings for block_interestfeed.
 *
 * @package    block_interestfeed
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
defined('MOODLE_INTERNAL') || die();

$string['pluginname'] = 'New in what you follow';
$string['interestfeed:addinstance'] = 'Add a new New in what you follow block';
$string['interestfeed:myaddinstance'] = 'Add a new New in what you follow block to the Dashboard';

$string['dismiss'] = 'Dismiss';
$string['dismissall'] = 'Dismiss all';
$string['new'] = 'New';
$string['nothingfollowed'] = 'You are not following any courses yet. Use the Follow course content block on a course page to follow a course or section, and new content will appear here.';
$string['nothingnew'] = 'Nothing new in the last {$a} days in the courses and sections you follow.';
$string['showingfirst'] = 'Showing the latest {$a}';
$string['taskcleanup'] = 'Remove old New in what you follow data';
$string['updated'] = 'Updated';

$string['ignoredmodules'] = 'Ignored activity types';
$string['ignoredmodules_desc'] = 'Activities and resources of these types are never shown in the feed.';
$string['includeupdated'] = 'Include updated content';
$string['includeupdated_desc'] = 'Show activities and resources that were edited, not just ones that were added. An item comes back to the feed if it changes after the user has opened or dismissed it.';
$string['layout'] = 'Layout';
$string['layout_desc'] = 'Show items as tiles (like the Tiles course format\'s activity sub tiles) or as a compact list.';
$string['layoutlist'] = 'List';
$string['layouttiles'] = 'Tiles';
$string['lookbackdays'] ='Look-back period (days)';
$string['lookbackdays_desc'] = 'Only content added or updated within this many days is shown.';
$string['maxitems'] = 'Maximum items';
$string['maxitems_desc'] = 'The most items shown in the block at once.';

$string['privacy:metadata:block_interestfeed_changes'] = 'When each activity or resource was last added or updated, used to build the feed.';
$string['privacy:metadata:block_interestfeed_changes:userid'] = 'The ID of the user who last added or updated the activity or resource.';
$string['privacy:metadata:block_interestfeed_changes:timechanged'] = 'When the activity or resource was last added or updated.';
$string['privacy:metadata:block_interestfeed_seen'] = 'When a user last opened or dismissed an activity or resource, so it can be hidden from their feed.';
$string['privacy:metadata:block_interestfeed_seen:userid'] = 'The ID of the user.';
$string['privacy:metadata:block_interestfeed_seen:cmid'] = 'The ID of the activity or resource.';
$string['privacy:metadata:block_interestfeed_seen:timeseen'] = 'When the user opened or dismissed it.';
$string['privacy:path'] = 'New in what you follow';
