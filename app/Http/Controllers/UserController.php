<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;



class UserController extends Controller
{
    /*
    //GET
    */

    //Check if a user exists
    public function existsUser($user_id)
    {
        $user = User::find($user_id);
        if($user == null)
        {
            Log::info('User not found: ', [$user_id]);
            return response()->json(["exists" => false], 404);
        }
        else{
            Log::info('User found: ', [$user_id]);
            return response()->json(["exists" => true], 201);
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

    //Retrieve all the existing users
    public function getUsers($user_id)
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
            Log::warning('User does not have administrator rights: ', $adminUser->permissions);
            return response()->json(["result" => "failed","errorMessage" => 'User does not have administrator rights: '], 202);
        }

        $users =User::all();
        //$users = User::where('permissions', "Administrator")->where('role', "Evaluator")->get();
        Log::info('Retrieving all users ');
        return response()->json(["result" => "ok", "users" => $users], 201);
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
            Log::warning('User does not have administrator rights: ', $adminUser->permissions);
            return response()->json(["result" => "failed","errorMessage" => 'User does not have administrator rights: '], 202);
        }

        $reviewers = User::where('permissions', "Reviewer")->get();
        Log::info('Retrieving all reviewers ');
        return response()->json(["result" => "ok", "reviewers" => $reviewers], 201);

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

