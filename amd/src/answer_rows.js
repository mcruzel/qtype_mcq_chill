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
 * Add, remove and reorder choice rows on the QCM Chill editing form without a reload.
 *
 * @module     qtype_mcq_chill/answer_rows
 * @copyright  2026 Maxime Cruzel
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

const ADD_BUTTON_CLASS = 'qtype-mcq-chill-add-answer';
const REMOVE_BUTTON_CLASS = 'qtype-mcq-chill-remove-answer';
const WRAPPER_CLASS = 'qtype-mcq-chill-add-answer-wrapper';
const JS_ENABLED_CLASS = 'qtype-mcq-chill-js';
const HANDLE_CLASS = 'qtype-mcq-chill-drag-handle';
const MOVE_UP_CLASS = 'qtype-mcq-chill-move-up';
const MOVE_DOWN_CLASS = 'qtype-mcq-chill-move-down';
const REORDER_CLASS = 'qtype-mcq-chill-reorder';
const DRAGGING_CLASS = 'qtype-mcq-chill-dragging';
const TEMP_INDEX_OFFSET = 10000;

/**
 * Escape a string for use in a regular expression.
 *
 * @param {string} value the literal string
 * @returns {string}
 */
const escapeRegExp = (value) => value.replace(/[.*+?^${}()|[\]\\]/g, '\\$&');

/**
 * Replace a repeated-element index in a name, id or similar attribute.
 *
 * @param {string} value the original attribute value
 * @param {number} from the current index
 * @param {number} to the new index
 * @returns {string}
 */
const replaceIndex = (value, from, to) => {
    if (!value) {
        return value;
    }
    const fromText = String(from);
    return value
        .replace(new RegExp('\\[' + escapeRegExp(fromText) + '\\]', 'g'), '[' + to + ']')
        .replace(new RegExp('_' + escapeRegExp(fromText) + '(?![0-9])', 'g'), '_' + to);
};

/**
 * Rewrite every attribute of a row (and its descendants) from one index to another.
 *
 * @param {Element} root the choice row
 * @param {number} from the current index
 * @param {number} to the new index
 */
const rewriteIndex = (root, from, to) => {
    const nodes = [root, ...root.querySelectorAll('*')];
    nodes.forEach((el) => {
        Array.from(el.attributes).forEach((attr) => {
            const updated = replaceIndex(attr.value, from, to);
            if (updated !== attr.value) {
                el.setAttribute(attr.name, updated);
            }
        });
    });
};

/**
 * The mount point used for per-row action controls.
 *
 * @param {Element} row the choice row
 * @returns {Element}
 */
const getRowMount = (row) => {
    return row.querySelector('[data-fieldtype="group"]')
        || row.querySelector('.fgroup')
        || row.querySelector('.felement')
        || row;
};

/**
 * Initialise dynamic add/remove/reorder of answer rows on the question editing form.
 *
 * @param {Object} config
 * @param {string} config.groupName repeated group name (answergroup)
 * @param {string} config.repeatCountName hidden repeat count field (noanswers)
 * @param {string} config.phpAddButtonName the PHP "add N fields" submit name
 * @param {number} config.startIndex next index expected by PHP (row count)
 * @param {number} config.minChoices minimum number of non-empty choices
 * @param {string} config.addLabel label of the JS add button
 * @param {string} config.removeLabel label of each JS remove button
 * @param {string} config.choiceLabel choice label template containing {$a}
 * @param {string} config.dragHandleLabel accessible label of the drag handle
 * @param {string} config.moveUpLabel label of the move-up button
 * @param {string} config.moveDownLabel label of the move-down button
 */
