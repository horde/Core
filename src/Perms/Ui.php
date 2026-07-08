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

use Horde\Core\Uri\UriBuilderInterface;
use Horde\Token\Token;
use Horde\Tree\Node;
use Horde\Tree\Renderer\ResponsiveRenderer;
use Horde\Tree\TreeBuilder;
use Horde_Auth_Base;
use Horde_Core_Perms;
use Horde_Group;
use Horde_Notification_Handler;
use Horde_Perms;
use Horde_Perms_Base;
use Horde_Perms_Exception;
use Horde_Perms_Permission;
use Horde_Registry;
use Horde_View;
use Psr\Http\Message\ServerRequestInterface;

/**
 * Horde_View based responsive permissions administration UI.
 *
 * Renders the edit, add, and delete permission forms as plain HTML
 * templates. The edit form exposes tri-state (grant / neutral / deny)
 * radio controls per permission bit, backed by the Horde_Perms 3.1
 * deny cascade. The tree view is delegated to the legacy renderer
 * because there is no benefit in rewriting Horde_Tree here.
 *
 * Selected when $conf['perms']['negative_permissions'] = 'on'.
 * See Horde\Core\Factory\PermsUi.
 *
 * All collaborators arrive through the constructor. The class does
 * not read $GLOBALS or reach into the injector at runtime. The
 * factory is responsible for wiring dependencies at construction.
 */
class Ui implements PermsUiInterface
{
    /**
     * Rendered form state. One of null | 'edit' | 'add' | 'delete'.
     * Set by the setup* methods, consumed by renderForm().
     */
    private ?string $formMode = null;

    /**
     * Data hash passed to the currently-active Horde_View template.
     */
    private array $formData = [];

    /**
     * Form variables. Accepts either the legacy Horde_Variables or the
     * modern Horde\Util\Variables. Kept as `mixed` because both are in
     * play across the FRAMEWORK_6_0 tree.
     */
    private mixed $vars = null;

    /**
     * @param Horde_Perms_Base         $perms         Permission backend.
     * @param Horde_Core_Perms         $corePerms     Core wrapper (titles, params, tree metadata).
     * @param string                   $templatePath  Absolute path to the templates directory. The factory
     *                                                resolves this from the Horde registry.
     * @param ServerRequestInterface   $request       Current HTTP request. Used to gate validate*()
     *                                                methods on POST and to read the CSRF token.
     * @param Token                    $tokens        CSRF token service. Generates the token embedded
     *                                                in each rendered form and verifies the returned
     *                                                token on submission.
     * @param UriBuilderInterface      $uriBuilder    URL builder used by the tree render to compose
     *                                                add / edit / delete action links.
     * @param Horde_Registry           $registry      Registry used by the tree render to filter out
     *                                                permissions that belong to uninstalled apps.
     * @param Horde_Group|null         $groups        Group backend for the group picker. Null when the caller
     *                                                cannot construct one; the picker degrades to "no groups".
     * @param Horde_Auth_Base|null     $auth          Auth driver for the user picker. Null when the caller
     *                                                cannot construct one; the picker falls back to a text input.
     */
    public function __construct(
        private readonly Horde_Perms_Base $perms,
        private readonly Horde_Core_Perms $corePerms,
        private readonly string $templatePath,
        private readonly ServerRequestInterface $request,
        private readonly Token $tokens,
        private readonly UriBuilderInterface $uriBuilder,
        private readonly Horde_Registry $registry,
        private readonly ?Horde_Notification_Handler $notification = null,
        private readonly ?Horde_Group $groups = null,
        private readonly ?Horde_Auth_Base $auth = null
    ) {
    }

    public function setVars(mixed $vars): void
    {
        $this->vars = $vars;
    }

