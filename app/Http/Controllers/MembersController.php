<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use App\Repositories\MemberRepository;

class MembersController extends Controller
{
    protected $memberRepo;

    public function __construct(MemberRepository $memberRepo)
    {
    	$this->memberRepo = $memberRepo;
    }

    public function index()
    {
        $members = $this->memberRepo->all();
        return response()->json([
            'start'=> intval(\Input::get('start', 0)), 
            'limit' => intval(\Input::get('limit', 30)), 
            'total' => $this->merchantRepo->count(),
            'members'=>$members
        ]);
    }

    public function show($id)
    {
    	$member = $this->memberRepo->find($id);
    	return $member;
    }


}
