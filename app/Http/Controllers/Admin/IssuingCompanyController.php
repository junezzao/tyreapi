<?php namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use LucaDegasperi\OAuth2Server\Authorizer;
use Illuminate\Http\Response;
use App\Models\Admin\IssuingCompany;
use Bican\Roles\Models\Role;
use App\Repositories\Contracts\IssuingCompanyRepository as IssuingCompanyRepository;
use Activity;
use Illuminate\Support\Collection;

class IssuingCompanyController extends AdminController
{
    protected $issuingCompanyRepo;
    protected $authorizer;

    public function __construct(IssuingCompanyRepository $issuingCompanyRepo, Authorizer $authorizer)
    {
        $this->middleware('oauth');
        $this->issuingCompanyRepo = $issuingCompanyRepo;
        $this->authorizer = $authorizer;
    }

	public function index()
	{
		$issuingCompanies = $this->issuingCompanyRepo->all();
		return response()->json($issuingCompanies);
    }

    public function show($id)
    {
        $issuingCompany = $this->issuingCompanyRepo->findOrFail($id);        
        return response()->json($issuingCompany);
    }

	public function store()
	{
		$issuingCompany = $this->issuingCompanyRepo->create(request()->except('access_token'));
		Activity::log('Issuing Company '.$issuingCompany->id.' was created', $this->authorizer->getResourceOwnerId());
		return response()->json($issuingCompany);
	}

    public function edit($id)
    {
    }

	public function update($id)
	{
		$issuingCompany = $this->issuingCompanyRepo->update(request()->except('access_token'), $id);
		Activity::log('Issuing Company ('.$id.' - '. $issuingCompany->name.') was updated', $this->authorizer->getResourceOwnerId());
		return response()->json($issuingCompany);
	}

	public function destroy($id)
	{
		$success = $this->issuingCompanyRepo->delete($id);
		if($success) Activity::log('Issuing Company ('.$id.')  was deleted', $this->authorizer->getResourceOwnerId());
		return response()->json(['acknowledged'=>$success?true:false]);
	}
}
