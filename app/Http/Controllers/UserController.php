<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Redis;



class UserController extends Controller
{
    /*
    //GET
    */

    //Check if a user exists
    public function existsUser($userId)
    {
        $user = User::find($userId);
        if($user == null)
        {
            Log::info('User not found: ', [$userId]);
            return response()->json(["exists" => false], 404);
        }
        else{
            Log::info('User found: ', [$userId]);
            return response()->json(["exists" => true], 201);
        }
    }

    //Retrieve existing user
    public function getUser($userId)
    {
        $validator = Validator::make(["userId" => $userId], [
            'userId' => 'required|exists:App\Models\User,userId|string|numeric',
        ]);
        if ($validator->fails()) {
            Log::error('Resource Validation Failed: ', [$validator->errors(), $userId]);
            return response()->json(["result" => "failed","errorMessage" => $validator->errors()], 400);
        }

        $user = User::find($userId);

        Log::info('Retrieving user with id: ', [$user->userId]);
        return response()->json(["result" => "ok", "user" => $user], 201);
    }

    //Retrieve all the existing users
    public function getUsers()
    {
        //TODO:check for admin priv maybe???
        $users =User::all();
        //$users = User::where('permissions', "admin")->where('role', "Evaluator")->get();
        Log::info('Retrieving all users ');
        return response()->json(["result" => "ok", "users" => $users], 201);
    }

    //Retrieve all the users with "reviewer" permission     {admin}
    public function getAllReviewers($userId)
    {
        //Validating the input
        $validator = Validator::make(["userId" => $userId], [
            'userId' => 'required|exists:App\Models\User,userId|string|numeric',
        ]);
        if ($validator->fails()) {
            Log::error('Resource Validation Failed: ', [$validator->errors(), $userId]);
            return response()->json(["result" => "failed","errorMessage" => $validator->errors()], 400);
        }

        //Check user is admin
        $adminUser = User::find($userId);
        if(in_array("admin", $adminUser->permissions))
        {
            Log::info('Fetch all requested by admin: ', [$userId]);
        }
        else{
            Log::warning('User does not have administrator rights: ', $adminUser->permissions);
            return response()->json(["result" => "failed","errorMessage" => 'User does not have administrator rights: '], 202);
        }

        $reviewers = User::where('permissions', "Reviewer")->get();
        Log::info('Retrieving all reviewers ');
        return response()->json(["result" => "ok", "users" => $reviewers], 201);

    }


    /*
    //POST
    */
    //Add a new user to the collection, given the ID
    public function insertUser(Request $request)
    {
        //TODO: MOOOOOOOAR VALIDATION MOAAAAAAAR (request check etc)
        $rules = array(
            'userId' => 'required|unique:App\Models\User,userId|string|numeric',
            'role' => 'present|nullable|string',
            'permissions' => 'present|array'
        );

        $user = new User;

        $user->userId = $request->userId;                   //Users ID
        $user->role = "";                                   //User role ("" default value)
        $user->permissions = ["user"];                      //User permission ("user" default value)

        //Validation on the final user entities
        $validator = Validator::make($user->attributesToArray(),$rules);
        if ($validator->fails()) {
            Log::error('Resource Validation Failed: ', [$validator->errors(), $request->toArray()]);
            return response()->json(["result" => "failed","errorMessage" => $validator->errors()], 400);
        }

        //Save to database and log
        $user->save();
        Log::info('Adding new user with id: ', [$user->userId, $request->toArray() ]);

        return response()->json(["result" => "ok"], 201);
    }

    /*
    //PUT
    */
    //Update user roles         {user}
    public function updateRoleUser(Request $request)
    {
        //TODO: MOOOOOOOAR VALIDATION MOAAAAAAAR (user check etc)
        //Validating the request
        $rules = array(
            'userId' => 'required|exists:App\Models\User,userId|string|numeric',         //ID must be present and existing in the database
            'role' => 'present|nullable|string'
        );
        $validator = Validator::make($request->toArray(),$rules);
        if ($validator->fails()) {
            Log::error('Request Validation Failed: ', [$validator->errors(), $request->toArray()]);
            return response()->json(["result" => "failed","errorMessage" => $validator->errors()], 400);
        }

        //Updating the role
        $user = User::find($request->userId);
        $user->role = $request->role;
        $user->save();
        Log::info('Updating user role with id: ', [$user->userId, $user->role]);
        return response()->json(["result" => "ok"], 201);

    }

    //Update user permissions      {admin}
    public function updatePermissionsUser(Request $request)
    {
        //Request validation
        $rules = array(
            'userId' => 'present|exists:App\Models\User,userId|string|numeric',         //ID must be present and existing in the database
            'targetId' => 'present|exists:App\Models\User,userId|string|numeric',       // >>       >>              >>          >>
            'permissions' => 'present|array'
        );
        $validator = Validator::make($request->toArray(),$rules);
        if ($validator->fails()) {
            Log::error('Request Validation Failed: ', [$validator->errors(), $request->toArray()]);
            return response()->json(["result" => "failed","errorMessage" => $validator->errors()], 400);
        }

        //Check if usedId has admin privileges
        $adminUser = User::find($request->userId);
        if(in_array("admin", $adminUser->permissions))
        {
            Log::info('Update requested by admin: ', [$request->userId]);
        }
        else{
            Log::warning('User does not have administrator rights: ', $adminUser->permissions);
            return response()->json(["result" => "failed","errorMessage" => 'User does not have administrator rights: '], 202);
        }
        $user = User::find($request->targetId);
        $newPermissions = $request->permissions;
        //Check if targetId has admin privileges and if they are included in the request
        if(in_array("admin", $user->permissions))
        {
            if(in_array("admin", $request->permissions))
            {
                Log::info('Expected behaviour by update');
            }
            else{
                Log::warning('Administrator rights not given, adding..... ', $request->permissions);
                //add string to array
                array_push($newPermissions, "admin");
            }
        }
        else
        {
            if(in_array("admin", $request->permissions))
            {
                Log::warning('Target user does not have administrator rights: ', $user->permissions);
                return response()->json(["result" => "failed","errorMessage" => 'Target user does not have administrator rights: '], 202);
            }
            else{
                Log::info('Expected behaviour by update');
            }
        }

        //Updating the permissions of the targetId
        $user = User::find($request->targetId);
        $user->permissions = $newPermissions;

        //Save new data, log and return
        $user->save();
        Log::info('Updating user permissions with id: ', [$user->userId, $user->permissions]);
        return response()->json(["result" => "ok"], 201);

    }


    /*
   //PLAYAROUND
   */
    public function playaround()
    {
        $result = Http::redisFetch();
        return $result;
    }

    public function morningHead(Request $request)
    {

        $headId = $request->header('user_id');
        $result = User::find($headId);
        return response()->json(["result" => "ok", $result], 201);
    }






}
