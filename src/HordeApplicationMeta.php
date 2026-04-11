<?php

namespace Horde\Core;

class HordeApplicationMeta extends HordeLibraryMeta
{
    public function __construct(
        public readonly string $vendorDirPath
    ) {}
}
