<?php

declare(strict_types=1);

namespace Modules\Tenant\Providers;

use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Request;
use Illuminate\Support\Facades\Schema;
use Modules\Tenant\Services\TenantService;
use Modules\Xot\Providers\XotBaseServiceProvider;

use function is_array;
use function Safe\realpath;

class TenantServiceProvider extends XotBaseServiceProvider
{
    protected string $module_dir = __DIR__;

    protected string $module_ns = __NAMESPACE__;

    public string $module_name = 'tenant';

    public function bootCallback(): void
    {
        $this->mergeConfigs();

        if (Request::has('act') && 'migrate' === Request::input('act')) {
            DB::purge('mysql'); // Call to a member function prepare() on null
            DB::reconnect('mysql');
        }
        // DB::purge(); //Call to a member function prepare() on null
        // Database connection [mysql] not configured.
        DB::reconnect();
        Schema::defaultStringLength(191);

        $map = TenantService::config('morph_map');
        if (! is_array($map)) {
            $map = [];
        }

        Relation::morphMap($map);
    }

    public function mergeConfigs(): void
    {
        /*
        dddx([
            'base_path' => base_path(),
            'path1' => realpath(__DIR__ . '/../../../'),
            'run' => $this->app->runningUnitTests(),
            'run1' => $this->app->runningInConsole(),
        ]);
        */
        // if ($this->app->runningUnitTests()) {
        if (base_path() != realpath(__DIR__ . '/../../../')) {
            // $this->publishes([
            //    __DIR__ . '/../Config/xra.php' => config_path('xra.php'),
            // ], 'config');

            $name = TenantService::getName();
            File::makeDirectory(config_path($name), 0755, true, true);
            $this->mergeConfigFrom(__DIR__ . '/../Config/xra.php', 'xra');

            return;
        }

        $configs = TenantService::getConfigNames();

        foreach ($configs as $v) {
            $tmp = TenantService::config($v['name']);
        }
    }

    // end mergeConfigs

    public function registerCallback(): void
    {
    }
}
