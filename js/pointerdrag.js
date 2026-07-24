/**
 * pointerdrag.js - Pointer Events based drag toolkit.
 *
 * A thin primitive. Three orthogonal capabilities:
 *
 *   1. Pointer tracking. Emits Drag:start / Drag:move / Drag:end
 *      with the current clientX / clientY, source reference, and a
 *      mutable state bag consumers own.
 *
 *   2. Ghost rendering (opt-in via `ghosting: true`). Maintains a
 *      pixel-pinned clone of the source under the cursor via
 *      transform: translate3d. pointer-events:none so hit-testing
 *      sees through.
 *
 *   3. Drop target hit-testing (opt-in; automatic unless
 *      `nodrop: true`). Marks droppables with `.horde-drop-target`.
 *      Emits Drag:enter / Drag:leave / Drag:drop as targets change.
 *
 * Cross-cutting:
 *
 *   - Pointer capture via setPointerCapture so drags survive the
 *     cursor leaving the source.
 *   - stopPropagation on pointerdown so an innermost draggable wins
 *     when draggables nest.
 *   - pointercancel handled the same as pointerup, except Drag:end
 *     detail carries cancelled: true.
 *   - Auto-scroll of a named container (via `scroll` option) using
 *     requestAnimationFrame. After each scroll nudge, replays the
 *     last pointermove so consumer handlers recompute against the
 *     new scroll state.
 *   - Touch, pen and mouse unified because Pointer Events cover
 *     them.
 *
 * Deliberately NOT provided (consumers do their own coordinate
 * work): snap, snap-to-parent, constraint, offset, caption, parent
 * clamp. Every one of these tried to abstract a coordinate frame
 * decision that only the consumer can make correctly.
 *
 * Custom events (native CustomEvent, bubbling):
 *
 *   - Drag:start   Fired on source once threshold crossed.
 *   - Drag:move    Fired on source on every pointermove during
 *                  drag, and after each auto-scroll nudge.
 *   - Drag:enter   Fired on source and target when the cursor
 *                  enters a droppable.
 *   - Drag:leave   Fired on source and target when the cursor
 *                  leaves a droppable.
 *   - Drag:drop    Fired on the drop target (bubbles) on pointerup
 *                  over a valid droppable.
 *   - Drag:end     Fired on source on pointerup or pointercancel.
 *
 * All details carry:
 *   { source, ghost, state, clientX, clientY, targetEl, originalEvent }
 * `Drag:end` additionally carries `cancelled: boolean`.
 *
 * `state` is a plain object created at drag start, mutable by the
 * consumer, preserved through moves and end. Replaces the
 * dragdrop2 pattern of Object.extend(dragInstance, {...}) plus
 * element.retrieve('drag').
 *
 * @author     Ralf Lang <ralf.lang@ralf-lang.de>
 * @copyright  2026 The Horde Project (http://www.horde.org/)
 * @license    LGPL-2.1 (http://www.horde.org/licenses/lgpl21)
 */

