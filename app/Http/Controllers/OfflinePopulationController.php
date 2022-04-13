<?php

namespace App\Http\Controllers;

use App\Models\User;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Predis\Client as PredisClient;

class OfflinePopulationController extends Controller
{
    public function populateUsers()
    {
        //Redis
        $client = new PredisClient([
            'scheme' => 'tcp',
            'host' => env('REDIS_HOST', ''),
            'port' => env('REDIS_PORT', ''),
        ]);

        /*$deleteResult = $client->del('mel_users_innovation');
        if($deleteResult == 0)
        {
            return response('I didnt delete a thing');
        }*/

        //Query all user data, filter based on activity and order based on user_id
        $properBody = [
            "query" => [
                "bool" => [
                    "must" => [
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
            ],
            "sort" => [
                [
                    "user_id" => [
                        "order" => "asc"
                    ]
                ]
            ]
        ];

        //Http request to MEL
        $userDataResponse = Http::withHeaders(["Authorization" =>env('MEL_API_KEY','')])
            ->post(env('MEL_SEARCH_USERS',''), $properBody);

        $userDataResponse = json_decode($userDataResponse, true);
        Log::info("Scroll_id given" , [$userDataResponse["_scroll_id"]]);
        Log::info("Records filtered ", [$userDataResponse["filtered_records"]]);

        $allUserDataFinal = $userDataResponse["data"];
        //$recordsExpected = $userDataResponse["filtered_records"];
        //Solution for now, just do it for records filtered = 50
        $recordsExpected = 50;
        //Construct the scrollId
        //$scrollId = substr($userDataResponse["_scroll_id"], 0, -2);
        //$scrollId = "&scroll_id=".$scrollId."%3D%3D";
        $scrollId = "&scroll_id=".$userDataResponse["_scroll_id"];
        Log::info("Scroll_id constructed" , [$scrollId]);
        while ($recordsExpected > 10)
        {
            Log::info("ANOTHER 10 BOYYYY", [$recordsExpected]);

            $userDataResponse = Http::withHeaders(["Authorization" =>env('MEL_API_KEY','')])
                ->post(env('MEL_SEARCH_USERS','').$scrollId, $properBody);
            $userDataResponse = json_decode($userDataResponse, true);
            Log::info("CHECK THIS OUT BROCOLOCO", $userDataResponse);
            $allUserDataFinal = array_merge($allUserDataFinal, $userDataResponse["data"]);
            //$scrollId = substr($userDataResponse["_scroll_id"], 0, -2);
            //$scrollId = "&scroll_id=".$scrollId."%3D%3D";
            $scrollId = "&scroll_id=".$userDataResponse["_scroll_id"];
            Log::info("Scroll_id constructed" , [$scrollId]);
            $recordsExpected -= 10;
        }

        foreach ($allUserDataFinal as $melUser)
        {
            if($melUser["name"] != null && $melUser["email"] != null)
            {
                $user = User::find((string)$melUser["user_id"]);
                if($user == null)
                {
                    $user = new User;

                    $user->userId = (string)$melUser["user_id"];                //Users ID
                    $user->permissions = ["User"];                              //User permission ("user" default value)
                    $user->role = "";                                           //User role ("" default value)
                    $user->fullName = $melUser["name"];
                    $user->email = $melUser["email"];

                    //Save to database and log
                    Log::info('Adding new user with id: ', [$user->userId]);
                    $user->save();
                }
                else{
                    Log::info('User already exists', [(string)$melUser["user_id"]]);
                }

                //Set redis data
                $score = unpack('I*', $user->fullName)[1];
                $redisUser = array("user_id" => $user->userId, "permissions" => $user->permissions,"name" => $user->fullName, "email" => $user->email);

                //Αdd to Redis
                $redisUser = json_encode($redisUser);
                $resultAdd = $client->zadd('mel_users_innovation', [$redisUser =>  $score]);
                if($resultAdd == 0)
                {
                    Log::info('Mel user not added to redis, already exists', [$redisUser]);
                }
            }
            else{
                Log::info('Credentials were null', [(string)$melUser["user_id"]]);
            }
        }

        /////////////////////////////////////
        //Temporary fix, add specific profiles
        $score = unpack('I*', "George Gkoumas")[1];
        $testUser = array("user_id" => "25120", "permissions" => ["User", "Reviewer", "Administrator"],"name" => "George Gkoumas", "email" => "giorgos@scio.systems");
        $testUser = json_encode($testUser);
        $resultAdd = $client->zadd('mel_users_innovation', [$testUser =>  $score]);

        $score = unpack('I*', "Quang Bao Le")[1];
        $testUser = array("user_id" => "102", "permissions" => ["User", "Reviewer", "Administrator"],"name" => "Quang Bao Le", "email" => "Q.Le@cgiar.org");
        $testUser = json_encode($testUser);
        $resultAdd = $client->zadd('mel_users_innovation', [$testUser =>  $score]);
        ////////////////////////////////////

        $resultRedis = $client->zrange('mel_users_innovation', 0, -1);
        $usersFromRedis = array();
        foreach($resultRedis as $singleUser)
        {
            array_push($usersFromRedis, json_decode($singleUser, true));
        }

        Log::info("Databases populated");
        return response()->json(["result" => "ok", "users" => $usersFromRedis], 201);
    }
}
