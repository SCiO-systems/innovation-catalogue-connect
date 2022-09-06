<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;
use Predis\Client as PredisClient;


class UserController extends Controller
{
    /*
    //GET
    */

    //Check if a user exists and return the proper usable info from MEL
    public function existsUser($profile_id)
    {
        //Fetch the user from MEL based on the profile id given
        $melResponse = Http::withHeaders(["Authorization" =>env('MEL_API_KEY','')])
            ->post(env('MEL_SEARCH_USERS',''), [
                "query" => [
                    "bool" => [
                        "must" => [
                            [
                                "match" => [
                                    "profile_id" => $profile_id
                                ]
                            ],
                            [
                                "term" => [
                                    "user_is_active" => [
                                        "value" => "1"
                                    ]
                                ]
                            ],
                            [
                                "term" => [
                                    "profile_is_active" => [
                                        "value" => "1"
                                    ]
                                ]
                            ]
                        ]
                    ]
                ]
            ]);
        $melResponseDecoded = json_decode($melResponse, true);
        //Check if user was not found
        if($melResponseDecoded["showing_records"] == 0)
        {
            Log::error('User not found in MEL', [$profile_id]);
            return response()->json(["result" => "failed","errorMessage" => 'User not found in MEL'], 400);
        }
        $melData = $melResponseDecoded["data"][0];


        $user = User::find((string)$melData["user_id"]);
        if($user == null)
        {
            $userData = array("user_id" => (string)$melData["user_id"], "name" => $melData["name"], "email" => $melData["email"], "country" => $melData["location"], "organization" => $melData["partner_full_name"]);
            Log::info('User not found: ', [$userData["user_id"]]);
            return response()->json(["result" => "ok", "exists" => false, "data" => $userData], 404);
        }
        else{
            $userData = array("user_id" => (string)$melData["user_id"]);
            Log::info('User found: ', [$userData["user_id"]]);
            return response()->json(["result" => "ok","exists" => true, "data" => $userData], 201);
        }
    }

    //Retrieve existing user
    public function getUser($user_id)
    {
        //Validation on user_id
        $validator = Validator::make(["user_id" => $user_id], [
            'user_id' => 'required|exists:App\Models\User,userId|string|numeric',
        ]);
        if ($validator->fails()) {
            Log::error('Resource Validation Failed: ', [$validator->errors(), $user_id]);
            return response()->json(["result" => "failed","errorMessage" => $validator->errors()], 400);
        }

        $user = User::find($user_id);

        Log::info('Retrieving user with id: ', [$user->userId]);
        return response()->json(["result" => "ok", "user" => $user], 201);
    }

    //Get all users from Redis (lazy loading)    {admin}
    public function getUsersPaginated(Request $request)
    {
        $rules = array(
            'user_id' => 'required|exists:App\Models\User,userId|string|numeric',
            'offset' => 'required|int',
            'limit' => 'required|int|gt:offset',
            'order' => 'required|string'
        );

        $validator = Validator::make($request->toArray(),$rules);
        if ($validator->fails()) {
            Log::error('Request Validation Failed: ', [$validator->errors(), $request->toArray()]);
            return response()->json(["result" => "failed", "errorMessage" => $validator->errors()], 400);
        }

        //Check if user is admin

        //Check user is admin
        $adminUser = User::find($request->user_id);
        if(in_array("Administrator", $adminUser->permissions))
        {
            Log::info('Fetch all requested by administrator: ', [$request->user_id]);
        }
        else{
            Log::warning('User does not have administrator privileges: ', $adminUser->permissions);
            return response()->json(["result" => "failed","errorMessage" => 'User does not have administrator privileges: '], 202);
        }

        //Redis
        /*
        $client = new PredisClient([
            'scheme' => 'tcp',
            'host'   => env('REDIS_HOST',''),
            'port'   => env('REDIS_PORT',''),
        ]);

        $userCount = $client->zcount(env('REDIS_USERS_KEY',''), -INF, +INF);
        $resultRedis = $client->zrange(env('REDIS_USERS_KEY',''), $request->offset, $request->limit);
        $usersFromRedis = array();
        foreach($resultRedis as $singleUser)
        {
            array_push($usersFromRedis, json_decode($singleUser, true));
        }*/

        if(strcmp($request->order, 'ascending'))
        {
            $users = User::orderBy('fullName', 'asc')->offset($request->offset)->limit($request->limit)->get();
        }elseif (strcmp($request->order, 'descending'))
        {
            $users = User::orderBy('fullName', 'desc')->offset($request->offset)->limit($request->limit)->get();
        }
        else
        {
            Log::error("Wrong parameters given", [$request->toArray()]);
            return response()->json(["result" => "failed","errorMessage" => 'Wrong parameters given'], 202);
        }


        $userCount = User::count();
        Log::info("Retrieving users from mongo paginated");
        return response()->json(["result" => "ok", "users" => $users, "total_users" => $userCount], 200);
    }

