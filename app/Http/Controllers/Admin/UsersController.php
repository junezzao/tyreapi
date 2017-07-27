<?php 
namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use LucaDegasperi\OAuth2Server\Authorizer;
use Illuminate\Http\Response;
use App\Models\User;
use App\Models\Admin\Merchant;
use Bican\Roles\Models\Role;
use App\Repositories\Contracts\UserRepository;
use App\Repositories\Contracts\MerchantRepository;
use Illuminate\Http\Request;
use Validator;
use DB;
use App\Services\Mailer;
use Activity;

class UsersController extends AdminController
{
    protected $userRepo;
    protected $merchantRepo;
    protected $mailer;
    protected $authorizer;

    public function __construct(UserRepository $userRepo, MerchantRepository $merchantRepo, Mailer $mailer, Authorizer $authorizer)
    {
        $this->middleware('oauth');
        //$this->middleware('permission:view.user', ['only' => ['index', 'show']]);
        //$this->middleware('permission:edit.user', ['only' => ['edit', 'update']]);
        $this->userRepo = $userRepo;
        $this->merchantRepo = $merchantRepo;
        $this->mailer = $mailer;
        //$this->user = OAuth::user();
        $this->authorizer = $authorizer;
    }

    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index()
    {
        $users = $this->userRepo->all(request()->all());
        return response()->json([
            'start' => intval(request()->get('start', 0)), 
            'limit' => intval(request()->get('limit', 30)), 
            'total' => $this->userRepo->count(),
            'users' => $users
        ]);
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
        $user_category = Role::select('id', 'name')->where('slug', '=', $request->input('user_category'))->firstOrFail();
        $merchant = $this->merchantRepo->findBy('slug', $request->input('merchant', ''), ['id', 'name', 'timezone', 'currency']);
        $token = $this->generateRandomString();

        $user['first_name'] = ucwords($request->input('first_name'));
        $user['last_name'] = ucwords($request->input('last_name'));
        $user['email'] = $request->input('email');
        $user['password'] = bcrypt($token);
        $user['contact_no'] = $request->input('contact_no', '');
        $user['address'] = $request->input('address', '');
        $user['timezone'] = !empty($request->input('default_timezone')) ? $request->input('default_timezone') : (!empty($merchant) ? $merchant->timezone : '');
        $user['currency'] = !empty($request->input('default_currency')) ? $request->input('default_currency') : (!empty($merchant) ? $merchant->currency : '');
        $user['status'] = 'Unverified';
        $user['category'] = $user_category->name;
        $user['merchant_id'] = (!empty($merchant)) ? $merchant->id : NULL;

        $user = $this->userRepo->create($user);
        $user->attachRole($user_category->id);

        Activity::log('New user (' . $user->email . ' - ' . $user->id . ') has been created.', $this->authorizer->getResourceOwnerId());

        if ($user_category->name == 'Mobile Merchant') {
            $email_data['recipient_name'] = $user->first_name . " " . $user->last_name;
            $email_data['merchant_name'] = !empty($merchant) ? $merchant->name : '';
            $email_data['url'] = null;
            $email_data['email'] = $user->email;
            $email_data['token'] = $token;
            $email_data['app_url'] = 'https://itunes.apple.com/my/app/arc-mobile/id1192584210?mt=8';

            $mailer->accountVerification($email_data);
        }else {
            $email_data['recipient_name'] = $user->first_name . " " . $user->last_name;
            $email_data['merchant_name'] = !empty($merchant) ? $merchant->name : '';
            $email_data['url'] = $request->input('url');
            $email_data['email'] = $user->email;
            $email_data['token'] = $token;

            $mailer->accountVerification($email_data);
        }
        

        return response(array(), 200);
    }

    /**
     * Display the specified resource.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function show($id)
    {
        $user = User::with('merchant')->with('channels')->withTrashed()->find($id);
        $role = $user->getRoles();
        if (count($role) > 0) {
            $user->category = $role[0]->slug;
            $user->level = $role[0]->level;
        } else {
            $user->category = '';
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
        $input = \Input::except('email');
        $user = $this->userRepo->find($id);
        
        // if user edited by superadmin
        if (isset($input['editUser'])) {
            $user_category = Role::select('id', 'name')->where('slug', '=', $input['category'])->firstOrFail();
            $user->detachAllRoles();
            $user->attachRole($user_category->id);

            $merchant = $this->merchantRepo->findBy('slug', $input['merchant'], ['id', 'name', 'timezone', 'currency']);
            $input['merchant_id'] = (!empty($merchant) && ($input['category'] == 'clientadmin' || $input['category'] == 'clientuser')) ? $merchant->id : null;

            $input['category'] = $user_category->name;
        }

        $user = $this->userRepo->update($input, $id);
        
        if (isset($input['new_password']) && !empty($input['new_password'])) {
            $user->password = bcrypt($input['new_password']);
            $user->save();
        }

        Activity::log('User profile ('. $user->id .') updated successfully.', $this->authorizer->getResourceOwnerId());

        $response['success'] = true;
        $response['admin'] = $user->toArray();
        return response()->json($response);
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function destroy($id)
    {
        $user = $this->userRepo->find($id);
        $user->status = "Deleted";
        $user->save();
        $user->delete();
        Activity::log('User (' . $user->email . ') has been deleted.', $this->authorizer->getResourceOwnerId());
        $response['success'] = true;
        return response()->json($response);
    }

}
