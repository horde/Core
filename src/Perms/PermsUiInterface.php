<?php

declare(strict_types=1);

/**
 * Copyright 2026 The Horde Project (http://www.horde.org/)
 *
 * See the enclosed file LICENSE for license information (LGPL). If you
 * did not receive this file, see http://www.horde.org/licenses/lgpl21.
 *
 * @category Horde
 * @package  Core
 * @license  http://www.horde.org/licenses/lgpl21 LGPL 2.1
 */

namespace Horde\Core\Perms;

use Horde_Perms;
use Horde_Perms_Permission;

/**
 * Contract for administrative permission UI implementations.
 *
 * Two concrete implementations ship with Horde. The legacy
 * Horde_Core_Perms_Ui uses Horde_Form and grant-only checkboxes. The
 * modern Horde\Core\Perms\Ui uses Horde_View with a responsive HTML form
 * and grant / neutral / deny radio controls per permission bit.
 *
 * Callers obtain an instance through Horde\Core\Factory\PermsUi. The
 * factory picks the implementation based on the $conf['perms']['admin_ui']
 * configswitch.
 *
 * The interface intentionally mirrors the public method set of the
 * legacy class so callers can switch between implementations without
 * changes elsewhere. The legacy class is not retrofitted to declare
 * `implements PermsUiInterface` because its release predates this
 * interface. Consumers that want to accept either implementation should
 * type-hint on `PermsUiInterface|\Horde_Core_Perms_Ui` where needed.
 */
interface PermsUiInterface
{
    /**
     * Injects the Horde_Variables (or Horde\Util\Variables) instance used
     * to read form submissions.
     */
    public function setVars(mixed $vars): void;

    /**
     * Renders the permissions tree as an HTML fragment to standard
     * output.
     *
     * The legacy Horde_Tree renderer echoes its output rather than
     * returning it. Consistent with that, this method is void: it
     * writes to the output buffer. Callers who need the string form
     * should wrap the call in Horde::startBuffer() / Horde::endBuffer().
     *
     * @param int|string|null $current  The currently-selected permission id.
     *                                  Null and the empty string are treated
     *                                  the same as Horde_Perms::ROOT so
     *                                  callers can pass form-data values
     *                                  through without pre-normalizing.
     */
    public function renderTree(int|string|null $current = Horde_Perms::ROOT): void;

    /**
     * Prepares the add-child-permission form for the given parent
     * permission.
     */
    public function setupAddForm(
        Horde_Perms_Permission $permission,
        ?string $force_choice = null
    ): void;

    /**
     * Validates the submitted add-form data. Returns the extracted info
     * hash on success, or false when validation fails.
     *
     * @return array|false
     */
    public function validateAddForm(array &$info);

    /**
     * Prepares the edit form for the given permission.
     */
    public function setupEditForm(Horde_Perms_Permission $permission): void;

    /**
     * Validates the submitted edit-form data. Returns the extracted info
     * hash on success, or false when validation fails. The returned hash
     * is shaped for Horde_Perms_Permission::updatePermissions().
     *
     * @return array|false
     */
    public function validateEditForm(array &$info);

    /**
     * Prepares the delete-confirmation form for the given permission.
     */
    public function setupDeleteForm(Horde_Perms_Permission $permission): void;

    /**
     * Validates the submitted delete-form response. Returns true when the
     * user confirmed deletion, false when they explicitly cancelled, or
     * null when no submission is present.
     *
     * @return bool|array|null
     */
    public function validateDeleteForm(array &$info);

    /**
     * Renders the currently-prepared form to the output buffer.
     */
    public function renderForm(string $form_script = 'edit.php'): void;
}
