<?php
namespace App\Http\Controllers\Admin;

use App\Http\Requests;
use App\Http\Controllers\Admin\AdminController;
use Illuminate\Http\Request;
use Caffeinated\Modules\Modules;
use Artisan;

class ModulesController extends AdminController
{

	public function __construct(Modules $module)
	{
		$this->module = $module;
	}

    /**
	 * Get all modules.
	 *
	 * @return array
	 */
	protected function getModules(Request $request)
	{
		$type = $request->input('type');
		
		if($type == 'enabled')	
			$modules = $this->module->enabled();
		elseif($type == 'disabled')
			$modules = $this->module->disabled();
		else
			$modules = $this->module->all();
		
		$results = [];

		foreach ($modules as $module) {
			$results[] = $this->getModuleInformation($module);
		}

		//return array_filter($results);
		return response()->json($results);
	}

	public function getModuleDetails(Request $request)
	{
		$slug = $request->input('slug');

		$module = $this->module->getProperties($slug);

		return response()->json($module);
	}

	/**
	 * Returns module manifest information.
	 *
	 * @param string  $module
	 * @return array
	 */
	protected function getModuleInformation($module)
	{
		return [
			'name'        => $module['name'],
			'slug'        => $module['slug'],
			'description' => $module['description'],
			'status'      => ($this->module->isEnabled($module['slug'])) ? 'Enabled' : 'Disabled'
		];
	}

	public function enableModule(Request $request)
	{
		$slug = $request->input('slug');

		Artisan::call('module:enable', [
	        'slug' => $slug
	    ]);

		$response['success'] = true; 
		$response['module'] = $this->module->getProperties($slug);
	    return response()->json($response);
	}

	public function disableModule(Request $request)
	{
		$slug = $request->input('slug');

		Artisan::call('module:disable', [
	        'slug' => $slug
	    ]);

		$response['success'] = true; 
		$response['module'] = $this->module->getProperties($slug);
	    return response()->json($response);
	}
}
