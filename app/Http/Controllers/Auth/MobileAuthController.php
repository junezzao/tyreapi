<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use LucaDegasperi\OAuth2Server\Authorizer;
use Activity;
use App\Http\Controllers\Admin\AdminController as AdminController;
use App\Services\Mailer;

class MobileAuthController extends Controller
{
    /**
     * @var User
     */
    protected $user;
    protected $mailer;
    

    /**
     * AuthController Constructor.
     *
     * @param User $user
     */
    public function __construct(User $user, Mailer $mailer)
    {
        $this->user = $user;
        $this->mailer = $mailer;
    }

    /**
     * Verify if credentials are valid.
     *
     * @param string $username
     * @param string $password
     *
     * @return bool
     */
    public function passwordGrantVerify($username, $password)
    {
        $user = $this->user->where('email', strtolower($username))
                //->whereIn('category',['Merchant Admin','Merchant User'])
                ->first();

        if ($user) {
            if (app('hash')->check($password, $user->getAuthPassword())) {
                Activity::log('User login successful from Mobile.', $user->getKey());
                return $user->getKey();
            }
        }
        
        return false;
    }

    /**
     * Respond to incoming access token requests.
     *
     * @param Authorizer $authorizer
     *
     * @return mixed
     */
    public function accessToken(Authorizer $authorizer)
    {
        return $this->respond($authorizer->issueAccessToken());
    }

    public function appLogout(Authorizer $authorizer)
    {
        Activity::log('User logout successful from Mobile.', $authorizer->getResourceOwnerId());
        return 'logout successful';
    }

    public function passwordReset()
    {
        $user = $this->user->where('email', \Input::get('email') )
                ->whereIn('category',['Merchant Admin','Merchant User'])
                ->first();

        if (is_null($user)) {
            $response['success'] = false;
            $response['error'] = 'The email given does not match any user in our system.';
            
            return response()->json($response);
        }

        $merchant = $user->merchant()->first();

        $usercontroller = new AdminController;
        $input = array(); 
        Activity::log('Mobile >> Password reset (' . $user->email . ' - ' . $user->id . ') has been reset.', $user->id);

        $token = $usercontroller->generateRandomString();

        $email_data['recipient_name'] = $user->first_name . " " . $user->last_name;
        $email_data['merchant_name'] = !empty($merchant) ? $merchant->name : '';
        $email_data['url'] = \Input::get('url');
        $email_data['email'] = $user->email;
        $email_data['token'] = $token;

        $input['password'] = bcrypt($token);
        $input['status'] = 'Unverified';
       
        $user->update($input);

        $this->mailer->resetPassword($email_data, true);
        
        $response['success'] = true;
        $response['admin'] = $user->toArray();
        
        return response()->json($response);
    }
}
