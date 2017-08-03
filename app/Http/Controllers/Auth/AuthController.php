<?php

namespace App\Http\Controllers\Auth;

use App\Models\User;
use Validator;
use App\Http\Controllers\Controller;
use Illuminate\Foundation\Auth\ThrottlesLogins;
use Illuminate\Foundation\Auth\AuthenticatesAndRegistersUsers;
use Illuminate\Http\Request;
use GuzzleHttp\Client;
use Activity;
use Session;
use Carbon\Carbon;
use Auth;
use App\Services\Mailer;
use App\Http\Controllers\Auth\OAuthController;
use App\Repositories\UserRepository;
use Bican\Roles\Models\Role;
use LucaDegasperi\OAuth2Server\Authorizer;
use LucaDegasperi\OAuth2Server\Exceptions\NoActiveAccessTokenException;

class AuthController extends Controller
{
    /*
    |--------------------------------------------------------------------------
    | Registration & Login Controller
    |--------------------------------------------------------------------------
    |
    | This controller handles the registration of new users, as well as the
    | authentication of existing users. By default, this controller uses
    | a simple trait to add these behaviors. Why don't you explore it?
    |
    */

    use AuthenticatesAndRegistersUsers, ThrottlesLogins;

    protected $redirectPath = '/1.0/hw/dashboard';
    protected $redirectAfterLogout = '/1.0/hw/login';

    /**
     * Create a new authentication controller instance.
     *
     * @return void
     */
    public function __construct(Authorizer $authorizer)
    {
        $this->middleware('guest', ['except' => 'getLogout']);
        $this->authorizer = $authorizer;
    }

    /**
     * Get a validator for an incoming registration request.
     *
     * @param  array  $data
     * @return \Illuminate\Contracts\Validation\Validator
     */
    protected function validator(array $data)
    {
        return Validator::make($data, [
            'name' => 'required|max:255',
            'email' => 'required|email|max:255|unique:users',
            'password' => 'required|confirmed|min:6',
        ]);
    }

    /**
     * Create a new user instance after a valid registration.
     *
     * @param  array  $data
     * @return User
     */
    protected function create(array $data)
    {
        return User::create([
            'name' => $data['name'],
            'email' => $data['email'],
            'password' => bcrypt($data['password']),
        ]);
    }

     public function postLogin(Request $request)
    {
        $this->redirectPath = route('hw.dashboard');

        $this->validate($request, [
            $this->loginUsername() => 'required', 'password' => 'required',
        ]);

        $throttles = $this->isUsingThrottlesLoginsTrait();

        if ($throttles && $this->hasTooManyLoginAttempts($request)) {
            return $this->sendLockoutResponse($request);
        }

        $credentials = $this->getCredentials($request);
        $authorized_status = ['Active', 'Unverified'];

        foreach ($authorized_status as $status) {
            $credentials['status'] = $status;

            if (Auth::guard('web')->attempt($credentials, $request->has('remember'))) {
                $request->request->add(['username' => $request->input('email'), 'grant_type' => 'password']);

                // get access token
                $response = Authorizer::issueAccessToken();

                $session['access_token'] = $response['access_token'];
                $session['token_type'] = $response['token_type'];
                $session['expires_on'] = Carbon::now()->addminutes($response['expires_in']);
                Session::put('hapi', $session);

                $user = User::where('email', strtolower($credentials['email']))->first();
                $userSession['user_id'] = $user->id;
                $userSession['user_firstname'] = $user->first_name;
                $userSession['status'] = $user->status;
                Session::put('user', $userSession);

                if (strcasecmp(Auth::guard('web')->user()['status'], 'Unverified') == 0) {
                    $this->redirectPath = route('hw.users.show_verify');
                }

                return $this->handleUserWasAuthenticated($request, $throttles);
            }
        }

        if ($throttles) {
            $this->incrementLoginAttempts($request);
        }

        return redirect()->route('hw.login')
            ->withInput($request->only($this->loginUsername(), 'remember'))
            ->withErrors([
                $this->loginUsername() => $this->getFailedLoginMessage(),
            ]);
    }

