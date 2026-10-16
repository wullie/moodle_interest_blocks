// This file is part of Moodle - http://moodle.org/

/**
 * Dismiss controls for block_interestfeed.
 *
 * Dismissing calls block_interestfeed_dismiss, which returns the refreshed feed;
 * the block is then re-rendered from the same template the server used, so a
 * capped list pulls in the next item automatically.
 *
 * @module     block_interestfeed/feed
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
import Ajax from 'core/ajax';
import Templates from 'core/templates';
import Notification from 'core/notification';

const dismiss = (cmids, all) => Ajax.call([{
    methodname: 'block_interestfeed_dismiss',
    args: {cmids, all},
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

    container.addEventListener('click', (e) => {
        const button = e.target.closest('[data-action="interestfeed-dismiss"], [data-action="interestfeed-dismissall"]');
        if (!button || button.disabled) {
            return;
        }
        e.preventDefault();
        button.disabled = true;

        const all = button.dataset.action === 'interestfeed-dismissall';
        const cmids = all ? [] : [parseInt(button.dataset.cmid, 10)];

        dismiss(cmids, all)
            .then((feed) => Templates.renderForPromise('block_interestfeed/feed', feed))
            .then(({html, js}) => {
                Templates.replaceNodeContents(container, html, js);
                // Move focus somewhere sensible now the pressed button has gone.
                const next = container.querySelector('[data-action="interestfeed-dismiss"]')
                    || container.querySelector('[data-region="interestfeed-empty"]');
                if (next) {
                    if (!next.matches('button')) {
                        next.setAttribute('tabindex', '-1');
                    }
                    next.focus();
                }
                return;
            })
            .catch((error) => {
                button.disabled = false;
                Notification.exception(error);
            });
    });
};