export const init = (config) => {
    if (!config || !config.groupName) {
        return;
    }

    const groupName = config.groupName;
    const minChoices = Number(config.minChoices) || 2;
    const rowIdPattern = new RegExp('^fitem_id_' + escapeRegExp(groupName) + '_(\\d+)$');
    const rowSelectorPrefix = '[id^="fitem_id_' + groupName + '_"]';

    /**
     * The fitem wrappers of the repeated choice groups.
     *
     * @returns {Element[]}
     */
    const getRows = () => {
        return Array.from(document.querySelectorAll(rowSelectorPrefix))
            .filter((el) => rowIdPattern.test(el.id));
    };

    const firstRow = getRows()[0];
    if (!firstRow) {
        return;
    }

    const form = firstRow.closest('form');
    if (!form || form.dataset.qtypeMcqChillAnswerRows) {
        return;
    }
    form.dataset.qtypeMcqChillAnswerRows = '1';
    form.classList.add(JS_ENABLED_CLASS);

    /**
     * Index encoded in a row's id, or 0.
     *
     * @param {Element} row
     * @returns {number}
     */
    const indexFromRow = (row) => {
        const match = row.id.match(rowIdPattern);
        return match ? parseInt(match[1], 10) : 0;
    };

    /**
     * The choice row that contains a descendant, if any.
     *
     * @param {EventTarget|null} target
     * @returns {Element|null}
     */
    const rowFromTarget = (target) => {
        if (!target || !target.closest) {
            return null;
        }
        const row = target.closest(rowSelectorPrefix);
        if (!row || !form.contains(row) || !rowIdPattern.test(row.id)) {
            return null;
        }
        return row;
    };

    /**
     * Visible label of a choice, with a 1-based number.
     *
     * @param {number} index zero-based index
     * @returns {string}
     */
    const formatChoiceLabel = (index) => {
        return String(config.choiceLabel || '').replace(/\{\$a\}/g, String(index + 1));
    };

    /**
     * Update the group label and legend after a reindex.
     *
     * @param {Element} row
     * @param {number} index
     */
    const applyChoiceLabel = (row, index) => {
        const text = formatChoiceLabel(index);
        row.querySelectorAll('label, legend').forEach((el) => {
            const forAttr = el.getAttribute('for') || '';
            if (forAttr.indexOf('fraction') !== -1 || forAttr.indexOf('answer') !== -1) {
                return;
            }
            if (el.id && el.id.indexOf(groupName) !== -1) {
                el.textContent = text;
            } else if (el.tagName === 'LEGEND') {
                el.textContent = text;
            }
        });
    };

    /**
     * Keep Moodle's hidden repeat count in sync with the DOM.
     *
     * @param {number} count
     */
    const setRepeatCount = (count) => {
        const field = form.querySelector('[name="' + config.repeatCountName + '"]');
        if (field) {
            field.value = String(count);
        }
    };

    /**
     * Clear values copied from the cloned row.
     *
     * @param {Element} row
     */
    const resetRow = (row) => {
        row.querySelectorAll('input[type="text"], textarea').forEach((el) => {
            el.value = '';
        });
        row.querySelectorAll('input[type="checkbox"]').forEach((el) => {
            el.checked = false;
        });
        row.querySelectorAll('.invalid-feedback, .form-control-feedback').forEach((el) => {
            el.textContent = '';
        });
        row.querySelectorAll('.is-invalid, .has-error').forEach((el) => {
            el.classList.remove('is-invalid', 'has-error');
        });
        const handle = row.querySelector('.' + HANDLE_CLASS);
        if (handle) {
            handle.setAttribute('aria-grabbed', 'false');
        }
        row.classList.remove(DRAGGING_CLASS);
    };

    /**
     * Reindex remaining rows to contiguous 0..n-1 names and ids.
     */
    const reindexAll = () => {
        const rows = getRows();
        rows.forEach((row, target) => {
            const from = indexFromRow(row);
            if (from !== target) {
                rewriteIndex(row, from, TEMP_INDEX_OFFSET + target);
            }
        });
        getRows().forEach((row, target) => {
            const from = indexFromRow(row);
            if (from !== target) {
                rewriteIndex(row, from, target);
            }
            applyChoiceLabel(row, target);
        });
        setRepeatCount(getRows().length);
    };

    /**
     * Whether a row currently has non-empty choice text.
     *
     * @param {Element} row
     * @returns {boolean}
     */
    const rowIsFilled = (row) => {
        const input = row.querySelector('input[name^="answer["]');
        return Boolean(input && input.value.trim() !== '');
    };

    /**
     * Enable or grey out each Remove button according to MIN_CHOICES.
     */
    const refreshRemoveState = () => {
        const rows = getRows();
        const filled = rows.filter(rowIsFilled).length;
        rows.forEach((row) => {
            const button = row.querySelector('.' + REMOVE_BUTTON_CLASS);
            if (!button) {
                return;
            }
            const wouldDropBelowMin = rowIsFilled(row) && (filled - 1) < minChoices;
            button.disabled = rows.length <= 1 || wouldDropBelowMin;
        });
    };

    /**
     * Enable or grey out move up/down buttons at the ends of the list.
     */
    const refreshMoveState = () => {
        const rows = getRows();
        rows.forEach((row, index) => {
            const up = row.querySelector('.' + MOVE_UP_CLASS);
            const down = row.querySelector('.' + MOVE_DOWN_CLASS);
            if (up) {
                up.disabled = index === 0;
            }
            if (down) {
                down.disabled = index === rows.length - 1;
            }
        });
    };

    /**
     * Tell Moodle the form has unsaved changes.
     */
    const markFormChanged = () => {
        if (window.M && window.M.core_formchangechecker
                && typeof window.M.core_formchangechecker.set_form_changed === 'function') {
            window.M.core_formchangechecker.set_form_changed();
        }
    };

    /**
     * After a structural change, keep indexes and button states in sync.
     */
    const afterStructureChange = () => {
        reindexAll();
        refreshRemoveState();
        refreshMoveState();
        markFormChanged();
    };

    /**
     * Append a Remove button to a choice row if it does not already have one.
     *
     * @param {Element} row
     */
    const ensureRemoveButton = (row) => {
        if (row.querySelector('.' + REMOVE_BUTTON_CLASS)) {
            return;
        }
        const button = document.createElement('button');
        button.type = 'button';
        button.className = 'btn btn-sm btn-outline-danger ' + REMOVE_BUTTON_CLASS + ' ms-2';
        button.textContent = config.removeLabel;
        getRowMount(row).appendChild(button);
    };

    /**
     * Prepend a drag handle and keyboard move buttons to a choice row.
     *
     * Only the handle is draggable, so text inputs keep their normal behaviour.
     *
     * @param {Element} row
     */
    const ensureDragControls = (row) => {
        if (row.querySelector('.' + HANDLE_CLASS)) {
            return;
        }

        const controls = document.createElement('span');
        controls.className = REORDER_CLASS + ' me-2';

        const handle = document.createElement('span');
        handle.className = HANDLE_CLASS;
        handle.draggable = true;
        handle.setAttribute('role', 'button');
        handle.setAttribute('tabindex', '0');
        handle.setAttribute('aria-label', config.dragHandleLabel);
        handle.setAttribute('aria-grabbed', 'false');
        handle.setAttribute('title', config.dragHandleLabel);
        handle.textContent = '\u2630';
        handle.style.cursor = 'grab';
        handle.style.userSelect = 'none';
        handle.style.display = 'inline-block';
        handle.style.padding = '0.15rem 0.4rem';

        const up = document.createElement('button');
        up.type = 'button';
        up.className = 'btn btn-sm btn-outline-secondary ' + MOVE_UP_CLASS + ' ms-1';
        up.textContent = config.moveUpLabel;
        up.setAttribute('aria-label', config.moveUpLabel);

        const down = document.createElement('button');
        down.type = 'button';
        down.className = 'btn btn-sm btn-outline-secondary ' + MOVE_DOWN_CLASS + ' ms-1';
        down.textContent = config.moveDownLabel;
        down.setAttribute('aria-label', config.moveDownLabel);

        controls.appendChild(handle);
        controls.appendChild(up);
        controls.appendChild(down);
        getRowMount(row).insertBefore(controls, getRowMount(row).firstChild);
    };

    /**
     * Move a row by one position and reindex.
     *
     * @param {Element} row
     * @param {number} direction -1 to move up, 1 to move down
     */
    const moveRow = (row, direction) => {
        const rows = getRows();
        const index = rows.indexOf(row);
        const targetIndex = index + direction;
        if (index < 0 || targetIndex < 0 || targetIndex >= rows.length) {
            return;
        }
        const target = rows[targetIndex];
        if (direction < 0) {
            target.before(row);
        } else {
            target.after(row);
        }
        afterStructureChange();
        const focusClass = direction < 0 ? MOVE_UP_CLASS : MOVE_DOWN_CLASS;
        const focusButton = row.querySelector('.' + focusClass);
        if (focusButton) {
            focusButton.focus();
        }
    };

    /**
     * Clone the last choice row, reset it, and insert it without submitting.
     */
    const addRow = () => {
        const rows = getRows();
        const last = rows[rows.length - 1];
        if (!last) {
            return;
        }
        const clone = last.cloneNode(true);
        resetRow(clone);
        const wrapper = form.querySelector('.' + WRAPPER_CLASS);
        if (wrapper) {
            wrapper.before(clone);
        } else {
            last.after(clone);
        }
        ensureRemoveButton(clone);
        ensureDragControls(clone);
        afterStructureChange();
        const input = clone.querySelector('input[name^="answer["]');
        if (input) {
            input.focus();
        }
    };

    /**
     * Remove a choice row from the DOM and reindex the rest.
     *
     * @param {Element} row
     */
    const removeRow = (row) => {
        const button = row.querySelector('.' + REMOVE_BUTTON_CLASS);
        if (!button || button.disabled || getRows().length <= 1) {
            return;
        }
        row.remove();
        afterStructureChange();
    };

    /**
     * Insert the dragged row before or after the drop target, then reindex.
     *
     * @param {Element} dragged
     * @param {Element} target
     * @param {number} clientY
     */
    const dropRowOn = (dragged, target, clientY) => {
        if (!dragged || !target || dragged === target) {
            return;
        }
        const rect = target.getBoundingClientRect();
        if (clientY < rect.top + (rect.height / 2)) {
            target.before(dragged);
        } else {
            target.after(dragged);
        }
        afterStructureChange();
    };

    getRows().forEach((row) => {
        ensureRemoveButton(row);
        ensureDragControls(row);
    });

    const phpAdd = form.querySelector('[name="' + config.phpAddButtonName + '"]');
    if (phpAdd) {
        const item = phpAdd.closest('.fitem') || phpAdd;
        item.classList.add('d-none');
    }

    let addWrapper = form.querySelector('.' + WRAPPER_CLASS);
    if (!addWrapper) {
        addWrapper = document.createElement('div');
        addWrapper.className = 'fitem ' + WRAPPER_CLASS + ' mt-2';
        const addButton = document.createElement('button');
        addButton.type = 'button';
        addButton.className = 'btn btn-secondary ' + ADD_BUTTON_CLASS;
        addButton.textContent = config.addLabel;
        addWrapper.appendChild(addButton);
        const lastRow = getRows()[getRows().length - 1];
        if (phpAdd && phpAdd.closest('.fitem')) {
            phpAdd.closest('.fitem').before(addWrapper);
        } else if (lastRow) {
            lastRow.after(addWrapper);
        }
    }

    let dragRow = null;

    form.addEventListener('click', (e) => {
        const addButton = e.target.closest('.' + ADD_BUTTON_CLASS);
        if (addButton && form.contains(addButton)) {
            e.preventDefault();
            addRow();
            return;
        }
        const removeButton = e.target.closest('.' + REMOVE_BUTTON_CLASS);
        if (removeButton && form.contains(removeButton)) {
            e.preventDefault();
            const row = rowFromTarget(removeButton);
            if (row) {
                removeRow(row);
            }
            return;
        }
        const moveUp = e.target.closest('.' + MOVE_UP_CLASS);
        if (moveUp && form.contains(moveUp)) {
            e.preventDefault();
            const row = rowFromTarget(moveUp);
            if (row) {
                moveRow(row, -1);
            }
            return;
        }
        const moveDown = e.target.closest('.' + MOVE_DOWN_CLASS);
        if (moveDown && form.contains(moveDown)) {
            e.preventDefault();
            const row = rowFromTarget(moveDown);
            if (row) {
                moveRow(row, 1);
            }
        }
    });

    form.addEventListener('input', (e) => {
        if (e.target && e.target.name && e.target.name.indexOf('answer[') === 0) {
            refreshRemoveState();
        }
    });

    form.addEventListener('dragstart', (e) => {
        const handle = e.target.closest('.' + HANDLE_CLASS);
        if (!handle || !form.contains(handle)) {
            return;
        }
        const row = rowFromTarget(handle);
        if (!row) {
            return;
        }
        dragRow = row;
        handle.setAttribute('aria-grabbed', 'true');
        handle.style.cursor = 'grabbing';
        row.classList.add(DRAGGING_CLASS);
        e.dataTransfer.effectAllowed = 'move';
        e.dataTransfer.setData('text/plain', row.id);
    });

    form.addEventListener('dragend', (e) => {
        const handle = e.target.closest('.' + HANDLE_CLASS);
        if (handle) {
            handle.setAttribute('aria-grabbed', 'false');
            handle.style.cursor = 'grab';
        }
        if (dragRow) {
            dragRow.classList.remove(DRAGGING_CLASS);
        }
        dragRow = null;
    });

    form.addEventListener('dragover', (e) => {
        const row = rowFromTarget(e.target);
        if (!row || !dragRow || row === dragRow) {
            return;
        }
        e.preventDefault();
        e.dataTransfer.dropEffect = 'move';
    });

    form.addEventListener('drop', (e) => {
        const row = rowFromTarget(e.target);
        if (!row || !dragRow) {
            return;
        }
        e.preventDefault();
        dropRowOn(dragRow, row, e.clientY);
    });

    afterStructureChange();
};
