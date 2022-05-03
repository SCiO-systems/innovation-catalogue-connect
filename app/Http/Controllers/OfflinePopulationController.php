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

        $deleteResult = $client->del(env('REDIS_USERS_KEY',''));
        if($deleteResult == 0)
        {
            return response('I didnt delete a thing');
        }

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
                    $user->country = $melUser["location"];
                    $user->organization = $melUser["partner_full_name"];
                    $user->website = "";
                    $user->organizationLogo = "";

                    //Save to database and log
                    Log::info('Adding new user with id: ', [$user->userId]);
                    $user->save();
                }
                else{
                    Log::info('User already exists', [(string)$melUser["user_id"]]);
                }

                //Set redis data
                $score = unpack('I*', $user->fullName)[1];
                $redisUser = array("user_id" => $user->userId, "permissions" => $user->permissions,"name" => $user->fullName);

                //Αdd to Redis
                $redisUser = json_encode($redisUser);
                $resultAdd = $client->zadd(env('REDIS_USERS_KEY',''), [$redisUser =>  $score]);
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
        $testUser = array("user_id" => "25120", "permissions" => ["User", "Reviewer", "Administrator"],"name" => "George Gkoumas");
        $testUser = json_encode($testUser);
        $resultAdd = $client->zadd(env('REDIS_USERS_KEY',''), [$testUser =>  $score]);

        /*
        $score = unpack('I*', "Quang Bao Le")[1];
        $testUser = array("user_id" => "102", "permissions" => ["User", "Reviewer", "Administrator"],"name" => "Quang Bao Le");
        $testUser = json_encode($testUser);
        $resultAdd = $client->zadd('mel_users_innovation', [$testUser =>  $score]);*/
        ////////////////////////////////////

        $resultRedis = $client->zrange(env('REDIS_USERS_KEY',''), 0, -1);
        $usersFromRedis = array();
        foreach($resultRedis as $singleUser)
        {
            array_push($usersFromRedis, json_decode($singleUser, true));
        }

        Log::info("Databases populated");
        return response()->json(["result" => "ok", "key_used" => env('REDIS_USERS_KEY',''), "users" => $usersFromRedis], 201);
    }

    public function populateVocabularies()
    {
        ini_set('max_execution_time', 400);
        //Redis
        $client = new PredisClient([
            'scheme' => 'tcp',
            'host' => env('REDIS_HOST', ''),
            'port' => env('REDIS_PORT', ''),
        ]);

        $allKeys = $client->keys('*');

        $deleteResult = $client->del('clarisa_vocab_organization');
        if($deleteResult == 0)
        {
            return response('I didnt delete a thing');
        }

        Log::info("I REACHED THIS POINT YEEE BOIII");
        $result = Http::redisFetch();
        $vocabToArray = (array)$result;
        $organizations = array();
        foreach ($vocabToArray["clarisa_organization"] as $fields)
        {
            $valueProperty = array("id" => $fields->code, "value" => $fields->name);
            //array_push($organizations, $valueProperty);
            $valueProperty = json_encode($valueProperty);
            $resultAdd = $client->zadd('clarisa_vocab_organization', [$valueProperty =>  (int)$fields->code]);
            Log::info("I AM ADDING", [$fields->code]);
            if($resultAdd == 0)
            {
                Log::info('Element not added to redis, already exists', [$valueProperty]);
            }
        }
        //$vocabToArray = json_encode($vocabToArray);
        $resultRedis = $client->zrange('clarisa_vocab_organization', 0, -1);
        Log::info("I REACHED THIS ABOUT TO RETURN");
        return response()->json(["result" => "ok", "organizations" => $resultRedis], 200);
    }
}
