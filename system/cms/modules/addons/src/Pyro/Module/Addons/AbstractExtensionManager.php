<?php namespace Pyro\Module\Addons;

use Composer\Autoload\ClassLoader;
use Illuminate\Support\Str;

/**
 * PyroStreams Core Field Extension Library
 *
 * @package		PyroCMS\Core\Modules\Addons
 * @author		Parse19
 * @copyright	Copyright (c) 2011 - 2012, Parse19
 * @license		http://parse19.com/pyrostreams/docs/license
 * @link		http://parse19.com/pyrostreams
 */
abstract class AbstractExtensionManager
{
	/**
	 * The module we're loading addons in regards to
	 * @var string
	 */
	protected static $module = array();

	/**
	 * The slug of the addon extension being worked with
	 * @var string
	 */
	protected static $slug = array();

	/**
	 * Places where our extensions may be
	 *
	 * @var		array
	 */
	protected static $addonPaths = array();

	/**
	 * Modules where dashboards may be
	 *
	 * @var		array
	 */
	protected static $modulePaths = array();

	/**
	 * Core addon path
	 * @var [extension]
	 */
	protected static $coreAddonPath;

	/**
	 * Available extensions
	 * @var array
	 */
	protected static $extensions = array();

	/**
	 * The registry of extensions
	 * @var array
	 */
	protected static $extensionClasses = array();

	/**
	 * Has the classes being initiated
	 * @var arry
	 */
	protected static $initiated = array();

	/**
	 * Get instance (singleton)
	 * @return [extension] [description]
	 */
	public static function init($module, $slug, $preload = false)
	{
		if (! isset(static::$initiated[get_called_class()])) {
			ci()->load->helper('directory');
			ci()->load->language($module.'/'.$module);

			// Get Lang (full name for language file)
			// This defaults to english.
			$langs = ci()->config->item('supported_languages');

			// Set the module and slug
			static::$module[get_called_class()] = $module;
			static::$slug[get_called_class()] = $slug;

			// Needed for installer
			if ( ! class_exists('Settings')) {
				ci()->load->library('settings/Settings');
			}

			// Set our addon paths
			static::$addonPaths[get_called_class()] = array(
				'addon' 		=> ADDONPATH.'extensions/'.$module.'/'.$slug.'/',
				'addon_alt' 	=> SHARED_ADDONPATH.'extensions/'.$module.'/'.$slug.'/',
			);

			// Set module paths
			$modules = new ModuleManager;

			foreach ($modules->getAllEnabled() as $enabledModule) {
                if (is_dir($enabledModule['path'].'/extensions/'.$module.'/'.$slug.'/')) {
                    static::$modulePaths[get_called_class()][$enabledModule['slug']] = $enabledModule['path'].'/extensions/'.$module.'/'.$slug.'/';
                }
            }

			// Preload?
			if ($preload) {
                self::preload();
            }
		}

		static::$initiated[get_called_class()] = true;
	}

	/**
	 * Set addon path
	 * @param string $key  
	 * @param string $path 
	 */
	public static function setAddonPath($key, $path)
	{
		static::$addonPaths[get_called_class()][$key] = $path;
	}

	/**
	 * Set module path
	 * @param string $key  
	 * @param string $path 
	 */
	public static function setModulePath($key, $path)
	{
		static::$modulePaths[get_called_class()][$key] = $path;
	}

	/**
	 * Get addon paths
	 * @return array
	 */
	public static function getAddonPaths()
	{
		return static::$addonPaths[get_called_class()];
	}

	/**
	 * Get module paths
	 * @return array
	 */
	public static function getModulePaths()
	{
		return static::$modulePaths[get_called_class()];
	}

	/**
	 * Get extension
	 * @param  string  $extension         
	 * @param  boolean $gather_extensions 
	 * @return object
	 */
	public static function getExtension($extension = null)
	{
        if (! empty(static::$extensions[get_called_class()][$extension]) and is_object(static::$extensions[get_called_class()][$extension])) {
            return static::$extensions[get_called_class()][$extension];
        } else {
            return static::loadExtension($extension);
        }
	}

	/**
	 * Register slug class
	 * @param  array
	 * @return void
	 */
	public static function registerSlugClass($extensions = array())
	{
		if (is_string($extensions)) {
			$extensions = array($extensions);
		}

		if (is_array($extensions)) {
			foreach ($extensions as $extension) {
				static::$extensionClasses[get_called_class()][$extension] = static::getClass($extension);
			}
		}
	}