        $user->userId = $request->user_id;                   //Users ID
        $user->role = "";                                   //User role ("" default value)
        $user->permissions = ["User"];                      //User permission ("user" default value)

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
            'user_id' => 'required|exists:App\Models\User,userId|string|numeric',         //ID must be present and existing in the database
            'role' => 'present|nullable|string'
        );
        $validator = Validator::make($request->toArray(),$rules);
        if ($validator->fails()) {
            Log::error('Request Validation Failed: ', [$validator->errors(), $request->toArray()]);
            return response()->json(["result" => "failed", "errorMessage" => $validator->errors()], 400);
        }

        //Updating the role
        $user = User::find($request->user_id);
        $user->role = $request->role;
        $user->save();
        Log::info('Updating user role with id: ', [$user->user_id, $user->role]);
        return response()->json(["result" => "ok"], 201);
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

        //Check if usedId has admin privileges
        $adminUser = User::find($request->user_id);
        if(in_array("Administrator", $adminUser->permissions))
        {
            Log::info('Update requested by administrator: ', [$request->user_id]);
        }
        else{
            Log::warning('User does not have administrator rights: ', $adminUser->permissions);
            return response()->json(["result" => "failed","errorMessage" => 'User does not have administrator rights: '], 202);
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

        //Save new data, log and return
        $user->save();
        Log::info('Updating user permissions with id: ', [$user->user_id, $user->permissions]);
        return response()->json(["result" => "ok"], 201);

    }


    /*
   //PLAYAROUND
   */
    //Play around on clarisa vocabularies
    public function playaround()
    {
        $result = Http::redisFetch();
        $usefulHeaders = array("clarisa_technical_field",
            "clarisa_business_category",
            "clarisa_beneficiaries",
            "clarisa_users",
            "clarisa_investment_type",
            "clarisa_action_areas",
            "clarisa_innovation_readiness_levels",
            "clarisa_governance_type",
            "clarisa_environmental_benefits",
            "clarisa_administrative_scale",
            "clarisa_innovation_type",
            "clarisa_countries",
            "clarisa_technology_development_stage"
            );

        //$extraArray = (array)$result->clarisa_technical_field;
        //Log::info("HERES THE VOCAB", [$extraArray[0]]);

        $vocabToArray = (array)$result;
        $clarisa_vocabulary = array();
        //Log::info("HERE'S THE VOCAB", $vocabToArray["clarisa_innovation_type"]);
        //Log::info("HERE'S THE VOCAB", $vocabToArray["clarisa_business_category"]);
        /*foreach ($usefulHeaders as $header)
        {
            //Log::info("HERE'S THE VOCAB", $vocabToArray[$header]);
            $value = array();
            foreach ($vocabToArray[$header] as $fields)
            {
                if(strcmp($header, "clarisa_administrative_scale") == 0 || strcmp($header, "clarisa_innovation_type") == 0)
                {
                    //Log::info("HERE'S THE HEADER", [$header]);
                    $valueProperty = array("id" => $fields->code, "value" => $fields->name);
                }
                elseif (strcmp($header, "clarisa_countries") == 0)
                {
                    $valueProperty = array("id" => $fields->isoAlpha2, "value" => $fields->name);
                }
                elseif (strcmp($header, "clarisa_technology_development_stage") == 0)
                {
                    $valueProperty = array("id" => $fields->id, "value" => $fields->officialCode." ".$fields->name);
                }
                else{
                    $valueProperty = array("id" => $fields->id, "value" => $fields->name);
                }
                array_push($value, $valueProperty);

            }
            $singleHeader = array("header" => $header, "value" => $value);
            array_push($clarisa_vocabulary, $singleHeader);
        }*/

        //Log::info("HERE'S THE VOCAB", $vocabToArray["clarisa_sdg_targets"]);
        $sdgTargetPropertiesNames = array();
        foreach ($vocabToArray["clarisa_sdg_targets"] as $sdgProperties) //sdgProperties is object
        {
            $sdgTargetPropertiesValues[$sdgProperties->sdg->usndCode][] = array("id" => $sdgProperties->id , "value" => $sdgProperties->sdgTargetCode." - ".$sdgProperties->sdgTarget);
            if(!isset($sdgTargetPropertiesNames[$sdgProperties->sdg->usndCode]))
            {
                $sdgTargetPropertiesNames[$sdgProperties->sdg->usndCode][] = $sdgProperties->sdg->fullName;
            }
        }

        $sdgTargetValue = array();
        foreach ($sdgTargetPropertiesValues as $targetId => $targets) //$targets is array of sdg_target objects
        {
            //Log::info("HERE'S THE VOCAB", ["id" => $targetId, "title" => $sdgTargetsNames[$targetId][0], "value" => $targets]);
            $singleTarget = array("id" => $targetId, "title" => $sdgTargetPropertiesNames[$targetId][0], "value" => $targets);
            array_push($sdgTargetValue, $singleTarget);
        }
        $singleHeader = array("header" => "clarisa_sdg_targets", "value" => $sdgTargetValue);
        array_push($clarisa_vocabulary, $singleHeader);


        $indicatorTitles = array();
        foreach ($vocabToArray["clarisa_impact_areas_indicators"] as $indicatorProperties)
        {
            $singleIndicatorPropertiesValues[$indicatorProperties->impactAreaId][] = array("id" => $indicatorProperties->indicatorId, "value" => $indicatorProperties->indicatorStatement);
            if(!isset($indicatorTitles[$indicatorProperties->impactAreaId]))
            {
                $indicatorTitles[$indicatorProperties->impactAreaId][] = $indicatorProperties->impactAreaName;
            }
        }

        $impactAreaIndicatorValue = array();
        foreach ($singleIndicatorPropertiesValues as $impactAreaId => $impactAreaIndicators)
        {
            $singleIndicator = array("id" => $impactAreaId, "title" => $indicatorTitles[$impactAreaId][0], "value" => $impactAreaIndicators);
            array_push($impactAreaIndicatorValue, $singleIndicator);
        }
        $singleHeader = array("header" => "clarisa_impact_areas_indicators", "value" => $impactAreaIndicatorValue);
        array_push($clarisa_vocabulary, $singleHeader);

        return response()->json($clarisa_vocabulary, 201);
    }



    public function morningHead($timestamp)
    {
        $hardDate = date('Y-m-d H:i:s', time());
        $date = date('Y-m-d H:i:s', (int)$timestamp);
        return response()->json(["hardCodedDate" => $hardDate, "date" => $date], 201);
    }






}
