## Summary
- Fix RedBox and autocompleter DOM handling that prevented dialog overlays from reopening cleanly
- Avoid destructive `Element#update()` when moving dialog HTML into `RB_window`
- Harden PrettyAutocompleter and KeyNavList teardown against detached nodes

## Motivation
Kronolith's event edit dialog uses RedBox with PrettyAutocompleter fields for attendees and tags. After closing the dialog once, reopening failed with `parentNode is null` errors from `removeChild`, leaving overlays and autocompleter state inconsistent.

## Changes
- **redbox.js**: replace `RB_window.update(html)` with a non-destructive move that parks previous children on `document.body`
- **prettyautocomplete.js**: re-init when box/input is detached; guard `removeItemNode()`, `updateInput()`, and `currentEntries()`; remove orphaned box nodes before rebuild
- **keynavlist.js**: guard `destroy()` when dropdown nodes are already detached

Companion PR in `horde/kronolith` wires these fixes into the event dialog lifecycle.

## Test plan
- [ ] In Kronolith, open/close/reopen event dialogs with attendee and tag autocompleters populated
- [ ] Verify no JavaScript console errors on second and third open
- [ ] Smoke-test other RedBox dialogs (tasks, calendars) still open and close normally
