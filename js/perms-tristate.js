/**
 * perms-tristate.js. Client-side niceties for the negative-permissions
 * administration dialog.
 *
 * Nothing here is required for the form to submit. The tri-state radios
 * are plain <input type="radio"> elements. The server reads the POST
 * body directly and folds the verdicts into grant / deny bitmasks in
 * Horde\Core\Perms\Ui::foldTriState().
 *
 * What this script adds:
 *   1. Arrow-key navigation between cells in a tri-state grid.
 *   2. Per-column bulk actions (grant / neutral / deny all).
 *   3. Optional live "N rules will change" preview under the submit
 *      button, so admins see the impact of a bulk action before it
 *      hits the server.
 *
 * The script uses only native browser APIs. No jQuery, no Prototype.
 * It attaches through DOMContentLoaded and cleans up nothing on
 * unload. A single admin form has a page lifetime, so leak-free
 * teardown is not a concern.
 */

(() => {
    'use strict';

    /* Cell verdict values must match Horde\Core\Perms\Ui::foldTriState. */
    const VERDICTS = ['grant', 'neutral', 'deny'];

    document.addEventListener('DOMContentLoaded', () => {
        const forms = document.querySelectorAll('form.perms-form');
        forms.forEach(wireForm);
        wireTreeToggles();
    });

    /**
     * Wires one perms form. Idempotent per form because the DOM only
     * gets one DOMContentLoaded pass.
     */
    function wireForm(form) {
        wireKeyboardNav(form);
        wireBulkActions(form);
        wireLivePreview(form);
    }

    /**
     * Wires the client-side expand/collapse buttons in the perms
     * admin tree. The server emits every node with its children
     * inline in the DOM (Horde\Core\Perms\Ui::renderTree passes
     * static=true to the ResponsiveRenderer) so the toggle is a pure
     * display: none flip on the sibling <ul>. No page reload, no
     * XHR, no server round-trip.
     *
     * The button text is "-" when expanded and "+" when collapsed
     * so the visual matches the marker convention used in the
     * legacy Horde_Tree render. aria-expanded is kept in sync for
     * screen readers.
     */
    function wireTreeToggles() {
        const buttons = document.querySelectorAll('button.perms-tree-toggle');
        buttons.forEach((button) => {
            button.addEventListener('click', (event) => {
                event.preventDefault();
                event.stopPropagation();
                const item = button.closest('.horde-tree__item');
                if (item === null) {
                    return;
                }
                const childList = item.querySelector(':scope > .horde-tree__list');
                if (childList === null) {
                    return;
                }
                const isCollapsed = childList.hasAttribute('hidden');
                if (isCollapsed) {
                    childList.removeAttribute('hidden');
                    button.textContent = '-';
                    button.setAttribute('aria-expanded', 'true');
                } else {
                    childList.setAttribute('hidden', '');
                    button.textContent = '+';
                    button.setAttribute('aria-expanded', 'false');
                }
            });
        });
    }

    /**
     * Arrow-key navigation between tri-state radios in the same grid.
     *
     * Left / Right cycles verdicts within a single radiogroup. That is
     * already the native browser behaviour, so this handler only takes
     * over Up / Down to jump between rows at the same column position.
     */
    function wireKeyboardNav(form) {
        form.addEventListener('keydown', (event) => {
            if (event.key !== 'ArrowUp' && event.key !== 'ArrowDown') {
                return;
            }
            const target = event.target;
            if (!(target instanceof HTMLInputElement) || target.type !== 'radio') {
                return;
            }
            const fieldset = target.closest('.perms-tri');
            if (fieldset === null) {
                return;
            }

            const verdict = target.value;
            const scope = fieldset.closest('.perms-scope');
            if (scope === null) {
                return;
            }

            /* Every tri-state fieldset in this scope, in DOM order. */
            const allFieldsets = Array.from(scope.querySelectorAll('.perms-tri'));
            const currentIndex = allFieldsets.indexOf(fieldset);
            if (currentIndex < 0) {
                return;
            }

            const delta = event.key === 'ArrowDown' ? 1 : -1;
            const nextIndex = currentIndex + delta;
            if (nextIndex < 0 || nextIndex >= allFieldsets.length) {
                return;
            }
            const nextFieldset = allFieldsets[nextIndex];
            const nextRadio = nextFieldset.querySelector(`input[type="radio"][value="${verdict}"]`);
            if (nextRadio instanceof HTMLInputElement) {
                event.preventDefault();
                nextRadio.focus();
            }
        });
    }

    /**
     * Bulk-action buttons. Any element carrying data-perms-bulk="scope"
     * plus data-verdict="grant|neutral|deny" applies that verdict to
     * every tri-state cell within the named scope.
     *
     * The template does not currently emit these buttons. The wiring
     * lives here so an install can enable bulk actions by adding
     * buttons to a local template override without JS changes.
     *
     *   <button type="button"
     *           data-perms-bulk="u"
     *           data-verdict="deny">Deny READ for every user</button>
     *
     * A data-column attribute narrows to one column, e.g.
     * data-column="4" affects only Horde_Perms::READ (bit 4).
     */
    function wireBulkActions(form) {
        form.addEventListener('click', (event) => {
            const button = event.target instanceof Element
                ? event.target.closest('[data-perms-bulk][data-verdict]')
                : null;
            if (button === null) {
                return;
            }
            event.preventDefault();

            const scope = button.dataset.permsBulk;
            const verdict = button.dataset.verdict;
            const column = button.dataset.column ?? null;
            if (!VERDICTS.includes(verdict)) {
                return;
            }

            applyBulk(form, scope, verdict, column);
            dispatchLiveUpdate(form);
        });
    }

    /**
     * Applies a verdict to every matching radio in the given scope.
     *
     * @param scope   The value of the .perms-scope data-scope attribute.
     * @param verdict 'grant' | 'neutral' | 'deny'
     * @param column  Optional bit column to narrow to. When null, all
     *                columns in matching fieldsets are affected.
     */
    function applyBulk(form, scope, verdict, column) {
        const scopeEl = form.querySelector(`.perms-scope[data-scope="${cssEscape(scope)}"]`);
        if (scopeEl === null) {
            return;
        }
        const fieldsets = scopeEl.querySelectorAll('.perms-tri');
        fieldsets.forEach((fs) => {
            if (column !== null) {
                const anyRadio = fs.querySelector('input[type="radio"]');
                if (anyRadio === null) {
                    return;
                }
                /* Field names look like scope[bit]. Match by bit suffix. */
                const suffix = `[${column}]`;
                if (!anyRadio.name.endsWith(suffix)) {
                    return;
                }
            }
            const target = fs.querySelector(`input[type="radio"][value="${verdict}"]`);
            if (target instanceof HTMLInputElement) {
                target.checked = true;
            }
        });
    }

    /**
     * Live preview. Renders a running summary of "N rows will change"
     * below the submit button whenever a radio changes. The initial
     * verdict per cell is captured on load, so the count reflects
     * user edits rather than absolute state.
     */
    function wireLivePreview(form) {
        const initial = captureInitialVerdicts(form);
        if (initial.size === 0) {
            return;
        }

        let counter = form.querySelector('.perms-live-preview');
        if (counter === null) {
            counter = document.createElement('p');
            counter.className = 'perms-live-preview';
            counter.setAttribute('aria-live', 'polite');
            const buttons = form.querySelector('.perms-buttons');
            if (buttons !== null) {
                buttons.insertAdjacentElement('beforebegin', counter);
            }
        }

        const update = () => {
            const changed = countChanged(form, initial);
            counter.textContent = formatChangeSummary(changed);
        };
        form.addEventListener('change', update);
        form.addEventListener('perms:live-update', update);
        update();
    }

    /**
     * Captures the current verdict for every radio group. Keyed by the
     * group's shared name attribute (which is unique per group).
     */
    function captureInitialVerdicts(form) {
        const map = new Map();
        form.querySelectorAll('.perms-tri').forEach((fs) => {
            const checked = fs.querySelector('input[type="radio"]:checked');
            if (checked === null) {
                return;
            }
            map.set(checked.name, checked.value);
        });
        return map;
    }

    function countChanged(form, initial) {
        let changed = 0;
        form.querySelectorAll('.perms-tri').forEach((fs) => {
            const checked = fs.querySelector('input[type="radio"]:checked');
            if (checked === null) {
                return;
            }
            const wasInitial = initial.get(checked.name);
            if (wasInitial !== undefined && wasInitial !== checked.value) {
                changed += 1;
            }
        });
        return changed;
    }

    function formatChangeSummary(count) {
        if (count === 0) {
            return '';
        }
        if (count === 1) {
            return '1 permission cell will change.';
        }
        return `${count} permission cells will change.`;
    }

    function dispatchLiveUpdate(form) {
        form.dispatchEvent(new CustomEvent('perms:live-update', { bubbles: false }));
    }

    /**
     * Tiny CSS.escape() fallback for the rare cases where the value is
     * a scope name with a hyphen or dot. Modern browsers all have
     * window.CSS.escape but the fallback keeps the code resilient.
     */
    function cssEscape(value) {
        if (typeof window.CSS !== 'undefined' && typeof window.CSS.escape === 'function') {
            return window.CSS.escape(value);
        }
        return String(value).replace(/["\\]/g, '\\$&');
    }
})();
