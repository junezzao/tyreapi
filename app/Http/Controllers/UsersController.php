<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Http\Requests;
use App\Http\Controllers\Controller;
use Bican\Roles\Models\Role;
use Session;
use Activity;
use App\Models\User;
use App\Repositories\Contracts\UserRepository;
use LucaDegasperi\OAuth2Server\Authorizer;

class UsersController extends Controller
{
    protected $userRepo;

    /**
     * Instantiate a new UserController instance.
     *
     * @return void
     */
    public function __construct(UserRepository $userRepo, Authorizer $authorizer)
    {
        $this->middleware('oauth');
        $this->userRepo = $userRepo;
        $this->authorizer = $authorizer;
        $this->userId = $this->authorizer->getResourceOwnerId();
    }


    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index()
    {
        return view('users.dashboard');
    }

    /**
     * Show the form for creating a new resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function create()
    {
        //
    }

    /**
     * Store a newly created resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function store(Request $request)
    {
        //
    }

    /**
     * Display the specified resource.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function show($id)
    {
        //
    }

    /**
     * Show the form for editing the specified resource.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function edit($id)
    {
        //
    }

    /**
     * Update the specified resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function update(Request $request, $id)
    {
        //
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function destroy($id)
    {
        //
    }

    public function verify(Request $request)
    {
        $response = $this->userRepo->update(array('password' => bcrypt($request->input('password')), 'status' => 'Active'), $request->input('user_id'));

        if ($response) {
            Activity::log('User verified successfully.', $this->userId);
        }
        
        return response(array(), 200);
    }
}