    //Retrieve all the users with "reviewer" permission     {admin}
    public function getAllReviewers($user_id)
    {
        //Validating the input
        $validator = Validator::make(["user_id" => $user_id], [
            'user_id' => 'required|exists:App\Models\User,userId|string|numeric',
        ]);
        if ($validator->fails()) {
            Log::error('Resource Validation Failed: ', [$validator->errors(), $user_id]);
            return response()->json(["result" => "failed","errorMessage" => $validator->errors()], 400);
        }

        //Check user is admin
        $adminUser = User::find($user_id);
        if(in_array("Administrator", $adminUser->permissions))
        {
            Log::info('Fetch all requested by administrator: ', [$user_id]);
        }
        else{
            Log::warning('User does not have administrator privileges: ', $adminUser->permissions);
            return response()->json(["result" => "failed","errorMessage" => 'User does not have administrator privileges'], 202);
        }

        $reviewers = User::where('permissions', "Reviewer")->get();
        Log::info('Retrieving all reviewers ');
        return response()->json(["result" => "ok", "reviewers" => $reviewers], 201);

    }

    //Retrieve all the users with "Scaling Readiness Expert" permission     {admin}
    public function getAllScalingReadinessExperts($user_id)
    {
        //Validating the input
        $validator = Validator::make(["user_id" => $user_id], [
            'user_id' => 'required|exists:App\Models\User,userId|string|numeric',
        ]);
        if ($validator->fails()) {
            Log::error('Resource Validation Failed: ', [$validator->errors(), $user_id]);
            return response()->json(["result" => "failed","errorMessage" => $validator->errors()], 400);
        }

        //Check user is admin
        $adminUser = User::find($user_id);
        if(in_array("Administrator", $adminUser->permissions))
        {
            Log::info('Fetch all requested by administrator: ', [$user_id]);
        }
        else{
            Log::warning('User does not have administrator privileges: ', $adminUser->permissions);
            return response()->json(["result" => "failed","errorMessage" => 'User does not have administrator privileges'], 202);
        }

        $scalingReadinesExperts = User::where('permissions', "Scaling Readiness Expert")->get();
        Log::info('Retrieving all Scaling Readiness Expert ');
        return response()->json(["result" => "ok", "reviewers" => $scalingReadinesExperts], 201);

    }

