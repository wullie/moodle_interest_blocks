// This file is part of Moodle - http://moodle.org/

/**
 * Follow / unfollow toggles for block_interestflag.
 *
 * Each toggle calls block_interestflag_set_flag, which returns the full block state;
 * the block is then re-rendered from the same template the server used.
 *
 * @module     block_interestflag/flag
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
import Ajax from 'core/ajax';
import Templates from 'core/templates';
import Notification from 'core/notification';

const setFlag = (courseid, sectionid, flagged, currentsectionid) => Ajax.call([{
    methodname: 'block_interestflag_set_flag',
    args: {courseid, sectionid, flagged, currentsectionid},
}])[0];

/**
 * Wire up the block.
 *
 * @param {string} containerId DOM id of the block's wrapper element.
 */
export const init = (containerId) => {
    const container = document.getElementById(containerId);
    if (!container) {
        return;
    }

    const courseid = parseInt(container.dataset.courseid, 10);
    const currentsectionid = parseInt(container.dataset.currentsectionid, 10) || 0;

    container.addEventListener('click', (e) => {
        const button = e.target.closest('[data-action="interestflag-toggle"]');
        if (!button || button.disabled) {
            return;
        }
        e.preventDefault();
        button.disabled = true;

        const sectionid = parseInt(button.dataset.sectionid, 10) || 0;
        const flagged = button.dataset.flagged !== '1';

        setFlag(courseid, sectionid, flagged, currentsectionid)
            .then((state) => Templates.renderForPromise('block_interestflag/controls', state))
            .then(({html, js}) => {
                Templates.replaceNodeContents(container, html, js);
                // Keep keyboard focus on the control the user just pressed.
                const again = container.querySelector(
                    `[data-action="interestflag-toggle"][data-sectionid="${sectionid}"]`
                );
                if (again) {
                    again.focus();
                }
                return;
            })
            .catch((error) => {
                button.disabled = false;
                Notification.exception(error);
            });
    });
};
