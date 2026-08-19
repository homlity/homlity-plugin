<?php

declare(strict_types=1);

/**
 * Stubs mínimos del store de Action Scheduler.
 *
 * Reproducen únicamente lo que el reporter necesita para resolver el propietario
 * de una acción fallida: fetch_action($id) → get_hook() / get_group().
 * El estado vive en WpStubs::$scheduledActions.
 */

use Homlity\PluginInmobiliario\Tests\Support\WpStubs;

if (!class_exists('ActionScheduler_Action')) {
    class ActionScheduler_Action
    {
        public function __construct(private string $hook = '', private string $group = '') {}

        public function get_hook(): string
        {
            return $this->hook;
        }

        public function get_group(): string
        {
            return $this->group;
        }
    }
}

if (!class_exists('ActionScheduler_NullAction')) {
    /** Acción inexistente: Action Scheduler devuelve un hook vacío. */
    class ActionScheduler_NullAction extends ActionScheduler_Action
    {
        public function __construct()
        {
            parent::__construct('', '');
        }
    }
}

if (!class_exists('ActionScheduler_Store')) {
    class ActionScheduler_Store
    {
        public function fetch_action(int $actionId): ActionScheduler_Action
        {
            $action = WpStubs::$scheduledActions[$actionId] ?? null;
            if ($action === null) {
                return new ActionScheduler_NullAction();
            }

            return new ActionScheduler_Action($action['hook'], $action['group']);
        }
    }
}

if (!class_exists('ActionScheduler')) {
    class ActionScheduler
    {
        public static function store(): ActionScheduler_Store
        {
            return new ActionScheduler_Store();
        }
    }
}