(function (root) {
    'use strict';

    var DROP_TARGET_CLASS = 'horde-drop-target';

    function HordeDraggable(element, opts) {
        if (!(this instanceof HordeDraggable)) {
            return new HordeDraggable(element, opts);
        }
        this.element = resolveElement(element);
        this.opts = mergeOptions(opts);

        this._pointerId = null;
        this._pendingDrag = false;
        this._active = false;
        this._startClient = { x: 0, y: 0 };
        this._grabOffset = { x: 0, y: 0 };
        this._lastMoveEvent = null;
        this._ghost = null;
        this._state = null;
        this._scrollContainer = null;
        this._scrollDelta = 0;
        this._rafId = null;
        this._currentTarget = null;

        this._onPointerDown = this._onPointerDown.bind(this);
        this._onPointerMove = this._onPointerMove.bind(this);
        this._onPointerEnd = this._onPointerEnd.bind(this);
        this._autoScrollTick = this._autoScrollTick.bind(this);
        this._onExternalScroll = this._onExternalScroll.bind(this);

        /* touch-action: none on the source. Without this, touch
         * browsers claim the pointer for pan/zoom after ~10 px of
         * movement and fire pointercancel on us. Remember the
         * caller's original value so destroy() can restore it. */
        this._srcTouchActionSaved = this.element.style.touchAction;
        this.element.style.touchAction = 'none';

        this.element.addEventListener('pointerdown', this._onPointerDown);
    }

    HordeDraggable.prototype = {

        destroy: function () {
            if (this._active || this._pendingDrag) {
                this._cleanup(true);
            }
            this.element.removeEventListener('pointerdown', this._onPointerDown);
            this.element.style.touchAction = this._srcTouchActionSaved;
        },

        _onPointerDown: function (e) {
            if (e.button !== 0 && !(this.opts.rightclick && e.button === 2)) {
                return;
            }
            /* Innermost draggable wins. Prevents ancestor draggables
             * from also picking up this pointerdown. */
            e.stopPropagation();

            try {
                this.element.setPointerCapture(e.pointerId);
            } catch (ex) {
                /* Non-fatal. Some elements refuse capture; the drag
                 * still works because we listen on the element and
                 * pointer events keep flowing. */
            }

            this._pointerId = e.pointerId;
            this._startClient.x = e.clientX;
            this._startClient.y = e.clientY;
            this._lastMoveEvent = e;
            this._pendingDrag = true;
            this._active = false;

            /* Suppress text selection for the drag duration. */
            document.body.style.userSelect = 'none';

            this.element.addEventListener('pointermove', this._onPointerMove);
            this.element.addEventListener('pointerup', this._onPointerEnd);
            this.element.addEventListener('pointercancel', this._onPointerEnd);
        },

        _onPointerMove: function (e) {
            if (e.pointerId !== this._pointerId) {
                return;
            }
            this._lastMoveEvent = e;

            if (this._pendingDrag) {
                var dx = e.clientX - this._startClient.x;
                var dy = e.clientY - this._startClient.y;
                if ((dx * dx + dy * dy) < (this.opts.threshold * this.opts.threshold)) {
                    return;
                }
                this._pendingDrag = false;
                this._active = true;
                this._startDrag(e);
            }

            if (this._ghost) {
                this._positionGhost(e.clientX, e.clientY);
            }
            if (!this.opts.nodrop) {
                this._updateDropTarget(e);
            }
            this._maybeAutoScroll(e.clientX, e.clientY);

            this._dispatch('Drag:move', this.element, this._detail(e));
        },

        _onPointerEnd: function (e) {
            if (e.pointerId !== this._pointerId) {
                return;
            }
            var wasActive = this._active;
            var cancelled = (e.type === 'pointercancel');

            if (wasActive) {
                if (!cancelled && this._currentTarget) {
                    this._dispatch('Drag:drop', this._currentTarget,
                        this._detail(e));
                }
                var endDetail = this._detail(e);
                endDetail.cancelled = cancelled;
                this._dispatch('Drag:end', this.element, endDetail);
                /* Suppress the click event that browsers fire on
                 * pointerup after a drag. Without this, consumer
                 * click-handlers (e.g., kronolith's click-to-edit)
                 * fire on every drop and re-open a dialog with a
                 * stale server view. Capturing listener + one-shot
                 * so it never masks a legitimate click. */
                var swallow = function (ev) {
                    ev.stopPropagation();
                    ev.preventDefault();
                    window.removeEventListener('click', swallow, true);
                };
                window.addEventListener('click', swallow, true);
                /* If no click fires within a tick (e.g., because
                 * pointerup happened outside the source), remove the
                 * one-shot so it does not eat a later click. */
                setTimeout(function () {
                    window.removeEventListener('click', swallow, true);
                }, 0);
            }
            this._cleanup(false);
        },

        _startDrag: function (e) {
            /* Grab offset: cursor position relative to the source's
             * top-left corner at drag start. Ghost stays aligned to
             * this offset for the whole drag. */
            var rect = this.element.getBoundingClientRect();
            this._grabOffset.x = e.clientX - rect.left;
            this._grabOffset.y = e.clientY - rect.top;

            /* Fresh state bag for this drag. Consumer owns it. */
            this._state = {};

            if (this.opts.ghosting) {
                this._ghost = this._createGhost(rect);
                this._positionGhost(e.clientX, e.clientY);
            }
            if (this.opts.scroll) {
                this._scrollContainer = resolveElement(this.opts.scroll);
            }
            /* Replay Drag:move whenever an external scroll (mouse
             * wheel, trackpad, kinetic touch, JS-driven) changes
             * either the drag's scroll container or the window.
             * The primitive already replays after its own auto-scroll
             * nudges; this handles the same class of state change
             * from any other source uniformly. Consumers that read
             * container.scrollTop or getBoundingClientRect() in
             * their move handler stay coherent. */
            if (this._scrollContainer) {
                this._scrollContainer.addEventListener('scroll', this._onExternalScroll);
            }
            window.addEventListener('scroll', this._onExternalScroll, true);

            this._dispatch('Drag:start', this.element, this._detail(e));
        },

        _onExternalScroll: function () {
            if (this._active && this._lastMoveEvent) {
                this._onPointerMove(this._lastMoveEvent);
            }
        },

        _createGhost: function (srcRect) {
            /* Deep clone so text and children survive. Pin pixel
             * dimensions from the source's rendered size, otherwise
             * a percentage width on the source blows up against
             * <body>'s width once the clone is reparented. */
            var g = this.element.cloneNode(true);
            g.removeAttribute('id');
            g.style.position = 'fixed';
            g.style.top = '0';
            g.style.left = '0';
            g.style.margin = '0';
            g.style.width = srcRect.width + 'px';
            g.style.height = srcRect.height + 'px';
            g.style.boxSizing = 'border-box';
            g.style.pointerEvents = 'none';
            g.style.willChange = 'transform';
            g.style.zIndex = '10000';
            g.style.opacity = '0.7';
            document.body.appendChild(g);
            return g;
        },

        _positionGhost: function (clientX, clientY) {
            var x = clientX - this._grabOffset.x;
            var y = clientY - this._grabOffset.y;
            this._ghost.style.transform =
                'translate3d(' + x + 'px, ' + y + 'px, 0)';
        },

        _updateDropTarget: function (e) {
            var under = document.elementFromPoint(e.clientX, e.clientY);
            var target = under ? under.closest('.' + DROP_TARGET_CLASS) : null;
            if (target === this._currentTarget) {
                return;
            }
            if (this._currentTarget) {
                var leaveDetail = this._detail(e);
                leaveDetail.targetEl = this._currentTarget;
                this._dispatch('Drag:leave', this.element, leaveDetail);
                this._dispatch('Drag:leave', this._currentTarget, leaveDetail);
            }
            this._currentTarget = target;
            if (target) {
                var enterDetail = this._detail(e);
                enterDetail.targetEl = target;
                this._dispatch('Drag:enter', this.element, enterDetail);
                this._dispatch('Drag:enter', target, enterDetail);
            }
        },

        _maybeAutoScroll: function (clientX, clientY) {
            if (!this._scrollContainer) {
                this._scrollDelta = 0;
                return;
            }
            var rect = this._scrollContainer.getBoundingClientRect();
            var hot = this.opts.scrollHotZone;
            var maxSpeed = this.opts.scrollMaxSpeed;

            var topDist = clientY - rect.top;
            var bottomDist = rect.bottom - clientY;
            var newDelta = 0;

            if (topDist < hot && this._scrollContainer.scrollTop > 0) {
                newDelta = -Math.min(maxSpeed,
                    Math.ceil((hot - topDist) * maxSpeed / hot));
            } else if (bottomDist < hot) {
                var maxScroll = this._scrollContainer.scrollHeight
                    - this._scrollContainer.clientHeight;
                if (this._scrollContainer.scrollTop < maxScroll) {
                    newDelta = Math.min(maxSpeed,
                        Math.ceil((hot - bottomDist) * maxSpeed / hot));
                }
            }
            this._scrollDelta = newDelta;
            if (newDelta !== 0 && this._rafId === null) {
                this._rafId = window.requestAnimationFrame(this._autoScrollTick);
            }
        },

        _autoScrollTick: function () {
            this._rafId = null;
            if (!this._scrollDelta || !this._scrollContainer) {
                return;
            }
            this._scrollContainer.scrollTop += this._scrollDelta;
            /* Replay the last pointermove. The consumer's handler
             * recomputes against the new scroll state; the ghost
             * refreshes; drop target hit-testing refreshes. */
            if (this._lastMoveEvent) {
                this._onPointerMove(this._lastMoveEvent);
            }
            if (this._scrollDelta !== 0) {
                this._rafId = window.requestAnimationFrame(this._autoScrollTick);
            }
        },

        _cleanup: function (forceReleaseCapture) {
            if (this._rafId !== null) {
                window.cancelAnimationFrame(this._rafId);
                this._rafId = null;
            }
            /* pointerup and pointercancel implicitly release capture
             * per the Pointer Events spec, so releasePointerCapture()
             * is only needed on paths that do not go through those
             * events. destroy() is the only such path — the caller
             * asked us to tear down mid-drag. */
            if (forceReleaseCapture && this._pointerId !== null) {
                try {
                    this.element.releasePointerCapture(this._pointerId);
                } catch (ex) {}
            }
            this.element.removeEventListener('pointermove', this._onPointerMove);
            this.element.removeEventListener('pointerup', this._onPointerEnd);
            this.element.removeEventListener('pointercancel', this._onPointerEnd);
            if (this._scrollContainer) {
                this._scrollContainer.removeEventListener('scroll', this._onExternalScroll);
            }
            window.removeEventListener('scroll', this._onExternalScroll, true);

            document.body.style.userSelect = '';

            if (this._ghost && this._ghost.parentNode) {
                this._ghost.parentNode.removeChild(this._ghost);
            }
            this._ghost = null;
            this._state = null;
            this._active = false;
            this._pendingDrag = false;
            this._pointerId = null;
            this._scrollContainer = null;
            this._scrollDelta = 0;
            this._lastMoveEvent = null;
            this._currentTarget = null;
        },

        _detail: function (e) {
            return {
                source: this.element,
                ghost: this._ghost,
                state: this._state,
                clientX: e.clientX,
                clientY: e.clientY,
                targetEl: this._currentTarget,
                originalEvent: e
            };
        },

        _dispatch: function (name, target, detail) {
            target.dispatchEvent(new CustomEvent(name, {
                bubbles: true,
                cancelable: true,
                detail: detail
            }));
        }
    };

    function HordeDroppable(element) {
        if (!(this instanceof HordeDroppable)) {
            return new HordeDroppable(element);
        }
        this.element = resolveElement(element);
        this.element.classList.add(DROP_TARGET_CLASS);
    }

    HordeDroppable.prototype = {
        destroy: function () {
            this.element.classList.remove(DROP_TARGET_CLASS);
        }
    };

    function mergeOptions(opts) {
        opts = opts || {};
        return {
            threshold: opts.threshold || 0,
            rightclick: !!opts.rightclick,
            nodrop: !!opts.nodrop,
            ghosting: !!opts.ghosting,
            scroll: opts.scroll || null,
            scrollHotZone: opts.scrollHotZone || 40,
            scrollMaxSpeed: opts.scrollMaxSpeed || 20
        };
    }

    function resolveElement(ref) {
        if (typeof ref === 'string') {
            return document.getElementById(ref);
        }
        return ref;
    }

    root.HordeDraggable = HordeDraggable;
    root.HordeDroppable = HordeDroppable;
    root.HordeDragTargetClass = DROP_TARGET_CLASS;

}(typeof window !== 'undefined' ? window : this));
