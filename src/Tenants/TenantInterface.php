<?php

namespace Weaviate\Tenants;

interface TenantInterface
{
    public function getName(): string;

    public function getActivityStatus(): TenantActivityStatus;
}
