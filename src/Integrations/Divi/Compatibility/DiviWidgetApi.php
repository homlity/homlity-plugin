<?php

declare(strict_types=1);

namespace Homlity\PluginInmobiliario\Integrations\Divi\Compatibility;

if (!defined('ABSPATH')) {
    exit;
}

if (!class_exists(Controls_Manager::class)) {
    final class Controls_Manager
    {
        public const TAB_CONTENT = 'content';
        public const TAB_STYLE = 'style';
        public const TEXT = 'text';
        public const TEXTAREA = 'textarea';
        public const NUMBER = 'number';
        public const SELECT = 'select';
        public const SELECT2 = 'select2';
        public const SWITCHER = 'switcher';
        public const COLOR = 'color';
        public const SLIDER = 'slider';
        public const DIMENSIONS = 'dimensions';
        public const MEDIA = 'media';
        public const URL = 'url';
        public const ICONS = 'icons';
        public const CHOOSE = 'choose';
        public const HEADING = 'heading';
        public const HIDDEN = 'hidden';
    }
}

if (!class_exists(Widget_Base::class)) {
    abstract class Widget_Base
    {
        private array $homlityControls = [];
        private array $homlitySettings = [];
        private string $homlitySection = '';
        private array $homlitySectionArgs = [];

        public function __construct(array $data = [], array $args = [])
        {
            $this->register_controls();
        }

        abstract public function get_name(): string;
        abstract public function get_title(): string;

        protected function register_controls(): void {}

        protected function start_controls_section(string $id, array $args = []): void
        {
            $this->homlitySection = $id;
            $this->homlitySectionArgs = $args;
        }

        protected function end_controls_section(): void
        {
            $this->homlitySection = '';
            $this->homlitySectionArgs = [];
        }

        protected function start_controls_tabs(string $id): void {}
        protected function end_controls_tabs(): void {}
        protected function start_controls_tab(string $id, array $args = []): void {}
        protected function end_controls_tab(): void {}

        protected function add_control(string $id, array $args = []): void
        {
            $args['section'] = $this->homlitySection;
            $args['section_label'] = (string) ($this->homlitySectionArgs['label'] ?? '');
            if (!isset($args['tab']) && isset($this->homlitySectionArgs['tab'])) {
                $args['tab'] = $this->homlitySectionArgs['tab'];
            }
            $this->homlityControls[$id] = $args;
        }

        protected function add_responsive_control(string $id, array $args = []): void
        {
            $args['responsive'] = true;
            $this->add_control($id, $args);
        }

        protected function add_group_control(string $type, array $args = []): void
        {
            $name = (string) ($args['name'] ?? sanitize_key($type));
            $args['type'] = 'homlity_group';
            $args['group_type'] = $type;
            $this->add_control($name, $args);
        }

        public function get_controls(): array
        {
            return $this->homlityControls;
        }

        public function get_settings_for_display(?string $key = null): mixed
        {
            $defaults = [];
            foreach ($this->homlityControls as $name => $control) {
                if (array_key_exists('default', $control)) {
                    $defaults[$name] = $control['default'];
                }
            }
            $settings = array_replace($defaults, $this->homlitySettings);
            return $key === null ? $settings : ($settings[$key] ?? null);
        }

        public function homlitySetSettings(array $settings): void
        {
            $this->homlitySettings = $settings;
        }

        public function homlityRender(): string
        {
            ob_start();
            $this->render();
            return (string) ob_get_clean();
        }

        protected function render(): void {}

        public function get_id(): string
        {
            return substr(md5(static::class), 0, 8);
        }
    }
}

if (!class_exists(Icons_Manager::class)) {
    final class Icons_Manager
    {
        public static function enqueue_shim(): void {}

        public static function render_icon(array $icon, array $attributes = []): void
        {
            if (class_exists('\\Homlity\\PluginInmobiliario\\Services\\IconRenderer')) {
                \Homlity\PluginInmobiliario\Services\IconRenderer::render($icon, $attributes);
                return;
            }

            $value = sanitize_html_class((string) ($icon['value'] ?? ''));
            if ($value === '') {
                return;
            }
            $attrs = '';
            foreach ($attributes as $name => $attributeValue) {
                $attrs .= ' ' . esc_attr((string) $name) . '="' . esc_attr((string) $attributeValue) . '"';
            }
            echo '<i class="' . esc_attr($value) . '"' . $attrs . '></i>';
        }
    }
}

if (!class_exists(GroupControlShim::class)) {
    abstract class GroupControlShim
    {
        abstract public static function get_type(): string;
    }
}

if (!class_exists(Group_Control_Typography::class)) {
    final class Group_Control_Typography extends GroupControlShim
    {
        public static function get_type(): string { return 'typography'; }
    }
}
if (!class_exists(Group_Control_Border::class)) {
    final class Group_Control_Border extends GroupControlShim
    {
        public static function get_type(): string { return 'border'; }
    }
}
if (!class_exists(Group_Control_Box_Shadow::class)) {
    final class Group_Control_Box_Shadow extends GroupControlShim
    {
        public static function get_type(): string { return 'box_shadow'; }
    }
}
if (!class_exists(Group_Control_Text_Shadow::class)) {
    final class Group_Control_Text_Shadow extends GroupControlShim
    {
        public static function get_type(): string { return 'text_shadow'; }
    }
}
if (!class_exists(Group_Control_Background::class)) {
    final class Group_Control_Background extends GroupControlShim
    {
        public static function get_type(): string { return 'background'; }
    }
}
