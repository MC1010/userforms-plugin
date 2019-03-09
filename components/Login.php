<?php namespace WebBro\UserForms\Components;

use Auth;
use Cms\Classes\Page;
use Event;
use Exception;
use Flash;
use Lang;
use RainLab\User\Components\Account;
use RainLab\User\Models\Settings as UserSettings;
use Redirect;
use Request;
use ValidationException;
use Validator;
use Illuminate\Support\Facades\Log;

class Login extends Account
{
    public function componentDetails()
    {
        return [
            'name'        => 'webbro.userforms::lang.components.login.name',
            'description' => 'webbro.userforms::lang.components.login.description'
        ];
    }
    
    public function onRun()
    {
        //redirect to HTTPS checker
        if ($redirect = $this->redirectForceSecure()) {
            return $redirect;
        }
        
        /*
         * Activation code supplied
         */
        if ($code = $this->activationCode()) {
            $this->onActivate($code);
        }
        
        //user is logged in
        if($user = $this->user())
        {
            //redirect to selected page
            if ($redirect = $this->makeRedirection(true)) 
            {
                return $redirect;
            }
        }
        else 
        {
            $this->addJs('assets/js/login.js');
            $this->addCss('assets/css/style.css');
            
            $this->prepareVars();
        }
    }
    
    public function defineProperties()
    {
        $properties = parent::defineProperties();
        $properties['showTitles'] = [
            'title'       => /*Show titles*/'webbro.userforms::lang.components.login.show_titles_title',
            'description' => /*Should the field titles be displayed on the field*/'webbro.userforms::lang.components.login.show_titles_desc',
            'type'        => 'checkbox',
            'default'     => 0
        ];
        
        return $properties;
    }
    
    public function prepareVars()
    {
        parent::prepareVars();
        
        $this->page['showTitles'] = $this->property('showTitles');
    }
    
    public function onSignin()
    {
        try 
        {
            /*
             * Validate input
             */
            $data = post();
            $rules = $this->validationRules();
            
            if (!array_key_exists('login', $data)) 
            {
                $data['login'] = post('username', post('email'));
            }
            
            
            $validation = $this->makeValidator($data, $rules);
            if ($validation->fails()) 
            {
                throw new ValidationException($validation);
            }
            
            /*
             * Authenticate user
             */
            $credentials = [
                'login'    => array_get($data, 'login'),
                'password' => array_get($data, 'password')
            ];
            
            Event::fire('rainlab.user.beforeAuthenticate', [$this, $credentials]);
            
            $user = Auth::authenticate($credentials, true);
            if ($user->isBanned()) 
            {
                Auth::logout();
                throw new AuthException('rainlab.user::lang.account.banned');
            }
            
            /*
             * Redirect
             */
            if ($redirect = $this->makeRedirection(true)) 
            {
                return $redirect;
            }
        } 
        catch (Exception $ex) 
        {
            /*
             * Generic error messages are automatically generated by the system
             * when 'debug' mode is disabled.
             * 
             * 'Debug' mode causes confusing error messages to appear on the 
             * screen..... This IS normal.
             */
            if (Request::ajax()) 
            {
                throw $ex;
            }
            else 
            {
                Flash::error($ex->getMessage());
            }
        }
    }
    
    /**
     * Activate the user
     * @param  string $code Activation code
     */
    public function onActivate($code = null)
    {
        try {
            $code = post('code', $code);
            
            $errorFields = ['code' => Lang::get(/*Invalid activation code supplied.*/'rainlab.user::lang.account.invalid_activation_code')];
            
            /*
             * Break up the code parts
             */
            $parts = explode('!', $code);
            if (count($parts) != 2) {
                throw new ValidationException($errorFields);
            }
            
            list($userId, $code) = $parts;
            
            if (!strlen(trim($userId)) || !strlen(trim($code))) {
                throw new ValidationException($errorFields);
            }
            
            if (!$user = Auth::findUserById($userId)) {
                throw new ValidationException($errorFields);
            }
            
            if (!$user->attemptActivation($code)) {
                throw new ValidationException($errorFields);
            }
            
            $message = Lang::get(/*Successfully activated your account.*/'rainlab.user::lang.account.success_activation');
            
            /*
             * Redirect
             */
            return Redirect('')->with('message', $message);
            
        }
        catch (Exception $ex) {
            if (Request::ajax()) throw $ex;
            else Flash::error($ex->getMessage());
        }
    }
    
    public function makeValidator($data, $rules)
    {
        $messages = $this->getValidatorMessages();
        return Validator::make($data, $rules, $messages);
    }
    
    public function getValidatorMessages()
    {
        return [
            'login.required' => 'Please enter your ' . $this->loginAttribute(),
            'password.required' => 'Please enter your password'
        ];
    }
    
    public function validationRules()
    {
        return [
            'login' => $this->loginAttribute() == UserSettings::LOGIN_USERNAME
                            ? 'required|between:2,255'
                            : 'required|email|between:6,255',
            'password' => 'required|between:4,255'
        ];
    }
}