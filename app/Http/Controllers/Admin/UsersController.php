<?php 
namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use LucaDegasperi\OAuth2Server\Authorizer;
use Illuminate\Http\Response;
use App\Models\User;
use App\Models\Admin\Merchant;
use App\Models\Subscription;
use Bican\Roles\Models\Role;
use App\Repositories\Contracts\UserRepository;
use Illuminate\Http\Request;
use Validator;
use DB;
use App\Services\Mailer;
use Activity;
use Hash;

class UsersController extends AdminController
{
    protected $userRepo;
    protected $mailer;
    protected $authorizer;

    public function __construct(UserRepository $userRepo, Mailer $mailer, Authorizer $authorizer)
    {
        $this->middleware('oauth');
        $this->userRepo = $userRepo;
        $this->mailer = $mailer;
        $this->authorizer = $authorizer;
    }

    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index()
    {
        return $this->userRepo->all();
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
    public function store(Request $request, Mailer $mailer)
    {
    }

    /**
     * Display the specified resource.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function show($id)
    {
        $user = User::withTrashed()->with('subscriptions')->with('activePlan')->find($id);
        $role = $user->getRoles();
        if (count($role) > 0) {
            $user->level = $role[0]->level;
        }
        
        return response()->json($user);
    }

    /**
     * Show the form for editing the specified resource.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function edit($id)
    {
    }

    /**
     * Get the logged in user.
     *
     * @return \Illuminate\Http\Response
     */
    public function getLoggedInUser()
    {
        $user_id = $this->authorizer->getResourceOwnerId(); // the token user_id
        $user = $this->userRepo->find($user_id);// get the user data from database
        $user->role = $user->getRoles();
        return response()->json($user);
    }

    /**
     * Get the permission of the logged in user.
     *
     * @return \Illuminate\Http\Response
     */
    public function getLoggedInUserPermission()
    {
        $user_id = $this->authorizer->getResourceOwnerId(); // the token user_id
        $user = $this->userRepo->find($user_id);
        $permissions = $user->getPermissions();
        return response()->json($permissions);
    }

    /**
     * Get user by id.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function getUser($id)
    {
    }

    /**
     * Update the specified resource.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function update($id)
    {
        $input = \Input::except('email', 'category');
        
        $user = $this->userRepo->update($input, $id);
        
        if (isset($input['new_password']) && !empty($input['new_password'])) {
            if(Hash::check($input['old_password'], $user->password)) {
                $user->password = bcrypt($input['new_password']);
                $user->save();
            } else {
                $response['success'] = false;
                $response['errors']['old_password'][] = 'The old password does not match.';
                return response()->json($response);
            }
        }

        Activity::log('User profile ('. $user->id .') updated successfully.', $this->authorizer->getResourceOwnerId());

        return response()->json(['success' => true]);
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function destroy($id)
    {
    }

    public function subscribe(Request $request, $id)
    {
        $user = User::with('subscriptions')->find($id);
        $today = date('Y-m-d');
        $lastEndDate = $today;
        if(count($user->subscriptions) > 0) {
            $lastEndDate = $user->subscriptions[0]->end_date;
        }

        $newStartDate = strtotime($today) >= strtotime($lastEndDate) ? $today : date("Y-m-d", strtotime("+1 day", strtotime($lastEndDate)));

        $subscription = new Subscription;
        $subscription->user_id = $id;
        $subscription->role_id = $request->input('subscription_type');
        $subscription->status = strtotime($today) >= strtotime($newStartDate) ? 'Active' : 'Upcoming';
        $subscription->start_date = $newStartDate;
        $subscription->end_date = date("Y-m-d", strtotime("-1 day", strtotime("+1 month", strtotime($newStartDate))));
        $subscription->save();

        $role = Role::findOrFail($subscription->role_id);
        if($subscription->status == 'Active') {
            $user->category = $role->name;
            $user->subscription_id = $subscription->id;
            $user->detachAllRoles();
            $user->attachRole($role->id);
            $user->save();
        }

        return response()->json(['success' => true]);
    }

}