    //Fetch autocomplete suggestions from MEL based on name (10 results max)   {user}
    public function autocompleteUsers(Request $request)
    {
        //Validating the input
        $validator = Validator::make(["users_name" => $request->autocomplete], [
            'users_name' => 'required|string',
        ]);
        if ($validator->fails()) {
            Log::error('Resource Validation Failed: ', [$validator->errors(), $request->autocomplete]);
            return response()->json(["result" => "failed","errorMessage" => $validator->errors()], 400);
        }

        //Query user data from MEL based on $users_name with fuzziness
        $autocompleteResponse = Http::withHeaders(["Authorization" =>env('MEL_API_KEY','')])
            ->post(env('MEL_SEARCH_USERS',''), [
                "query" => [
                    "bool" => [
                        "must" => [
                            [
                                "match" => [
                                    "name" => [
                                        "query" => $request->autocomplete,
                                        "fuzziness" => 1
                                    ]
                                ]
                            ],
                            [
                                "term" => [
                                    "user_is_active" => [
                                        "value" => "1"
                                    ]
                                ]
                            ],
                            [
                                "term" => [
                                    "profile_is_active" => [
                                        "value" => "1"
                                    ]
                                ]
                            ]
                        ]
                    ]
                ]
            ]);

        $autocompleteArray = json_decode($autocompleteResponse, true);
        $autocomplete = array();
        Log::info("These are the autocomplete suggestions for name", $autocompleteArray);
        foreach ($autocompleteArray["data"] as $usersSuggested)
        {
            if(str_contains($usersSuggested["photo"], "-user-"))
            {
                $userPhoto = $usersSuggested["photo"];
            }
            else{
                //Hardcoded the url used by MEL
                $userPhoto = "https://mel.cgiar.org/graph/getimage/width/164/height/164/image/-user-".$usersSuggested["photo"];
            }
            $desiredData = array("name" => $usersSuggested["name"], "photo" => $userPhoto);
            array_push($autocomplete, $desiredData);
        }

        return response()->json(["result" => "ok", "autocomplete_suggestions" => $autocomplete], 201);
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
            'permissions' => 'present|array',
            'email' => 'present|nullable|string',
            'fullName' => 'present|nullable|string'
        );

        //Redis
        $client = new PredisClient([
            'scheme' => 'tcp',
            'host' => env('REDIS_HOST', ''),
            'port' => env('REDIS_PORT', ''),
        ]);

        $user = new User;

        $user->userId = $request->user_id;                  //Users ID
        $user->role = "";                                   //User role ("" default value)
        $user->permissions = ["User"];                      //User permission ("User" default value)
        $user->fullName = $request->name;
        $user->email = $request->email;
        $user->country = $request->country;
        $user->organization = $request->organization;
        $user->website = "";
        $user->organizationLogo = "";

        //Validation on the final user entities
        $validator = Validator::make($user->attributesToArray(),$rules);
        if ($validator->fails()) {
            Log::error('Resource Validation Failed: ', [$validator->errors(), $request->toArray()]);
            return response()->json(["result" => "failed","errorMessage" => $validator->errors()], 400);
        }

        //Save to database and log
        $user->save();
        Log::info('Adding new user with id: ', [$user->userId, $request->toArray()]);


        //Set redis data
        $score = unpack('I*', $user->fullName)[1];
        $redisUser = array("user_id" => $user->userId, "permissions" => $user->permissions,"name" => $user->fullName);
        //Αdd to Redis
        $redisUser = json_encode($redisUser);
        $resultAdd = $client->zadd(env('REDIS_USERS_KEY',''), [$redisUser =>  $score]);
        if($resultAdd == 0)
        {
            Log::error('New user not added to Redis, already exists', [$redisUser]);
        }
        Log::info('New user added to Redis', [$redisUser]);