    public function renderTree(int|string|null $current = Horde_Perms::ROOT): void
    {
        // A null / empty $current means "no permission selected"
        // (index page, no query param). We still need a valid root
        // sentinel for the tree walk, but nothing should be marked as
        // the current selection. Track the two concerns separately.
        $selectionActive = ($current !== null && $current !== '');
        if (!$selectionActive) {
            $current = Horde_Perms::ROOT;
        }

        try {
            $nodes = $this->perms->getTree();
        } catch (Horde_Perms_Exception $e) {
            return;
        }

        $builder = new TreeBuilder('perms_ui');
        $installedApps = $this->registry->listApps(['notoolbar', 'active', 'hidden']);
        $extraRight = [];

        // Prefix node IDs so they stay strings through the tree's
        // internal id => node map. PHP promotes numeric string array
        // keys to int, and Horde\Tree\State\NullStateStorage's
        // isExpanded() strictly type-hints string, so numeric-only
        // IDs (like the perm_id column values) trigger a TypeError
        // inside ResponsiveRenderer. The prefix is stripped nowhere
        // externally visible: extraRight and the tree id are the same
        // opaque token, and the action links use the unprefixed id
        // for the URL query params.
        $nodeId = static fn (int|string $rawId): string => 'p:' . $rawId;

        foreach ($nodes as $perm_id => $node) {
            $params = [];
            if ($selectionActive && (string) $current === (string) $perm_id) {
                $params['class'] = 'selected';
            }

            $treeId = $nodeId($perm_id);

            if ($perm_id == Horde_Perms::ROOT) {
                $builder->addNode(new Node(
                    id: $treeId,
                    label: _("All Permissions"),
                    parentId: null,
                    expanded: true,
                    params: $params
                ));
                $extraRight[$treeId] = [$this->treeAddLink((string) $perm_id)];
                continue;
            }

            // Skip permissions whose owning app is not currently
            // installed. Same rule as the legacy render.
            $parents = explode(':', (string) $node);
            if (!in_array($parents[0], $installedApps, true)) {
                continue;
            }

            // If getApplicationPermissions() throws (backend misalignment,
            // uninstalled sub-package), skip the node rather than fail
            // the whole tree. Notifications are the legacy path's job.
            try {
                $app_perms = $this->corePerms->getApplicationPermissions($parents[0]);
            } catch (Horde_Perms_Exception $e) {
                continue;
            }

            $parent_id = $this->perms->getParent((string) $node);

            $links = [];
            if (isset($app_perms['tree'])
                && is_array(\Horde_Array::getElement($app_perms['tree'], $parents))) {
                $links[] = $this->treeAddLink((string) $perm_id);
            }
            $links[] = $this->treeEditLink((string) $perm_id);
            $links[] = $this->treeDeleteLink((string) $perm_id);
            $extraRight[$treeId] = $links;

            // Expand ancestors of the currently-selected node so it is
            // visible in the collapsed default state.
            $expanded = isset($nodes[$current])
                && strpos((string) $nodes[$current], (string) $node) === 0
                && $nodes[$current] !== $node;

            $builder->addNode(new Node(
                id: $treeId,
                label: $this->corePerms->getTitle((string) $node),
                parentId: $nodeId($parent_id),
                expanded: $expanded,
                params: $params
            ));
        }

        $tree = $builder->build()->sorted('label');

        // Compute a per-node "has children" map so the JS toggle
        // button can be emitted inline for every expandable row.
        // The button lives in .horde-tree__extra-left and lets the
        // browser show / hide the immediate <ul> sibling. No server
        // round-trip; the full tree is in the DOM once, JS just
        // flips display: none on the child list.
        $extraLeft = [];
        foreach ($tree->getNodes() as $id => $_node) {
            if ($tree->getChildIds($id) !== []) {
                $extraLeft[$id] = [
                    '<button type="button"'
                    . ' class="perms-tree-toggle"'
                    . ' aria-expanded="true"'
                    . ' aria-label="' . htmlspecialchars(_("Collapse or expand this branch"), ENT_QUOTES) . '"'
                    . '>-</button>',
                ];
            }
        }

        echo '<div class="horde-content">';
        echo (new ResponsiveRenderer())->render($tree, [
            'alternate' => true,
            'extraLeft' => $extraLeft,
            'extraRight' => $extraRight,
            // Fully-static render. Every child list is emitted in
            // the DOM. ResponsiveRenderer's interactive mode uses
            // a server-side ?ht_toggle_<name>=<id> round-trip which
            // the perms admin does not implement. Static mode plus
            // the client-side perms-tree-toggle button gives the
            // admin a fully browsable tree with expand / collapse
            // interactivity but no page reloads.
            'static' => true,
        ]);
        echo '</div>';
    }