    public function getLogout() {
        $this->redirectAfterLogout = route('hw.getlogin');

        $access_token = Session::get('hapi');
        Activity::log('User logout successfully.', \Auth::guard('web')->user('id'));
        // $response = $this->logoutUser($access_token['access_token']);
        Session::forget('hapi');
        Auth::guard('web')->logout();
        return redirect()->route('hw.login');
    }

    public function forgot(Request $request) {
        $request->request->add(['url' => route('hw.login')]);

        $oauth = new OAuthController(new User, new Mailer);
        $response = $oauth->passwordReset();

        if ($response->getStatusCode() == 200) {
            $content = $response->getData();

            if ($content->success) {
                flash()->success('Your password has been reset. Please check your email.');
            }
            else {
                flash()->error($content->error);
            }
            
            return redirect()->back();
        } else {
            flash()->error('Something wrong happens');
            return back()->withInput();
        }
    }

    public function showVerify() {
        if (strcasecmp(Auth::guard('web')->user()['status'], "Unverified") !== 0) {
            flash()->error(trans('permissions.unauthorized'));
            return redirect()->route('hw.dashboard');
        }

        $user_id = Auth::guard('web')->user()['id'];
        return view('users.verify', array('user_id' => $user_id));
    }

    public function verify(Request $request) {
        $this->validate($request, array(
            'password' => 'regex:"^(?=.*[a-zA-Z])(?=.*\d)[A-Za-z\d$@$!%*?&]{6,}"|confirmed|required'
        ));

        $userRepo = new UserRepository(new User, new Role);
        $response = $userRepo->update(array('password' => bcrypt($request->input('password')), 'status' => 'Active'), $request->input('user_id'));

        if ($response) {
            Session::forget('hapi');

            $userSession = session('user');
            $userSession['status'] = 'Active';
            
            Session::put('user', $userSession);

            Activity::log('User verified successfully.', $request->input('user_id'));
            return redirect()->route('hw.dashboard');
        }
        
        flash()->error('Something wrong happens');
        return back()->withInput();
    }

    public function store(Request $request)
    {
        $this->validate($request, array(
            'operation_type'    => 'required|max:255',
            'first_name'        => 'required|max:255',
            'last_name'         => 'required|max:255',
            'email'             => 'required|email|max:255|unique:users',
            'contact_no'        => 'required|max:255',
            'company_name'      => 'required|max:255',
            'address_line_1'    => 'required|max:255',
            'address_line_2'    => 'sometimes|max:255',
            'address_city'      => 'required|max:255',
            'address_postcode'  => 'required|max:255',
            'address_state'     => 'required|max:255',
            'address_country'   => 'required|max:2'
        ));

        $role = Role::select('id', 'name')->where('slug', 'lite')->firstOrFail();
        $token = $this->generateRandomString();

        $data               = $request->all();
        $data['password']   = bcrypt($token);
        $data['status']     = 'Unverified';
        $data['category']   = $role->name;

        $userRepo = new UserRepository(new User, new Role);
        $user = $userRepo->create($data);
        $user->attachRole($role->id);

        $this->sendActivationEmail($data, $token);
        
        try {
            $ownerId = $this->authorizer->getResourceOwnerId();
        } catch (NoActiveAccessTokenException $e) {
            $ownerId = 0;
        }

        Activity::log('New user (' . $user->email . ' - ' . $user->id . ') has been created', $ownerId);

        return response(array(), 200);
    }

    public function sendActivationEmail($input, $token)
    {
        \DB::table('password_resets')->insert(['email' => $input['email'], 'token' => $token, 'created_at' => Carbon::now()]);

        $data['actionUrl']      = env('APP_URL_ADMIN').'/account/activate/'.$token;
        $data['actionText']     = 'Account Verification';
        $data['recipientName']  = $input['first_name'].' '.$input['last_name'];
        $data['email']          = $input['email'];

        $mailer = new Mailer();
        $mailer->accountVerification($data);
    }

}
