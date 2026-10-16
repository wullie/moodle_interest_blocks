<?php
// This file is part of Moodle - http://moodle.org/

/**
 * New in what you follow: Dashboard block.
 *
 * Lists activities and resources that were added or updated in the courses and
 * sections the user follows (via block_interestflag), so new content comes to the
 * user rather than the user having to go looking for it.
 *
 * @package    block_interestfeed
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class block_interestfeed extends block_base {

    public function init(): void {
        $this->title = get_string('pluginname', 'block_interestfeed');
    }

    public function applicable_formats(): array {
        return [
            'all' => false,
            'my'  => true,
        ];
    }

    public function has_config(): bool {
        return true;
    }

    public function instance_allow_multiple(): bool {
        return false;
    }

    public function get_content(): ?stdClass {
        global $USER, $OUTPUT;

        if ($this->content !== null) {
            return $this->content;
        }

        $this->content         = new stdClass();
        $this->content->text   = '';
        $this->content->footer = '';

        if (!isloggedin() || isguestuser()) {
            return $this->content;
        }

        $data        = \block_interestfeed\feed_manager::export_for_template($USER->id);
        $containerid = html_writer::random_id('block-interestfeed-');

        $this->content->text = html_writer::div(
            $OUTPUT->render_from_template('block_interestfeed/feed', $data),
            'block-interestfeed',
            ['id' => $containerid, 'aria-live' => 'polite']
        );

        $this->page->requires->js_call_amd('block_interestfeed/feed', 'init', [$containerid]);

        return $this->content;
    }
}
