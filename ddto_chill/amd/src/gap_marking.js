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
 * Detect words in the source sentence and turn ticked ones into gaps.
 *
 * @module     qtype_ddto_chill/gap_marking
 * @copyright  2026 Maxime Cruzel
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

const WORD_RE = /[\p{L}\p{N}]+/gu;
const JS_ENABLED_CLASS = 'qtype-ddto-chill-js';

/**
 * Split a sentence into word tokens, matching the PHP tokenizer.
 *
 * @param {string} source
 * @returns {string[]}
 */
const wordList = (source) => {
    if (!source) {
        return [];
    }
    const words = source.match(WORD_RE);
    return words ? words.slice() : [];
};

/**
 * Initialise live word detection on the editing form.
 *
 * @param {Object} config
 * @param {string} config.sourceName name of the source textarea
 * @param {string} config.selectionName name of the hidden gap-index field
 * @param {string} config.listId id of the checkbox list container
 * @param {string} config.detectButtonName name of the noscript detect submit
 * @param {string} config.makeGapLabel suffix shown next to each word
 * @param {string} config.questionTextItemId fitem id of the core questiontext editor
 */
export const init = (config) => {
    if (!config || !config.sourceName || !config.listId) {
        return;
    }

    const source = document.querySelector('[name="' + config.sourceName + '"]');
    const list = document.getElementById(config.listId);
    const selection = document.querySelector('[name="' + config.selectionName + '"]');
    if (!source || !list) {
        return;
    }

    const form = source.closest('form');
    if (!form || form.dataset.qtypeDdtoChillGapMarking) {
        return;
    }
    form.dataset.qtypeDdtoChillGapMarking = '1';
    form.classList.add(JS_ENABLED_CLASS);

    const questionTextItem = document.getElementById(config.questionTextItemId);
    if (questionTextItem) {
        questionTextItem.classList.add('d-none');
    }

    const detectButton = form.querySelector('[name="' + config.detectButtonName + '"]');
    if (detectButton) {
        const item = detectButton.closest('.fitem') || detectButton;
        item.classList.add('d-none');
    }

    /**
     * Currently ticked word indices.
     *
     * @returns {number[]}
     */
    const readSelection = () => {
        if (selection && selection.value) {
            return selection.value.split(',').map((bit) => parseInt(bit, 10)).filter((n) => !isNaN(n));
        }
        return Array.from(list.querySelectorAll('input[type="checkbox"]:checked'))
            .map((el) => parseInt(el.getAttribute('data-word-index'), 10))
            .filter((n) => !isNaN(n));
    };

    /**
     * Write the ticked indices back to the hidden field.
     *
     * @param {number[]} indices
     */
    const writeSelection = (indices) => {
        if (selection) {
            selection.value = indices.join(',');
        }
    };

    /**
     * Rebuild the checkbox list from the current sentence, keeping ticks
     * whose index still points at the same word.
     */
    const rebuild = () => {
        const words = wordList(source.value);
        const previousWords = Array.from(list.querySelectorAll('[data-word-index]'))
            .map((el) => {
                const index = parseInt(el.getAttribute('data-word-index'), 10);
                const box = el.matches('input') ? el : el.querySelector('input');
                const label = el.matches('label') ? el : el.closest('label');
                let text = '';
                if (label) {
                    text = (label.textContent || '').replace(/\s+—.*$/, '').trim();
                }
                return {
                    index: index,
                    text: text,
                    checked: Boolean(box && box.checked),
                };
            });
        const previousByIndex = {};
        previousWords.forEach((item) => {
            previousByIndex[item.index] = item;
        });
        const kept = readSelection().filter((index) => {
            if (!words[index]) {
                return false;
            }
            const prev = previousByIndex[index];
            return !prev || prev.text === '' || prev.text === words[index];
        });
        const keptSet = {};
        kept.forEach((index) => {
            keptSet[index] = true;
        });

        list.textContent = '';
        words.forEach((word, index) => {
            const label = document.createElement('label');
            label.className = 'qtype-ddto-chill-gapword';
            const box = document.createElement('input');
            box.type = 'checkbox';
            box.className = 'qtype-ddto-chill-isgap';
            box.setAttribute('data-word-index', String(index));
            box.value = '1';
            box.checked = Boolean(keptSet[index]);
            label.appendChild(box);
            label.appendChild(document.createTextNode(' ' + word + ' — ' + (config.makeGapLabel || '')));
            list.appendChild(label);
        });
        writeSelection(kept);
    };

    list.addEventListener('change', (e) => {
        const box = e.target.closest('input[type="checkbox"]');
        if (!box || !list.contains(box)) {
            return;
        }
        const indices = Array.from(list.querySelectorAll('input[type="checkbox"]:checked'))
            .map((el) => parseInt(el.getAttribute('data-word-index'), 10))
            .filter((n) => !isNaN(n));
        writeSelection(indices);
        if (window.M && window.M.core_formchangechecker
                && typeof window.M.core_formchangechecker.set_form_changed === 'function') {
            window.M.core_formchangechecker.set_form_changed();
        }
    });

    source.addEventListener('input', rebuild);

    // First paint: if PHP already rendered checkboxes, just sync the hidden field.
    if (list.querySelector('input[type="checkbox"]')) {
        writeSelection(readSelection());
    } else {
        rebuild();
    }
};
