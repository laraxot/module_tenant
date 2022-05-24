<?php

declare(strict_types=1);

namespace Modules\Tenant\Services;

use Exception;
// use Illuminate\Support\Facades\Storage;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
use Modules\Xot\Services\FileService;
use Modules\Xot\Services\PanelService;
use Nwidart\Modules\Facades\Module;

/**
 * Class TenantService.
 */
class TenantService {
    // public static $panel;

    /*
    public function __construct(Panel $panel) {
        static::$panel = $panel;
    }
    */

    /**
     * Undocumented function.
     */
    public static function getName(array $params = []): string {
        $default = env('APP_URL');
        if (! \is_string($default)) {
            // throw new Exception('['.$default.']['.__LINE__.']['.class_basename(__CLASS__).']');
            $default = 'localhost';
        }
        $default = Str::after($default, '//');

        $server_name = $default;
        if (isset($_SERVER['SERVER_NAME']) && '127.0.0.1' !== $_SERVER['SERVER_NAME']) {
            $server_name = $_SERVER['HTTP_HOST'] ?? $_SERVER['SERVER_NAME'];
        }

        $server_name = Str::replace('www.', '', $server_name);

        // die('<pre>'.print_r($_SERVER,true).'</pre>');

        $tmp = collect(explode('.', $server_name))
            ->map(
                function ($item) {
                    return Str::slug($item);
                }
            )->reverse()
            ->values();

        $config_file = config_path($tmp->implode(\DIRECTORY_SEPARATOR));

        if (file_exists($config_file)) {
            //    dd(['config_file' => $config_file, 'line' => __LINE__]);

            return $tmp->implode('/');
        }
        $config_file = config_path($tmp->slice(0, -1)->implode(\DIRECTORY_SEPARATOR));
        if (file_exists($config_file) && $tmp->count() > 2) {
            // dd(['config_file' => $config_file, 'tmp' => $tmp, 'line' => __LINE__]);

            return $tmp->slice(0, -1)->implode('/');
        }

        // default

        $default = str_replace('.', '-', $default);
        if (file_exists(base_path('config/'.$default)) && '' !== $default) {
            // dd(['default' => $default, 'line' => __LINE__,]);
            return $default;
        }

        // dd(['localhost' => 'localhost','line' => __LINE__,]);
        return 'localhost';
    }

    // end function

    /**
     * Undocumented function.
     */
    public static function filePath(string $filename): string {
        $path = base_path('config/'.self::getName().'/'.$filename);
        $path = str_replace(['/', '\\'], [\DIRECTORY_SEPARATOR, \DIRECTORY_SEPARATOR], $path);

        return $path;
    }

    // end function

    /**
     * tenant config.
     * ret_old \Illuminate\Config\Repository|\Illuminate\Contracts\Foundation\Application|mixed.
     * ret_old1 \Illuminate\Config\Repository|mixed.
     *
     * @param string|int|array|null $default
     *
     * @return string|int|array|float|null
     */
    public static function config(string $key, $default = null) {
        if (inAdmin() && Str::startsWith($key, 'morph_map') && null !== (\Request::segment(2))) {
            $module_name = \Request::segment(2);
            $models = getModuleModels($module_name);
            $original_conf = config('morph_map');
            if (! \is_array($original_conf)) {
                $original_conf = [];
            }
            $path = self::filePath('morph_map.php');
            $tenant_conf = [];
            if (File::exists($path)) {
                $tenant_conf = File::getRequire($path);
            }
            $merge_conf = collect($models)
                ->merge($original_conf)
                ->merge($tenant_conf)
                ->all();
            Config::set('morph_map', $merge_conf);
            $res = config($key);

            return $res;
        }

        $group = collect(explode('.', $key))->first();

        $original_conf = config($group);
        $tenant_name = self::getName();

        $config_name = str_replace('/', '.', $tenant_name).'.'.$group;
        $extra_conf = config($config_name);

        if (! \is_array($original_conf)) {
            $original_conf = [];
        }

        if (! \is_array($extra_conf)) {
            $extra_conf = [];
        }

        // -- ogni modulo ha la sua connessione separata
        // -- replicazione liveuser con lu .. tenere lu anche in database
        if ('database' === $key) {
            $modules = Module::toCollection();
            foreach ($modules as $item) {
                $name = $item->getSnakeName();
                if (! isset($extra_conf['connections'][$name])) {
                    $extra_conf['connections'][$name] = $extra_conf['connections']['mysql'];
                }
            }
        }

        $merge_conf = collect($original_conf)->merge($extra_conf)->all();
        if (null === $group) {
            throw new Exception('['.__LINE__.']['.class_basename(__CLASS__).']');
        }

        Config::set($group, $merge_conf);

        $res = config($key);

        if (null === $res && null !== $default) {
            $index = Str::after($key, $group.'.');
            $data = Arr::set($extra_conf, $index, $default);
            /*
            dddx([
                'key' => $key,
                'group' => $group,
                'index' => $index,
                '$config_name' => $config_name,
                'data' => $data,
            ]);
            */
            throw new Exception('['.__LINE__.']['.class_basename(__CLASS__).']');
            self::saveConfig(['name' => $group, 'data' => $data]);

            return $default;
        }

        return $res;
    }