        return response()->json(["result" => "ok"], 201);
    }

    /*
    //PUT
    */
    //Update user roles         {user}
    public function editUser(Request $request)
    {
        //Validating the request
        $rules = array(
            'user_id' => 'required|exists:App\Models\User,userId|string|numeric',         //ID must be present and existing in the database
            'role' => 'present|nullable|string',
            'website' => 'present|nullable|string',
            'organization_logo' => 'present|nullable|string'
        );
        $validator = Validator::make($request->toArray(),$rules);
        if ($validator->fails()) {
            Log::error('Request Validation Failed: ', [$validator->errors(), $request->toArray()]);
            return response()->json(["result" => "failed", "errorMessage" => $validator->errors()], 400);
        }

        //Updating the role
        $user = User::find($request->user_id);
        $user->role = $request->role;
        $user->website = $request->website;
        $user->organizationLogo = $request->organization_logo;
        $user->save();
        Log::info('Updating user information with id: ', [$user->user_id, $user->role]);
        return response()->json(["result" => "ok", "user" => $user], 201);
    }

    //Update user permissions      {admin}
    public function updatePermissionsUser(Request $request)
    {
        //Request validation
        $rules = array(
            'user_id' => 'present|exists:App\Models\User,userId|string|numeric',         //ID must be present and existing in the database
            'target_id' => 'present|exists:App\Models\User,userId|string|numeric',       // >>       >>              >>          >>
            'permissions' => 'present|array'
        );
        $validator = Validator::make($request->toArray(),$rules);
        if ($validator->fails()) {
            Log::error('Request Validation Failed: ', [$validator->errors(), $request->toArray()]);
            return response()->json(["result" => "failed","errorMessage" => $validator->errors()], 400);
        }

        //Redis
        $client = new PredisClient([
            'scheme' => 'tcp',
            'host' => env('REDIS_HOST', ''),
            'port' => env('REDIS_PORT', ''),
        ]);

        //Check if usedId has admin privileges
        $adminUser = User::find($request->user_id);
        if(in_array("Administrator", $adminUser->permissions))
        {
            Log::info('Update requested by administrator: ', [$request->user_id]);
        }
        else{
            Log::warning('User does not have administrator privileges: ', $adminUser->permissions);
            return response()->json(["result" => "failed","errorMessage" => 'User does not have administrator privileges'], 202);
        }
        $user = User::find($request->target_id);
        $newPermissions = $request->permissions;
        //Check if targetId has admin privileges and if they are included in the request
        if(in_array("Administrator", $user->permissions))
        {
            if(in_array("Administrator", $request->permissions))
            {
                Log::info('Expected behaviour by update');
            }
            else{
                Log::warning('Administrator rights not given, adding..... ', $request->permissions);
                //add string to array
                array_push($newPermissions, "Administrator");
            }
        }
        else
        {
            if(in_array("Administrator", $request->permissions))
            {
                Log::warning('Target user does not have administrator rights: ', $user->permissions);
                return response()->json(["result" => "failed","errorMessage" => 'Target user does not have administrator rights: '], 202);
            }
            else{
                Log::info('Expected behaviour by update');
            }
        }

        //Updating the permissions of the targetId
        $user = User::find($request->target_id);
        $user->permissions = $newPermissions;

        //Prepare redis data
        $score = unpack('I*', $user->fullName)[1];
        $redisUser = array("user_id" => $user->userId, "permissions" => $user->permissions,"name" => $user->fullName);
        $deleteResult = $client->zRemRangeByScore(env('REDIS_USERS_KEY',''), $score, $score);
        if($deleteResult == 0)
        {
            return response('I didnt delete a thing');
        }
        //Αdd to Redis
        $redisUser = json_encode($redisUser);
        $resultAdd = $client->zadd(env('REDIS_USERS_KEY',''), [$redisUser =>  $score]);
        if($resultAdd == 0)
        {
            Log::info('Mel user not added to redis, already exists', [$redisUser]);
        }


        //Save new data, log and return
        $user->save();
        Log::info('Updating user permissions with id: ', [$user->userId, $user->permissions]);
        return response()->json(["result" => "ok"], 201);

    }


    /*
   //PLAYAROUND
   */

    public function morningHead($timestamp)
    {
        $hardDate = date('Y-m-d H:i:s', time());
        $date = date('Y-m-d H:i:s', (int)$timestamp);
        return response()->json(["hardCodedDate" => $hardDate, "date" => $date], 201);
    }


}