	/**
	 * Register folder extensions
	 * @param  string  $folder
	 * @param  array   $extensions
	 * @param  boolean $preload
	 * @return void
	 */
	public static function registerFolderExtensions($folder, $extensions = array(), $preload = false)
	{
		static::init(static::$module[get_called_class()], static::$slug[get_called_class()]);

		if (is_string($extensions)) {
			$extensions = array($extensions);
		}

		if ($extensions === true) {
			$extensions = directory_map($folder, 1);
		}

		if (is_array($extensions) and ! empty($extensions)) {

			$loader = new ClassLoader;

			foreach ($extensions as $key => &$extension) {

				$extension = basename($extension);

				if ($extension == 'index.html') {
					unset($extensions[$key]);

					continue;
				}

				static::registerSlugClass($extension);

				$loader->add(static::getClass($extension), $folder.$extension.'/src/');
			}

			$loader->register();

			if ($preload) {
				foreach ($extensions as $preload_extension) {
					static::getExtension($preload_extension);
				}
			}
		}
	}

	/**
	 * Register addon extensions
	 * @param  boolean $preload
	 * @return void
	 */
	public static function registerExtensions($preload = false)
	{
		foreach (static::getAddonPaths() as $key => $path) {
			static::registerFolderExtensions($path, true, $preload);
		}
	}

	/**
	 * Register module extensions
	 * @param  boolean $preload
	 * @return void
	 */
	public static function registerModuleExtensions($preload = false)
	{
		foreach (static::getModulePaths() as $key => $path) {
			static::registerFolderExtensions($path, true, $preload);
		}
	}

	/**
	 * Get class
	 * @param  string $extension
	 * @return string
	 */
	public static function getClass($extension)
	{
		return 'Pyro\\Extension\\'.Str::studly(static::$module[get_called_class()]).'\\'.Str::studly(static::$slug[get_called_class()]).'\\'.Str::studly($extension);
	}

	/**
	 * Get classes
	 * @return array
	 */
	public static function getClasses()
	{
		return static::$extensionClasses[get_called_class()];
	}

	/**
	 * Get all extensions
	 * @return array 
	 */
	public static function getAllExtensions()
	{
		static::preload();

		return new \Pyro\Module\Addons\ExtensionCollection(static::$extensions[get_called_class()]);
	}

	/**
	 * Get registered extensions
	 * @return array 
	 */
	public static function getRegisteredExtensions()
	{
		return new \Pyro\Module\Addons\ExtensionCollection(static::$extensions[get_called_class()]);
	}

	/**
	 * Get the extensions together as a big object
	 *
	 * @return	void
	 */
	public static function preload()
	{
		static::registerFolderExtensions(static::$coreAddonPath.'extensions/', true, true);

		static::registerExtensions(true);

		static::registerModuleExtensions(true);
	}

	/**
	 * Load the actual extension into the
	 * extensions object
	 *
	 * @param	string - addon path
	 * @param	string - path to the file (with the file name)
	 * @param	string - the extension
	 * @param	string - mode
	 * @return	obj - the extension obj
	 */
	// $path, $file, $extension, $mode
	private static function loadExtension($extension)
	{
		if (empty($extension) or empty(static::$extensionClasses[get_called_class()][$extension])) return null;

		$class = static::getClass($extension);

		$instance = new $class;

        $type = static::$slug[get_called_class()];

		$reflection = new \ReflectionClass($instance);

		// Field Extension class folder location
		$class_path = dirname($reflection->getFileName());

		// The root path of the extension
		$path = str_replace(FCPATH, '', dirname(dirname(dirname(dirname(dirname($class_path))))));

		// Set asset paths
		$instance->path = $path;
        $instance->path_views = $path.'/views/';
        $instance->path_img = $path.'/img/';
		$instance->path_css = $path.'/css/';
		$instance->path_js = $path.'/js/';

		// -------------------------
		// Load the language file
		// -------------------------
        if (is_dir($path) and is_dir($path.'/language')) {
            
			$lang = ci()->config->item('language');

			// Fallback on English.
			if ( ! $lang) {
				$lang = 'english';
			}

			if ( ! is_dir($path.$lang)) {
				$lang = 'english';
			}

			ci()->lang->load($extension.'_'.$type.'_lang', $lang, false, false, $path.'/');

			unset($lang);
		}

		// Extension name is languagized
		if ( ! isset($instance->name)) {
			$instance->name = lang_label('lang:'.$extension.':'.$type.'.name');
		}

        // Extension name (plural) is languagized
        if ( ! isset($instance->plural)) {
            $instance->plural = lang_label('lang:'.$extension.':'.$type.'.plural');
        }

		// Extension description is languagized
		if ( ! isset($instance->description)) {
			$instance->description = lang_label('lang:'.$extension.':'.$type.'.description');
		}

		if (isset(ci()->profiler)) {
			ci()->profiler->log->info($class.' loaded');
		}

        // Set the extension type
        // This is helpful when same name extensions
        // have different purposes and therefore are a 
        // different extension type
        // Example: 
        //  - contact (extension) information (type)
        //  - contact (extension) form (type)
        $instance->type = $type;

        // We're loaded
        $instance->loaded();

		return static::$extensions[get_called_class()][$extension] = $instance;
	}
}