    public static function getConfigPath(string $key): string {
        $tenant_name = self::getName();
        $path = str_replace('/', '.', $tenant_name).'.'.$key;

        return $path;
    }

    public static function saveConfig(array $params): void {
        $name = 'xra';
        $data = [];
        extract($params);

        /*
        $tennant_name = self::getName();
        $config_name = $tennant_name.'.'.$name;
        $config_data = config($config_name);

        if (! is_array($config_data)) {
            $config_name = str_replace('/', '.', $config_name);
            $config_data = config($config_name);
        }
        if (! is_array($config_data)) {
            dddx([
                'config_name' => $config_name,
                'config_data' => $config_data,
                'params' => $params,
                'test' => config('ptvx-local'),
                'test1' => config_path('ptvx-local/morph_map.php'),
                'test1a' => self::filePath($name.'.php'),
                'test2' => File::getRequire(config_path('ptvx-local/morph_map.php')),
            ]);
        }
        */
        $path = self::filePath($name.'.php');
        $config_data = [];
        if (File::exists($path)) {
            $config_data = File::getRequire($path);
        }
        if (! \is_array($config_data)) {
            $config_data = [];
        }

        $config_data = array_merge_recursive_distinct($config_data, $data); // funzione in helper

        $config_data = Arr::sortRecursive($config_data);

        $path = self::filePath($name.'.php');
        $content = '<'.'?'.'php'.\chr(13).\chr(13).' return '.var_export($config_data, true).';';
        $content = str_replace('\\\\', '\\', $content);
        // dddx(['path' => $path, 'content' => $content]);
        File::put($path.'', $content);
    }

    /**
     * Undocumented function.
     */
    public static function modelClass(string $name): ?string {
        $name = Str::singular($name);
        $name = Str::snake($name);

        $class = self::config('morph_map.'.$name);

        if (null === $class) {
            $models = getAllModulesModels();
            if (! isset($models[$name])) {
                throw new Exception('model unknown ['.$name.']
                [line:'.__LINE__.']['.basename(__FILE__).']');
            }
            $class = $models[$name];
            $data = [];
            $data[$name] = $class;
            self::saveConfig(['name' => 'morph_map', 'data' => $data]);
        }
        // $model = app($class);
        if (! \is_string($class)) {
            if (\is_array($class)) {
                return $class[0];
            }
            dddx(
                [
                    'name' => $name,
                    'class' => $class,
                ]
            );
        }

        // 272    Method Modules\Tenant\Services\TenantService::model()
        // should return Illuminate\Database\Eloquent\Model
        // but returns object.
        // $model = new $class();
        if (! \is_string($class)) {
            throw new Exception('['.__LINE__.']['.class_basename(__CLASS__).']');
        }

        return $class;
    }

    /**
     * @throws \ReflectionException
     */
    public static function model(string $name): Model {
        $class = self::modelClass($name);
        $model = app($class);

        return $model;
    }

    /**
     * @throws \Illuminate\Contracts\Filesystem\FileNotFoundException
     * @throws \ReflectionException
     */
    public static function modelEager(string $name): \Illuminate\Database\Eloquent\Builder {
        $model = self::model($name);
        if (null === $model) {
            // return null;
            throw new \Exception('model is null');
        }
        $panel = PanelService::make()->get($model);
        if (null === $panel) {
            // return null;
            throw new \Exception('panel is null');
        }
        $with = $panel->with();
        // $model = $model->load($with);
        $model = $model->with($with);

        return $model;
    }

    /**
     * Find the path to a localized Markdown resource. copiata da jetstream.php.
     */
    public static function localizedMarkdownPath(string $name): ?string {
        $localName = preg_replace('#(\.md)$#i', '.'.app()->getLocale().'$1', $name);
        $lang = app()->getLocale();
        $paths = [
            self::filePath('lang/'.$lang.'/'.$name),
            self::filePath($name),
        ];

        $path = Arr::first(
            $paths,
            function ($path) {
                return file_exists($path);
            }
        );
        if (\is_string($path)) {
            return $path;
        }

        return null;
    }

    /**
     * @return array
     */
    public static function getConfigNames() {
        $name = self::getName(); //  local/ptvx

        $dir = config_path($name);
        $dir = FileService::fixPath($dir);
        $files = File::files($dir);

        $rows = collect($files)
            ->filter(
                function ($item) {
                    return 'php' === $item->getExtension();
                }
            )
            ->map(
                function ($item, $k) {
                    return [
                        'id' => $k + 1,
                        'name' => $item->getFilenameWithoutExtension(),
                    ];
                }
            )
            ->all();

        return $rows;
    }
}