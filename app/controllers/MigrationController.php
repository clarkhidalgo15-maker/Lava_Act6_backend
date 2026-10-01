<?php
defined('PREVENT_DIRECT_ACCESS') OR exit('No direct script access allowed');

class MigrationController extends Controller
{
    private $migration;

    public function __construct()
    {
        parent::__construct();

        if (strtolower((string) config_item('environment')) !== 'development') {
            http_response_code(404);
            exit('Migration routes are available only in development.');
        }

        $this->migration = load_class('Migration', 'libraries');
    }

    public function create_migration($migration_class)
    {
        if (!preg_match('/^[a-z][a-z0-9_]*$/', $migration_class)) {
            http_response_code(400);
            echo 'Invalid migration name.';
            return;
        }

        $this->migration->create_migration($migration_class);
    }

    public function migrate()
    {
        $this->migration->migrate();
    }

    public function rollback()
    {
        $this->migration->rollback();
    }

    public function rollback_all()
    {
        $this->migration->rollback_all();
    }

    public function refresh()
    {
        $this->migration->refresh();
    }

    public function status()
    {
        $this->migration->status();
    }
}