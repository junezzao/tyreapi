<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use LucaDegasperi\OAuth2Server\Authorizer;
use Activity;
use App\Http\Controllers\Admin\AdminController as AdminController;
use App\Services\Mailer;
use Carbon\Carbon;

class OAuthController extends Controller
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
        $user = $this->user->where('email', strtolower($username))->first();

        if ($user) {
            if (app('hash')->check($password, $user->getAuthPassword())) {
                Activity::log('User login successfully.', $user->getKey());
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
        Activity::log('User logout successfully.', $authorizer->getResourceOwnerId());
        return 'logout successful';
    }

    public function passwordReset()
    {
        $user = $this->user->where('email', \Input::get('email') )->first();
        if (is_null($user)) {
            //$response['success'] = false;
            //$response['error'] = trans('sentence.forgot_password_email_not_found', ['email'=>\Input::get('email')]);
            
            return response()->json(['success'=>true, 'admin'=>array()]);
        }

        //$user->update(['status'=>'Unverified']);

        Activity::log('Password for user (' . $user->email . ' - ' . $user->id . ') has been reset.', $user->id);

        $token = $this->generateRandomString();

        $this->sendPasswordResetEmail($user, $token);
        
        return response()->json(['success'=>true, 'admin'=>$user->toArray()]);
    }

    public function sendPasswordResetEmail($user, $token)
    {
        \DB::table('password_resets')->insert(['email' => $user->email, 'token' => $token, 'created_at' => Carbon::now()]);

        $data['recipientName']  = $user->first_name.' '.$user->last_name;
        $data['actionUrl']      = env('APP_URL_ADMIN').'/password/reset/'.$token;
        $data['email']          = $user->email;

        $this->mailer->resetPassword($data);
    }
}
