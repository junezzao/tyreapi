<?php

namespace App\Modules\Contracts\Http\Controllers;

use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use LucaDegasperi\OAuth2Server\Authorizer;
use Illuminate\Http\Response;
use App\Models\Admin\Contract;
use Bican\Roles\Models\Role;
use App\Modules\Contracts\Repositories\Eloquent\ContractRepository;
use Illuminate\Support\Collection;
use Activity;

class ContractsController extends Controller
{
    protected $contractRepo;
    protected $authorizer;
    protected $userRepo;

    public function __construct(ContractRepository $contractRepo, Authorizer $authorizer)
    {
        $this->middleware('oauth');
        $this->contractRepo = $contractRepo;
        $this->authorizer = $authorizer;
    }

	public function index()
	{
        $contract = $this->contractRepo->with('brand', 'merchant')->all();
		return response()->json([
			'start'  => intval(\Input::get('start', 0)), 
            'limit'  => intval(\Input::get('limit', 30)), 
            'total'  => $this->contractRepo->count(),
            'contracts' => $contract
        ]);
    }
    
    public function show($id)
    {
        $contract = $this->contractRepo->with('brand', 'merchant', 'contract_rules')->findOrFail($id);
        return response()->json($contract);
    }

	public function store()
	{
		$contract = $this->contractRepo->store(\Input::except('access_token'));
		return response()->json($contract);
	}

    public function edit($id)
    {
    }

	public function update($id)
    {
        //\Log::info(request()->except('access_token'));
        $contract = $this->contractRepo->update(request()->except('access_token'), $id);
        return response()->json($contract);
    }

    public function duplicate($id)
	{
        //\Log::info(request()->except('access_token'));
		$contract = $this->contractRepo->duplicate($id);
		return response()->json($contract);
	}

	public function destroy($id)
	{
        $contract = $this->contractRepo->delete($id);
        return response()->json(['acknowledged'=>$contract?true:false]);
	}

    public function getContracts()
    {
        return response()->json(['contracts'=>$this->contractRepo->whereIn('id', request()->input('id'))]);
    }    

    public function updateDate($id)
    {
        return response()->json($this->contractRepo->updateDate(request()->except('access_token'), $id));
    }

    public function calculateFee()
    {
        return response()->json($this->contractRepo->calculateFee(request()->except('_token')));
    }

    public function exportFeeReport()
    {
        return response()->json($this->contractRepo->exportFeeReport(request()->except('_token')));
    }

}
