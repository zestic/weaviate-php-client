<?php

namespace Weaviate;

use Weaviate\Collections\Collections;

/**
 * PHP interface mirroring the Python `WeaviateClient` class.
 *
 * This interface captures the public API of the synchronous client as
 * defined in `weaviate/client.py` (and the accompanying stub in
 * `weaviate/client.pyi`).  The intent is not to provide a working PHP
 * implementation, but rather to serve as a reference for client
 * behaviour when interacting with Weaviate from PHP code.
 */

interface WeaviateClientInterface
{
    /* connection helpers */
    public function connect(): void;
    public function close(): void;

    /* health checks */
    public function isConnected(): bool;
    public function isLive(): bool;
    public function isReady(): bool;

    /* low level query */
    // public function graphqlRawQuery(string $gqlQuery);

    /* metadata */
    // public function getMeta(): array;
    // public function getOpenIdConfiguration();

    /* namespaces / helpers, return types reference the concrete PHP classes. */
    // public function getAlias(): Alias;
    // public function getBackup(): Backup;
    // public function getBatch(): BatchClientWrapper;
    public function getCollections(): Collections;
    // public function getCluster(): Cluster;
    // public function getDebug(): Debug;
    // public function getGroups(): Groups;
    // public function getRoles(): Roles;
    // public function getUsers(): Users;
}