    /**
     * Composes the "add child permission" link for a tree row. Wrapped
     * in a helper so the three link builders stay tidy and share the
     * modern UriBuilder / registry path.
     */
    private function treeAddLink(string $permId): string
    {
        $url = $this->uriBuilder
            ->withAppWebroot('horde')
            ->withPart('admin/perms/addchild.php')
            ->withQueryParams(['perm_id' => $permId])
            ->toHordeUrl();
        return sprintf(
            '<a class="permsAdd" title="%s" href="%s">+</a>',
            htmlspecialchars(_("Add Child Permission"), ENT_QUOTES),
            htmlspecialchars((string) $url, ENT_QUOTES)
        );
    }

    private function treeEditLink(string $permId): string
    {
        $url = $this->uriBuilder
            ->withAppWebroot('horde')
            ->withPart('admin/perms/edit.php')
            ->withQueryParams(['perm_id' => $permId])
            ->toHordeUrl();
        return sprintf(
            '<a class="permsEdit" title="%s" href="%s">✎</a>',
            htmlspecialchars(_("Edit Permission"), ENT_QUOTES),
            htmlspecialchars((string) $url, ENT_QUOTES)
        );
    }

    private function treeDeleteLink(string $permId): string
    {
        $url = $this->uriBuilder
            ->withAppWebroot('horde')
            ->withPart('admin/perms/delete.php')
            ->withQueryParams(['perm_id' => $permId])
            ->toHordeUrl();
        return sprintf(
            '<a class="permsDelete" title="%s" href="%s">✕</a>',
            htmlspecialchars(_("Delete Permission"), ENT_QUOTES),
            htmlspecialchars((string) $url, ENT_QUOTES)
        );
    }

    public function setupAddForm(
        Horde_Perms_Permission $permission,
        ?string $force_choice = null
    ): void {
        $this->formMode = 'add';
        $this->formData = [
            'permission' => $permission,
            'perm_id' => $this->perms->getPermissionId($permission),
            'parent_title' => $this->corePerms->getTitle($permission->getName()),
            'child_perms' => $this->corePerms->getAvailable($permission->getName()),
            'force_choice' => $force_choice,
            'existing_children' => $this->existingChildren($permission),
        ];
    }

    public function validateAddForm(array &$info)
    {
        if (!$this->isSubmission()) {
            return false;
        }
        $childValue = $this->readVar('child');
        if ($childValue === null || $childValue === '') {
            return false;
        }
        $info['perm_id'] = $this->readVar('perm_id');
        $info['child'] = $childValue;
        return $info;
    }

    public function setupEditForm(Horde_Perms_Permission $permission): void
    {
        $this->formMode = 'edit';

        $type = $permission->get('type');
        $params = $this->corePerms->getParams($permission->getName());

        $userList = $this->fetchUserList();
        $groupList = $this->fetchGroupList();

        $this->formData = [
            'permission' => $permission,
            'perm_id' => $this->perms->getPermissionId($permission),
            'title' => $this->corePerms->getTitle($permission->getName()),
            'type' => $type,
            'params' => $params,
            'cols' => Horde_Perms::getPermsArray(),

            // Grant + deny masks, one per scope. Templates use the pair
            // to render the tri-state radio group per (scope, bit).
            'default_grant' => $permission->getDefaultPermissions(),
            'default_deny' => $permission->getDefaultDenies(),

            // Guest has no deny counterpart. The Horde_Perms cascade
            // treats guests as disjunct, so the modern UI hides the
            // deny column for guest to match.
            'guest_grant' => $permission->getGuestPermissions(),

            'creator_grant' => $permission->getCreatorPermissions(),
            'creator_deny' => $permission->getCreatorDenies(),

            'user_grants' => $permission->getUserPermissions(),
            'user_denies' => $permission->getUserDenies(),
            'user_list' => $userList,
            'new_users' => $this->newAssignableUsers($userList, $permission->getUserPermissions()),

            'group_grants' => $permission->getGroupPermissions(),
            'group_denies' => $permission->getGroupDenies(),
            'group_list' => $groupList,
            'new_groups' => $this->newAssignableGroups($groupList, $permission->getGroupPermissions()),
        ];
    }

