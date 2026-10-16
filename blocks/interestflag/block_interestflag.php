<?php
// This file is part of Moodle - http://moodle.org/

/**
 * Follow course content block.
 *
 * Sits on course pages and lets the current user follow the whole course, or
 * individual sections of it. New and updated content in whatever they follow is
 * surfaced on their Dashboard by block_interestfeed.
 *
 * @package    block_interestflag
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class block_interestflag extends block_base {

    public function init(): void {
        $this->title = get_string('pluginname', 'block_interestflag');
    }

    public function applicable_formats(): array {
        // 'course-view' also matches the single-section page (course-view-section-*).
        return [
            'all'         => false,
            'course-view' => true,
        ];
    }

    public function has_config(): bool {
        return false;
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

        $course = $this->page->course;
        if (empty($course->id) || (int) $course->id === SITEID) {
            return $this->content;
        }
        if (!has_capability('block/interestflag:flag', context_course::instance($course->id))) {
            return $this->content;
        }

        $currentsectionid = $this->get_current_sectionid($course);
        $data = \block_interestflag\flag_manager::get_course_state($course, $USER->id, $currentsectionid);

        // Unique per page so the JS can find its own mount point.
        $containerid = html_writer::random_id('block-interestflag-');

        $this->content->text = html_writer::div(
            $OUTPUT->render_from_template('block_interestflag/controls', $data),
            'block-interestflag',
            [
                'id'                    => $containerid,
                'data-courseid'         => $course->id,
                'data-currentsectionid' => $currentsectionid,
                'aria-live'             => 'polite',
            ]
        );

        $this->page->requires->js_call_amd('block_interestflag/flag', 'init', [$containerid]);

        return $this->content;
    }

    /**
     * Work out which section is being viewed, if any:
     *   /course/section.php?id=N   (Moodle 4.4+ single-section page)
     *   /course/view.php?section=N (older single-section URLs)
     */
    private function get_current_sectionid(stdClass $course): int {
        $url = $this->page->url;

        if (str_starts_with($this->page->pagetype, 'course-view-section-')) {
            return (int) $url->get_param('id');
        }

        $sectionnum = $url->get_param('section');
        if ($sectionnum !== null) {
            $section = get_fast_modinfo($course)->get_section_info((int) $sectionnum);
            return $section ? (int) $section->id : 0;
        }

        return 0;
    }
}
