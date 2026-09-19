// This file is part of Moodle - http://moodle.org/
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
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.

/**
 * Drag a choice chip onto a gap; the native select is the keyboard fallback.
 *
 * @module     qtype_ddto_chill/dragdrop
 * @copyright  2026 Maxime Cruzel
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

const DROP_CLASS = 'qtype-ddto-chill-drop';
const DRAG_CLASS = 'qtype-ddto-chill-drag';
const SELECT_CLASS = 'qtype-ddto-chill-select';
const OVER_CLASS = 'qtype-ddto-chill-drop-over';
const USED_CLASS = 'qtype-ddto-chill-used';

/**
 * Initialise drag-and-drop enhancement on one question attempt.
 *
 * @param {Object} config
 * @param {string} config.containerId id of the question wrapper
 */
export const init = (config) => {
    if (!config || !config.containerId) {
        return;
    }
    const container = document.getElementById(config.containerId);
    if (!container || container.dataset.qtypeDdtoChillDragdrop) {
        return;
    }
    container.dataset.qtypeDdtoChillDragdrop = '1';

    /**
     * Mark chips whose choice number is currently placed in a gap.
     */
    const refreshUsed = () => {
        const used = {};
        container.querySelectorAll('select.' + SELECT_CLASS).forEach((select) => {
            const value = parseInt(select.value, 10);
            if (value) {
                used[value] = true;
            }
        });
        container.querySelectorAll('.' + DRAG_CLASS).forEach((chip) => {
            const choice = parseInt(chip.getAttribute('data-choice'), 10);
            if (used[choice]) {
                chip.classList.add(USED_CLASS);
            } else {
                chip.classList.remove(USED_CLASS);
            }
        });
    };

    /**
     * Place a choice into the select of a drop zone.
     *
     * @param {Element} drop
     * @param {number} choiceno
     */
    const placeChoice = (drop, choiceno) => {
        const select = drop.querySelector('select.' + SELECT_CLASS);
        if (!select || select.disabled) {
            return;
        }
        select.value = String(choiceno);
        select.dispatchEvent(new Event('change', {bubbles: true}));
        refreshUsed();
    };

    let dragChoice = 0;

    container.addEventListener('dragstart', (e) => {
        const chip = e.target.closest('.' + DRAG_CLASS);
        if (!chip || !container.contains(chip)) {
            return;
        }
        dragChoice = parseInt(chip.getAttribute('data-choice'), 10) || 0;
        chip.setAttribute('aria-grabbed', 'true');
        e.dataTransfer.effectAllowed = 'copy';
        e.dataTransfer.setData('text/plain', String(dragChoice));
    });

    container.addEventListener('dragend', (e) => {
        const chip = e.target.closest('.' + DRAG_CLASS);
        if (chip) {
            chip.setAttribute('aria-grabbed', 'false');
        }
        container.querySelectorAll('.' + OVER_CLASS).forEach((el) => el.classList.remove(OVER_CLASS));
        dragChoice = 0;
    });

    container.addEventListener('dragover', (e) => {
        const drop = e.target.closest('.' + DROP_CLASS);
        if (!drop || !container.contains(drop)) {
            return;
        }
        e.preventDefault();
        e.dataTransfer.dropEffect = 'copy';
        drop.classList.add(OVER_CLASS);
    });

    container.addEventListener('dragleave', (e) => {
        const drop = e.target.closest('.' + DROP_CLASS);
        if (drop) {
            drop.classList.remove(OVER_CLASS);
        }
    });

    container.addEventListener('drop', (e) => {
        const drop = e.target.closest('.' + DROP_CLASS);
        if (!drop || !container.contains(drop)) {
            return;
        }
        e.preventDefault();
        drop.classList.remove(OVER_CLASS);
        const fromData = parseInt(e.dataTransfer.getData('text/plain'), 10);
        const choiceno = fromData || dragChoice;
        if (choiceno) {
            placeChoice(drop, choiceno);
        }
    });

    container.addEventListener('change', (e) => {
        if (e.target && e.target.classList && e.target.classList.contains(SELECT_CLASS)) {
            refreshUsed();
        }
    });

    // Keyboard: Enter on a focused chip "picks it up"; Enter on a drop zone places it.
    let picked = 0;
    container.addEventListener('keydown', (e) => {
        if (e.key !== 'Enter' && e.key !== ' ') {
            return;
        }
        const chip = e.target.closest('.' + DRAG_CLASS);
        if (chip && container.contains(chip)) {
            e.preventDefault();
            picked = parseInt(chip.getAttribute('data-choice'), 10) || 0;
            container.querySelectorAll('.' + DRAG_CLASS).forEach((el) => el.setAttribute('aria-grabbed', 'false'));
            chip.setAttribute('aria-grabbed', 'true');
            return;
        }
        const drop = e.target.closest('.' + DROP_CLASS);
        if (drop && picked && container.contains(drop)) {
            e.preventDefault();
            placeChoice(drop, picked);
            picked = 0;
            container.querySelectorAll('.' + DRAG_CLASS).forEach((el) => el.setAttribute('aria-grabbed', 'false'));
        }
    });

    refreshUsed();
};
