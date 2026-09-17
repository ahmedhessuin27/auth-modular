<?php

namespace App\Modules\Shared\Application\Messaging\Contracts;

use App\Modules\Shared\Application\Messaging\QueueTopology;

interface MessageTopologyInterface
{
    public function setup(QueueTopology ...$topologies): void;
}