    public function validateEditForm(array &$info)
    {
        if (!$this->isSubmission()) {
            return false;
        }

        $info['perm_id'] = $this->readVar('perm_id');
        $type = (string) $this->readVar('perm_type', 'matrix');

        if ($type !== 'matrix' && $type !== 'boolean') {
            // Reassigning the by-ref param to the returned array so the
            // caller sees the numeric values. validateEditForm's
            // signature commits to $info being populated in-place.
            $info = $this->buildNumericInfo($info, $type);
            return $info;
        }

        // Scope-level pairs. readScope() returns per-bit hashes for
        // matrix and bools for boolean, matching the shape that
        // Horde_Perms_Permission::updatePermissions() expects.
        [$info['default'], $info['default_deny']] = $this->readScope('default', $type);
        [$info['creator'], $info['creator_deny']] = $this->readScope('creator', $type);

        // Guest is grant-only.
        $info['guest'] = $this->readScopeGrantOnly('guest', $type);

        // Per-name pairs for users and groups. readPerName() collects
        // the tri-state values across all users / groups submitted and
        // returns two hashes keyed by name.
        [$info['u'], $info['u_deny']] = $this->readPerName('u', $type);
        [$info['g'], $info['g_deny']] = $this->readPerName('g', $type);

        // Handle the "new user / new group" row.
        $newUser = $this->readNewName('u_new');
        if ($newUser !== null) {
            [$grant, $deny] = $newUser['value'];
            if ($this->hasSetBit($grant, $type)) {
                $info['u'][$newUser['name']] = $grant;
            }
            if ($this->hasSetBit($deny, $type)) {
                $info['u_deny'][$newUser['name']] = $deny;
            }
        }
        $newGroup = $this->readNewName('g_new');
        if ($newGroup !== null) {
            [$grant, $deny] = $newGroup['value'];
            if ($this->hasSetBit($grant, $type)) {
                $info['g'][$newGroup['name']] = $grant;
            }
            if ($this->hasSetBit($deny, $type)) {
                $info['g_deny'][$newGroup['name']] = $deny;
            }
        }

        return $info;
    }

    /**
     * Builds the $info hash for non-matrix, non-boolean permission
     * types (currently 'int' and any custom scalar shape).
     *
     * These types do not participate in the deny cascade because
     * denying a numeric value is not a defined operation. The data
     * layer raises HordeLogicException on any numeric deny write.
     * The form emits plain scalar inputs per scope and per name,
     * and this method collects them into the shape
     * updatePermissions() expects for those keys: scalar for
     * default/guest/creator, name => scalar for u and g.
     */
    private function buildNumericInfo(array $info, string $type): array
    {
        $info['default'] = $this->readNumericScope('default');
        $info['guest'] = $this->readNumericScope('guest');
        $info['creator'] = $this->readNumericScope('creator');

        $info['u'] = $this->readNumericPerName('u');
        $info['g'] = $this->readNumericPerName('g');

        $newUser = $this->readNumericNewName('u_new');
        if ($newUser !== null) {
            $info['u'][$newUser['name']] = $newUser['value'];
        }
        $newGroup = $this->readNumericNewName('g_new');
        if ($newGroup !== null) {
            $info['g'][$newGroup['name']] = $newGroup['value'];
        }

        return $info;
    }

    /**
     * Reads one numeric scope's plain value. Empty string is treated
     * as "no rule at this scope" (null return) so updatePermissions()
     * can unset the entry.
     */
    private function readNumericScope(string $scope): mixed
    {
        $raw = $this->readVar($scope);
        if ($raw === null || $raw === '' || is_array($raw)) {
            return null;
        }
        return $raw;
    }

