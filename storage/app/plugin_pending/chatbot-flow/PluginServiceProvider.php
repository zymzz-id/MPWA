<?php

namespace Plugins\ChatbotFlow;

use App\Plugins\BasePluginServiceProvider;

class PluginServiceProvider extends BasePluginServiceProvider
{
    protected string $pluginSlug = 'chatbot-flow';

    public function register(): void
    {
    }

    public function boot(): void
    {
        $this->loadPluginViews();
        $this->loadPluginMigrations();
        $this->loadPluginRoutes();
        $this->loadPluginTranslations();
    }

    public function getNavItems(): array
    {
        return [
            [
                'label' => 'Chatbot Flow',
                'route_name' => 'chatbot-flow',
                'icon' => 'tabler-git-branch',
                'route_pattern' => 'chatbot-flow*',
                'requires_device' => true,
                'admin_only' => false,
            ],
        ];
    }
}
