<?php

require_once __DIR__ . '/../core/config/connection.php';

/** Immutable request context shared with a module adapter. */
class TheRainModuleContext
{
    private $tenantId;
    private $userId;
    private $branchId;
    private $connection;

    public function __construct($tenantId, $userId = null, $branchId = null, mysqli $connection = null)
    {
        $this->tenantId = (int) $tenantId;
        $this->userId = $userId === null ? null : (int) $userId;
        $this->branchId = $branchId === null ? null : (int) $branchId;
        $this->connection = $connection;
    }

    public function tenantId()
    {
        return $this->tenantId;
    }

    public function userId()
    {
        return $this->userId;
    }

    public function branchId()
    {
        return $this->branchId;
    }

    public function connection()
    {
        return $this->connection ?: therain_db();
    }
}