    /**
     * Reads the per-name numeric hash for 'u' or 'g'. Only entries
     * with non-empty values are kept so the resulting hash reflects
     * user intent.
     */
    private function readNumericPerName(string $scope): array
    {
        $raw = $this->readVar($scope);
        $out = [];
        if (!is_array($raw)) {
            return $out;
        }
        foreach ($raw as $name => $value) {
            if ($value === null || $value === '' || is_array($value)) {
                continue;
            }
            $out[$name] = $value;
        }
        return $out;
    }

    /**
     * Reads a "new user / new group" numeric row. Returns null when
     * name or value is empty.
     */
    private function readNumericNewName(string $scope): ?array
    {
        $raw = $this->readVar($scope);
        if (!is_array($raw) || empty($raw['name'])) {
            return null;
        }
        $value = $raw['value'] ?? '';
        if ($value === null || $value === '' || is_array($value)) {
            return null;
        }
        return [
            'name' => (string) $raw['name'],
            'value' => $value,
        ];
    }

    public function setupDeleteForm(Horde_Perms_Permission $permission): void
    {
        $this->formMode = 'delete';
        $this->formData = [
            'permission' => $permission,
            'perm_id' => $this->perms->getPermissionId($permission),
            'title' => $this->corePerms->getTitle($permission->getName()),
        ];
    }

    public function validateDeleteForm(array &$info)
    {
        if (!$this->isSubmission()) {
            return null;
        }
        $submit = $this->readVar('submitbutton');
        if ($submit === null || $submit === '') {
            return null;
        }
        // The confirm button posts the translated "Delete" label.
        // Anything else (cancel, empty) is treated as "not confirmed".
        // Matching on the translated string mirrors the legacy path,
        // which built two horde-delete / horde-cancel submit inputs
        // with translated values and compared to _("Delete").
        if ($submit === _("Delete")) {
            $info['perm_id'] = $this->readVar('perm_id');
            return $info;
        }
        return false;
    }

    public function renderForm(string $form_script = 'edit.php'): void
    {
        if ($this->formMode === null) {
            return;
        }
        $view = $this->buildView();
        foreach ($this->formData as $k => $v) {
            $view->{$k} = $v;
        }
        $view->action = $form_script;
        $view->formToken = $this->formToken();
        echo $view->render($this->formMode);
    }

    // ---------- Internal helpers ----------

    /**
     * Instantiates a Horde_View bound to the perms template directory.
     * The path is injected at construction time by the factory.
     */
    private function buildView(): Horde_View
    {
        $view = new Horde_View(['templatePath' => $this->templatePath]);
        $view->addHelper('Tag');
        $view->addHelper('Text');
        return $view;
    }

    /**
     * Produces the CSRF form token expected by the modern perms form.
     * The token is generated by the injected Horde\Token\Token service
     * with a scope-specific seed so it is not interchangeable with
     * tokens minted by other admin forms.
     */
    private function formToken(): string
    {
        return $this->tokens->generate('perms')->token;
    }

    /**
     * Reads a top-level variable from the current HTTP request.
     *
     * Sources, in order:
     *   1. Parsed POST body from the injected ServerRequestInterface.
     *   2. Query params from the injected ServerRequestInterface.
     *   3. The legacy Horde_Variables container passed through
     *      setVars(), retained so callers running under bootstraps
     *      that populate Variables but leave the request skeletal
     *      still work.
     *
     * Modern code should not add new callers of setVars(). Every
     * value the UI needs at submit time is available on the
     * ServerRequest that the factory already wires into the
     * constructor.
     */
    private function readVar(string $name, mixed $default = null): mixed
    {
        $body = $this->request->getParsedBody();
        if (is_array($body) && array_key_exists($name, $body)) {
            return $body[$name];
        }
        $query = $this->request->getQueryParams();
        if (array_key_exists($name, $query)) {
            return $query[$name];
        }
        if ($this->vars !== null) {
            $v = $this->vars->get($name);
            if ($v !== null) {
                return $v;
            }
        }
        return $default;
    }

