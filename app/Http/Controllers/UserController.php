<?php

namespace App\Http\Controllers;

use App\Http\Requests\forgotPassword;
use App\Http\Requests\resetPassword as RequestsResetPassword;
use App\Http\Requests\resetPasswordRequest;
use App\Http\Requests\userLogIn;
use App\Http\Requests\userRegistration;
use App\Http\Resources\userResource;
use App\Jobs\emailRegistration;
use App\Jobs\resetPassword as JobsResetPassword;
use App\Mail\ResetPasswordMail;
use App\Models\ResetPassword;
use App\Models\User;
use App\Models\Token;
use App\Services\GenerateToken;
use Illuminate\Support\Facades\Hash;
use Illuminate\Http\Request;
use Throwable;

class UserController extends Controller
{
    public function register(userRegistration $request){
       try {
            $fields = $request->validated();
            
            $token = (new GenerateToken)->createToken($fields['email']);
            $verificationURL = 'http://127.0.0.1:8000/api/emailVerification/' . $token . '/' . $fields['email'];
            User::create([
                'name' => $fields['name'],
                'email' => $fields['email'],
                'password' => Hash::make($fields['password']),
                'age' => $fields['age'],
                'profile_picture' => $fields['profile_picture']->store('user_images/profile_photos'), 
                'Verification_Token' => $token,
                'Email_Verified_At' => null,
                'PasswordReset_Token' => null
            ]);
            emailRegistration::dispatch($fields['email'], $verificationURL); //php artisan queue:work
            return [
                'Status' => 1,
                'Message' => "Registration request sent successfully!"
            ];

        } catch (Throwable $e) {
            return $e->getMessage();
        }
    }

    public function emailVerification($token, $email){
        try {
            $emailVerify = User::where('email', $email)->first();
            if ($emailVerify->Email_Verified_At != null) {
                return response([
                    'message' => 'Already Verified'
                ]);
            } else if ($emailVerify) {
                $emailVerify->Email_Verified_At = date('Y-m-d h:i:s');
                $emailVerify->save();
                return response([
                    'message' => 'Eamil Verified'
                ]);
            } else {
                return response([
                    'message' => 'Error'
                ]);
            }
        } catch (Throwable $e) {
            return $e->getMessage();
        }
    }

    public function forgetPassword(forgotPassword $request){

        try{

            $fields = $request->validated();

            $token = (new GenerateToken)->createToken($fields['email']);
            $PasswordReset_Token = 'http://127.0.0.1:8000/api/resetPassword/' . $token . '/' . $fields['email'];
            
            $findEmail = User::where('email', $fields['email'])->first();
            $findEmail->PasswordReset_Token = $token;
            $findEmail->save();

            ResetPassword::create([
                'email' => $fields['email'],
                'token' => $token,
                'expiry' => 0
            ]);

            JobsResetPassword::dispatch($fields['email'], $PasswordReset_Token);
            
            return [
                'Status' => 1,
                'Message' => "Reset password request sent successfully!"
            ];
            
        } catch (Throwable $e) {
            return $e->getMessage();
        }
    }
    
    public function resetPassword(resetPasswordRequest $request, $token, $email){
        try{
            $fields = $request->validated();

            $verify = ResetPassword::where('email', $email)->where('token', $token)->first();
            
            $verify->expiry = 1;
            $verify->update();

            User::where('email',$email)->update([
                'password' => Hash::make($fields['password'])
            ]);
            return [
                'Status' => 1,
                'Message' => "Password changed successfully!"
            ];        
        } catch (Throwable $e) {
            return $e->getMessage();
        }

    }

    public function login(userLogIn $request){
        try {
            $fields = $request->validated();

            $user = User::where('email', $fields['email'])->first();
            if (isset($user->id)) {

                if (Hash::check($fields['password'], $user->password)) {
                
                    $isLoggedIn = Token::where('userID', $user->id)->first();
                    if ($isLoggedIn) {
                        return response([
                            "message" => "User already logged In",
                        ], 400);
                    }

                    $token = (new GenerateToken)->createToken($user->id);
                    $saveToken = Token::create([
                        "userID" => $user->id,
                        "token" => $token
                    ]);
                    $response = [
                        'status' => 1,
                        'message' => 'Logged in successfully',
                        'user' => new userResource($user),
                        'token' => $token
                    ];

                    return response($response, 201);
                } else {
                    return response([
                        'message' => 'Invalid email or password'
                    ], 401);
                }
            } else {
                return response()->json([
                    "status" => 0,
                    "message" => "Student not found"
                ], 404);
            }
        } catch (Throwable $e) {
            return $e->getMessage();
        }
    }

    public function logout(Request $request){
        try {
            $userID = decodingUserID($request);
            $userExist = Token::where("userID", $userID)->first();
            if ($userExist) {
                $userExist->delete();
            }

            return response([
                "message" => "logout successfull"
            ]);
        } catch (Throwable $e) {
            return $e->getMessage();
        }
    }

  

    public function seeProfile(Request $request){
        try {
            $userID = decodingUserID($request);
            $check = Token::where('token', $request->bearerToken())->first();
            if (!isset($check)) {
                return response([
                    "message" => "Invalid Token"
                ], 200);
            }

            if ($userID) {
                $profile = User::find($userID);
                return response([
                    "Profile" => new userResource($profile)
                ], 200);
            }
        } catch (Throwable $e) {
            return $e->getMessage();
        }
    }

    public function updateProfile(Request $request, $id){
        try {

            $user = User::all()->where('id', $id)->first();
            if (isset($user)) {
                $user->update($request->all());               
                if ($request->file('profile_picture') != null) {
                    $profilePicture = $request->file('profile_picture')->store('user_images');
                    $user->profile_picture = $profilePicture;
                    $user->save();
                }

                return response([
                    'Status' => '200',
                    'message' => 'you have successfully Updated User Profile',
                ]);
            }
            if ($user == null) {
                return response([
                    'Status' => '404',
                    'message' => 'User not found',
                ]);
            }
        } catch (Throwable $e) {
            return $e->getMessage();
        }
    }
}
