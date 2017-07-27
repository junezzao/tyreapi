<?php 
namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use LucaDegasperi\OAuth2Server\Authorizer;
use Illuminate\Http\Response;
use App\Models\Admin\Category;
use App\Models\Admin\ContractRule;
use App\Models\Admin\ChannelContractRule;
use Bican\Roles\Models\Role;
use App\Repositories\Eloquent\CategoryRepository;
use Activity;
use Illuminate\Support\Collection;
use Log;
use DB;

class CategoryController extends AdminController
{
	protected $categoryRepo;
    protected $authorizer;
    protected $userRepo;
	
	public function __construct(CategoryRepository $categoryRepo, Authorizer $authorizer)
    {
        $this->middleware('oauth');
        $this->categoryRepo = $categoryRepo;
        $this->authorizer = $authorizer;
    }
	public function index()
	{
		$categories = $this->categoryRepo;
		$count = $categories->count();
		$categories = $categories
					// ->skip(request()->get('start', 0))
					// ->take(request()->get('limit', 50))
					->all();
		return response()->json([
			// 'start'  => intval(request()->get('start', 0)), 
            // 'limit'  => intval(request()->get('limit', 50)), 
            // 'total'  => $count,
            'categories' => $categories->sortBy('full_name')
        ]);
	}

	public function show($id)
	{
		$model = $this->categoryRepo->findOrFail($id);
		return response()->json($model);
	}

	public function create()
	{

	}

	public function store()
	{
		$model = $this->categoryRepo->create(request()->all());
		return response()->json($model);
	}

	public function update($id)
	{
		$model = $this->categoryRepo->update(request()->all(), $id );
		return response()->json($model);
	}

	public function destroy($id)
	{
		$response = array();
		$errors = array();
		$contractRules = ContractRule::where('categories', '!=', '')->get();
		$chnlContractRules = ChannelContractRule::where('categories', '!=', '')->get();
		$duplicate = false;
		$success = true;
		foreach ($contractRules as $rule) {
			$categoryIds = json_decode($rule->categories,true);
			foreach ($categoryIds as $categoryId) {
				if ($id == $categoryId) {
					$duplicate = true;
					$success = false;
				}
			}
		}

		foreach ($chnlContractRules as $rule) {
			$categoryIds = json_decode($rule->categories,true);
			foreach ($categoryIds as $categoryId) {
				if ($id == $categoryId) {
					$duplicate = true;
					$success = false;
				}
			}
		}

		if ($success) {
			$response['acknowledged'] = $this->categoryRepo->delete($id);
		}else{
			if($duplicate){
				$errors[] = 'Unable to delete this category due to contract(s) using this category.';
			}
			$response['errors'] = $errors;
			$response['acknowledged'] = false;
		}
        return response()->json($response);
	}
}