    /**
     * Returns true when the current HTTP request is a form submission
     * that this Ui should act on. Renders on GET, validates on POST
     * once the CSRF token has been checked.
     *
     * Reading through the injected ServerRequestInterface keeps the
     * class free of $_SERVER / $_POST reads and matches the modern
     * Horde bootstrap that wires the request into the injector. The
     * token check goes through the injected Horde\Token\Token service
     * with the same 'perms' seed that formToken() uses on render.
     */
    private function isSubmission(): bool
    {
        if (strtoupper($this->request->getMethod()) !== 'POST') {
            return false;
        }
        $submittedToken = (string) $this->readVar('horde_form_token', '');
        if ($submittedToken === '') {
            $this->notifyBadToken();
            return false;
        }
        if (!$this->tokens->isValid($submittedToken, 'perms')) {
            $this->notifyBadToken();
            return false;
        }
        return true;
    }

    /**
     * Surfaces a "your submission was rejected" notice when a POST
     * arrives without a valid CSRF token. Silent rejection on the
     * server side would leave admins staring at an unchanged form
     * with no explanation. Called from isSubmission() on the two
     * bad-token branches.
     *
     * Notification is optional: when the caller did not inject a
     * handler the check still fails safely, just without a message.
     */
    private function notifyBadToken(): void
    {
        if ($this->notification === null) {
            return;
        }
        $this->notification->push(
            _("Your submission could not be verified. The form security token was missing or has expired. Please reload the page and try again."),
            'horde.error'
        );
    }

    /**
     * Reads one scope's tri-state radios (grant/neutral/deny) and
     * returns [grantValue, denyValue] in the shape that
     * Horde_Perms_Permission::updatePermissions() expects.
     *
     * For matrix type each value is a per-bit hash such as
     * `[SHOW => true, READ => false, EDIT => true, DELETE => false]`.
     * For boolean type each value is a bool.
     */
    private function readScope(string $scope, string $type): array
    {
        $raw = $this->readVar($scope);
        if (!is_array($raw)) {
            return $type === 'matrix'
                ? [$this->emptyBitHash(), $this->emptyBitHash()]
                : [false, false];
        }
        return $this->foldTriState($raw, $type);
    }

    private function readScopeGrantOnly(string $scope, string $type): mixed
    {
        $raw = $this->readVar($scope);
        if (!is_array($raw)) {
            return $type === 'matrix' ? $this->emptyBitHash() : false;
        }
        [$grant, $_deny] = $this->foldTriState($raw, $type);
        return $grant;
    }

    /**
     * Reads the per-name tri-state matrix for 'u' or 'g'. Returns
     * [grants, denies] where each is a name => (per-bit hash for
     * matrix / bool for boolean) map.
     */
    private function readPerName(string $scope, string $type): array
    {
        $raw = $this->readVar($scope);
        $grants = [];
        $denies = [];
        if (!is_array($raw)) {
            return [$grants, $denies];
        }
        foreach ($raw as $name => $perBit) {
            if (!is_array($perBit)) {
                continue;
            }
            [$g, $d] = $this->foldTriState($perBit, $type);
            if ($this->hasSetBit($g, $type)) {
                $grants[$name] = $g;
            }
            if ($this->hasSetBit($d, $type)) {
                $denies[$name] = $d;
            }
        }
        return [$grants, $denies];
    }

    /**
     * Reads a "new user" or "new group" tri-state row. Returns null
     * when the name field is empty. Otherwise returns
     * ['name' => ..., 'value' => [grantValue, denyValue]] in the
     * same shape readPerName() produces per-entry.
     */
    private function readNewName(string $scope): ?array
    {
        $raw = $this->readVar($scope);
        if (!is_array($raw) || empty($raw['name'])) {
            return null;
        }
        $name = (string) $raw['name'];
        $perBit = is_array($raw['value'] ?? null) ? $raw['value'] : [];
        $type = (string) $this->readVar('perm_type', 'matrix');
        return [
            'name' => $name,
            'value' => $this->foldTriState($perBit, $type),
        ];
    }

