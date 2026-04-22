<?php

declare(strict_types=1);

/**
 * Copyright 2026 The Horde Project (http://www.horde.org/)
 *
 * See the enclosed file LICENSE for license information (LGPL). If you
 * did not receive this file, see http://www.horde.org/licenses/lgpl21.
 *
 * @category  Horde
 * @copyright 2026 The Horde Project
 * @license   http://www.horde.org/licenses/lgpl21 LGPL 2.1
 * @package   Core
 */

namespace Horde\Core\Topbar;

use Horde\Tree\Node;
use Horde\Tree\TreeBuilder;
use Horde_Tree_Renderer_Base;
use Horde_Tree;

/**
 * Bridges legacy topbarCreate() calls to the modern Horde\Tree API.
 *
 * Legacy apps call topbarCreate(Horde_Tree_Renderer_Base $tree, $parent, $params)
 * and use $tree->addNode(array $node). This adapter extends the legacy renderer
 * so apps see a compatible type, but internally collects nodes into a modern
 * TreeBuilder.
 *
 * @category  Horde
 * @copyright 2026 The Horde Project
 * @license   http://www.horde.org/licenses/lgpl21 LGPL 2.1
 * @package   Core
 */
class LegacyTreeAdapter extends Horde_Tree_Renderer_Base
{
    private TreeBuilder $builder;

    public function __construct()
    {
        $tree = new Horde_Tree('topbar_adapter', []);
        parent::__construct($tree, []);
        $this->builder = new TreeBuilder('topbar_adapter');
    }

    public function addNode($node): void
    {
        $id = (string) $node['id'];
        $label = $node['label'] ?? '';
        $parentId = !empty($node['parent']) ? (string) $node['parent'] : null;
        $expanded = $node['expanded'] ?? true;
        $params = $node['params'] ?? [];

        $this->builder->addNode(new Node(
            id: $id,
            label: $label,
            parentId: $parentId,
            expanded: $expanded,
            params: $params,
        ));
    }

    public function getCollectedNodes(): array
    {
        $tree = $this->builder->build();
        return $tree->getNodes();
    }
}
