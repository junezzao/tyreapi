<?php
namespace App\Repositories\Modules;

use Caffeinated\Modules\Repositories\Repository;
use DB;
use Carbon\Carbon;
use Illuminate\Support\Facades\Schema;

class CustomRepository extends Repository
{

	/**
	* Get all modules.
	*
	* @return Collection
	*/
	public function all()
	{
		$basenames = $this->getAllBasenames();
		$modules   = collect();

		$basenames->each(function($module, $key) use ($modules) {
			$modules->put($module, $this->getProperties($module));
		});
		
		
		//$tbl_modules = collect((array)DB::table('modules')->get());

		return $modules;
		//return $modules->sortBy('order');
		
		
	}

	/**
	* Get all module slugs.
	*
	* @return Collection
	*/
	public function slugs()
	{
		/*$slugs = collect();

		$this->all()->each(function($item, $key) use ($slugs) {
			$slugs->push($item['slug']);
		});
		*/
		$slugs = DB::table('modules')->select('slug')->get();
		return $slugs;
	}

	/**
	 * Get modules based on where clause.
	 *
	 * @param  string  $key
	 * @param  mixed   $value
	 * @return Collection
	 */
	public function where($key, $value)
	{
		$collection = DB::table('modules')->where($key, $value)->get();

		return $collection;
	}

	/**
	 * Sort modules by given key in ascending order.
	 *
	 * @param  string  $key
	 * @return Collection
	 */
	public function sortBy($key)
	{
		$collection = DB::table('modules')->orderBy($key, 'asc')->get();

		return $collection;
	}

	/**
	* Sort modules by given key in ascending order.
	*
	* @param  string  $key
	* @return Collection
	*/
	public function sortByDesc($key)
	{
		$collection = DB::table('modules')->orderBy($key, 'desc')->get();

		return $collection;
	}

	/**
	 * Determines if the given module exists.
	 *
	 * @param  string  $slug
	 * @return bool
	 */
	public function exists($slug)
	{
		$exist = DB::table('modules')->where('slug', $slug)->count();

		return ($exists > 0 ? true : false);
			
		//return $this->slugs()->contains(strtolower($slug));
	}

	/**
	 * Returns count of all modules.
	 *
	 * @return int
	 */
	public function count()
	{
		$count = DB::table('modules')->count();

		return $count;
	}

	/**
	 * Get a module's properties.
	 *
	 * @param  string $slug
	 * @return Collection|null
	 */
	public function getProperties($slug)
	{
		if (! is_null($slug)) {
			$module     = studly_case($slug);
			$path       = $this->getManifestPath($module);
			//$contents   = $this->files->get($path);
			$collection = DB::table('modules')->where('slug', '=', $module)->first();
			//$collection = collect(json_decode($contents, true));

			return (array)$collection;
		}

		return null;
	}

	/**
	 * Get a module property value.
	 *
	 * @param  string $property
	 * @param  mixed  $default
	 * @return mixed
	 */
	public function getProperty($property, $default = null)
	{
		list($module, $key) = explode('::', $property);

		return $this->getProperties($module)->get($key, $default);
	}

	/**
	* Set the given module property value.
	*
	* @param  string  $property
	* @param  mixed   $value
	* @return bool
	*/
	public function setProperty($property, $value)
	{
		list($module, $key) = explode('::', $property);

		$module  = strtolower($module);
		$content = $this->getProperties($module);

		if (isset($content[$key])) {
			unset($content[$key]);
		}

		$content[$key] = $value;
		//$content       = json_encode($content, JSON_PRETTY_PRINT);

		$this->files->put($this->getManifestPath($module), $content);
		return DB::table('modules')->update([ $content ]);
	}

	/**
	 * Get all enabled modules.
	 *
	 * @return Collection
	 */
	public function enabled()
	{
        $moduleCache = $this->getCache();
        
        $modules = $this->all()->map(function($item, $key) use ($moduleCache) {
        	$item = (array) $item;
            $item['enabled'] = $moduleCache->get($item['slug']);

            return $item;
        });

		return $modules->where('enabled', true);
	}

	/**
	 * Get all disabled modules.
	 *
	 * @return Collection
	 */
	public function disabled()
	{
		$moduleCache = $this->getCache();
        
        $modules = $this->all()->map(function($item, $key) use ($moduleCache) {
        	$item = (array) $item;
            $item['enabled'] = $moduleCache->get($item['slug']);

            return $item;
        });

		return $modules->where('enabled', false);
	}

	/**
	 * Check if specified module is enabled.
	 *
	 * @param  string $slug
	 * @return bool
	 */
	public function isEnabled($slug)
	{
        $status = DB::table('modules')->select('enabled')->where('slug', '=', $slug)->first();
        
        return ($status->enabled == 1 ? true : false);
	}

	/**
	 * Check if specified module is disabled.
	 *
	 * @param  string $slug
	 * @return bool
	 */
	public function isDisabled($slug)
	{
        $status = DB::table('modules')->select('enabled')->where('slug', '=', $slug)->first();

        return ($status->enabled == 0 ? true : false);
	}

	/**
	 * Enables the specified module.
	 *
	 * @param  string $slug
	 * @return bool
	 */
	public function enable($slug)
	{
		DB::table('modules')->where('slug', '=', $slug)->update(['enabled' => true, 'updated_at' => Carbon::now()]);
        return $this->setCache(strtolower($slug), true);
	}

	/**
	 * Disables the specified module.
	 *
	 * @param  string $slug
	 * @return bool
	 */
	public function disable($slug)
	{
		DB::table('modules')->where('slug', '=', $slug)->update(['enabled' => false, 'updated_at' => Carbon::now()]);
        return $this->setCache(strtolower($slug), false);
	}

    /**
     * Refresh the cache with any newly found modules.
     *
     * @return bool
     */
    public function cache()
    {
        $cacheFile = storage_path('app/modules.json');
        $cache     = $this->getCache();
        $modules   = $this->all();

        $collection = collect([]);

        foreach ($modules as $module) {
            $collection->put($module['slug'], true);
        }

        $keys    = $collection->keys()->toArray();
        $merged  = $collection->merge($cache)->only($keys);
        $content = json_encode($merged->all(), JSON_PRETTY_PRINT);

        return $this->files->put($cacheFile, $content);
    }

    /**
     * Get the contents of the cache file.
     *
     * The cache file lists all module slugs and their
     * enabled or disabled status. This can be used to
     * filter out modules depending on their status.
     *
     * @return Collection
     */
    public function getCache()
    {
        $cacheFile = storage_path('app/modules.json');

        if (! $this->files->exists($cacheFile)) {
            $modules = $this->all();
            $content = [];

            foreach ($modules as $module) {
                $content[$module['slug']] = true;
            }

            $content = json_encode($content, JSON_PRETTY_PRINT);

            $this->files->put($cacheFile, $content);

            return collect(json_decode($content, true));
        }

        return collect(json_decode($this->files->get($cacheFile), true));
    }

    /**
     * Set the given cache key value.
     *
     * @param  string  $key
     * @param  mixed  $value
     * @return int
     */
    public function setCache($key, $value)
    {
        $cacheFile = storage_path('app/modules.json');
        $content   = $this->getCache();

        $content->put($key, $value);

        $content = json_encode($content, JSON_PRETTY_PRINT);

        return $this->files->put($cacheFile, $content);
    }
}