    /**
     * Collapses a bit => 'grant'|'neutral'|'deny' hash from the form
     * into a [grantValue, denyValue] pair.
     *
     * Matrix type returns a per-bit hash `[bit => true|false, ...]`
     * for each of grant and deny. That's the shape
     * Horde_Perms_Permission::updatePermissions() expects for the
     * 'default' / 'creator' / 'guest' / 'u' / 'g' keys and their
     * '_deny' companions. All bits from Horde_Perms::getPermsArray()
     * are present so bits the user cleared to 'neutral' show up as
     * false and get unset by updatePermissions().
     *
     * Boolean type collapses to two booleans. Only the SHOW slot is
     * inspected because the template renders one radio group per
     * boolean permission and stores its verdict under the SHOW key,
     * so matrix and boolean read the same input shape.
     */
    private function foldTriState(array $perBit, string $type): array
    {
        if ($type === 'boolean') {
            $verdict = $perBit[Horde_Perms::SHOW] ?? ($perBit[0] ?? 'neutral');
            return [$verdict === 'grant', $verdict === 'deny'];
        }

        $grant = $this->emptyBitHash();
        $deny = $this->emptyBitHash();
        foreach ($perBit as $bit => $verdict) {
            $bit = (int) $bit;
            if (!isset($grant[$bit])) {
                continue;
            }
            if ($verdict === 'grant') {
                $grant[$bit] = true;
            } elseif ($verdict === 'deny') {
                $deny[$bit] = true;
            }
        }
        return [$grant, $deny];
    }

    /**
     * Returns a bit => false hash covering every permission bit in
     * Horde_Perms::getPermsArray(). Serves as the neutral starting
     * point for foldTriState() so every bit shows up explicitly and
     * updatePermissions() can call unsetPerm() on the ones the admin
     * left neutral.
     */
    private function emptyBitHash(): array
    {
        $out = [];
        foreach (Horde_Perms::getPermsArray() as $bit => $_label) {
            $out[$bit] = false;
        }
        return $out;
    }

    /**
     * Checks whether a fold result contains any set bit. Used by
     * readPerName() to decide whether to emit an entry at all so
     * empty rows do not clutter the output hash.
     */
    private function hasSetBit(mixed $value, string $type): bool
    {
        if ($type !== 'matrix') {
            return (bool) $value;
        }
        if (!is_array($value)) {
            return false;
        }
        foreach ($value as $set) {
            if ($set) {
                return true;
            }
        }
        return false;
    }

    /**
     * Best-effort user list for the assignment picker. Returns an
     * empty array when no auth driver was injected or the driver
     * lacks list capability.
     */
    private function fetchUserList(): array
    {
        if ($this->auth === null) {
            return [];
        }
        if (!$this->auth->hasCapability('list')) {
            return [];
        }
        try {
            return $this->auth->listNames();
        } catch (\Throwable $e) {
            return [];
        }
    }

    private function fetchGroupList(): array
    {
        if ($this->groups === null) {
            return [];
        }
        try {
            return $this->groups->listAll();
        } catch (\Throwable $e) {
            return [];
        }
    }

    private function newAssignableUsers(array $userList, array $existing): array
    {
        $out = [];
        foreach ($userList as $uid => $label) {
            if (!isset($existing[$uid])) {
                $out[$uid] = $label;
            }
        }
        return $out;
    }

    private function newAssignableGroups(array $groupList, array $existing): array
    {
        $out = [];
        foreach ($groupList as $gid => $label) {
            if (!isset($existing[$gid])) {
                $out[$gid] = $label;
            }
        }
        return $out;
    }

    /**
     * Existing child permission names, used to exclude them from the
     * add-child dropdown. Mirrors what the legacy Ui does inline.
     */
    private function existingChildren(Horde_Perms_Permission $permission): array
    {
        $out = [];
        $prefix = $permission->getName() . ':';
        $length = strlen($prefix);
        try {
            $tree = $this->perms->getTree();
        } catch (Horde_Perms_Exception $e) {
            return $out;
        }
        foreach ($tree as $name) {
            if (str_starts_with((string) $name, $prefix)) {
                $out[] = substr((string) $name, $length);
            }
        }
        return $out;
    }
}
