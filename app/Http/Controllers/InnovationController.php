<?php

namespace App\Http\Controllers;

use App\Models\Innovation;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class InnovationController extends Controller
{
    /*
    //GET
    */
    //Get all innovations from the collection    {admin}
    public function  getAllInnovations($userId)
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

        $innovations = Innovation::all();
        Log::info('Retrieving all innovations ');
        return response()->json(["result" => "ok", "innovations" => $innovations], 201);
    }

    //Get all user innovations from the collection {user}
    public function  getAllUserInnovations($userId)
    {
        $innovations = Innovation::where('userIds', $userId)->where('deleted', false)->get();
        Log::info('Retrieving all user innovations ', [$userId]);
        return response()->json(["result" => "ok", "innovations" => $innovations], 201);
    }

    /*
    //POST
    */

    //Add a new innovation to the collection, given the userIds,status and formData    {user}
    public function insertInnovation(Request $request)
    {
        $innovation = new Innovation;
        $requestRules = array(
            'user_id' => 'required|string|numeric',
            'status' => [
                'required', Rule::in(['DRAFT','READY']),
            ],
        );


        //Validation and safety catches
        //for later(maybe)
        $validator = Validator::make($request->toArray(),$requestRules);
        if ($validator->fails()) {
            Log::error('Request Validation Failed: ', [$validator->errors(), $request->toArray()]);
            return response()->json(["result" => "failed","errorMessage" => $validator->errors()], 400);
        }

        //generate uuid with INNOV- prefix included
        $uuid = Str::uuid()->toString();
        $innovation->innovId = 'INNOV-'.$uuid;

        //Data assignment from resource and local generation
        $innovation->userIds = [$request->user_id];
        $innovation->status = $request->status;                 //DRAFT || READY TODO:(??ask nick??)
        $innovation->version = 1;                               //First time creating so version 1
        $innovation->persistId = [];                            //Later will add hdl, empty for now
        $innovation->deleted = false;                           //True for soft deleted innovations
        $innovation->reviewerIds = [];                          //Array of strings, empty on initialise
        $innovation->comments = " ";                            //Reviewer's comments, empty on initialise
        $innovation->formData = $request->form_data;            //The actual innovation data

        //Validation on the final user entities
        //for later

        //Save to database and log
        $innovation->save();
        Log::info('Adding new innovation with id: ', [$innovation->innovId, $request->toArray() ]);

        return response()->json(["result" => "ok"], 201);

    }

    /*
    //DELETE
    */

    public function deleteInnovation($innovId)
    {
        //TODO: check the user has delete priviledges (owns the innovation || admin)
        $validator = Validator::make(["innovId" => $innovId], [
            'innovId' => 'required|exists:App\Models\Innovation,innovId|string',
        ]);
        if ($validator->fails()) {
            Log::error('Resource Validation Failed: ', [$validator->errors(), $innovId]);
            return response()->json(["result" => "failed","errorMessage" => $validator->errors()], 400);
        }

        $innovation = Innovation::where('innovId', $innovId)
                                ->where('deleted', false)
                                ->where(function ($query) {
                                    $query->where('status', "DRAFT")->
                                            orWhere('status', "READY");
                                    })
                                ->first();

        $innovation->deleted = true;

        Log::info('Deleting innovation', [$innovation]);
        $innovation->save();


        return response()->json(["result" => "ok"], 201);
    }



}
