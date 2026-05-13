<?php

declare(strict_types=1);

namespace FlexCoreDataImporter;

use FlexCore\Modules\Plugins\PluginInterface;
use FlexCore\Modules\Plugins\PluginManifest;

class Plugin implements PluginInterface
{
    public function manifest(): PluginManifest
    {
        return PluginManifest::fromJson(__DIR__ . '/plugin.json');
    }

    public function boot(): void
    {
    }

    public function uninstall(): void
    {
        \DB::run('DROP TABLE IF EXISTS importer_sessions');
    }
}
