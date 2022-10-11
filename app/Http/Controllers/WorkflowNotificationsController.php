<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class WorkflowNotificationsController extends Controller
{

    public function sendNotificationEmail($innovation_id, $workflowState, $user_id , $title)
    {
        //$user_id, $workflowState
        //Standard values for the API call
        $url = env('SEND_IN_BLUE_URL', '');

        //Log::info("THIS HAS BEEN CALLED");

        $curl = curl_init($url);
        curl_setopt($curl, CURLOPT_URL, $url);
        curl_setopt($curl, CURLOPT_POST, true);
        curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);

        if (env('APP_STATE', '') == 'prod')
        {
            $apiKey = env('SEND_IN_BLUE_PROD_KEY', '');
        }
        else
        {
            $apiKey = env('SEND_IN_BLUE_DEV_KEY', '');
        }

        $headers = array(
            "accept: application/json",
            "api-key: ".$apiKey,
            "content-type: application/json",
        );
        curl_setopt($curl, CURLOPT_HTTPHEADER, $headers);


        //Find the recipient user details
        $findUser = User::find($user_id);
        if($findUser == null)
        {
            Log::warning("User not found", [$user_id]);
        }

        $sendToEmail = $findUser->email;
        $sendToName = $findUser->fullName;
        Log::info("User about to be notified", [$sendToEmail,$sendToName]);
        if(strcmp($sendToEmail, "giorgos@scio.systems") == 0)
        {
            $sendToEmail = "apostolis@scio.systems";
        }


        //Transform the uuid for the 7th workflow state, it has to be used in the search page
        $counter = 0;
        $transformedValue = "";
        $explodedUUID = explode("-",$innovation_id);
        Log::info("This is after the explosion", [$explodedUUID]);
        foreach ($explodedUUID as $subId)
        {
            if($counter != 0)
            {
                $transformedValue = $transformedValue.$subId;
            }
            $counter++;
        }
        $uuidTransformed = $transformedValue;
        Log::info("This is the transformed id", [$uuidTransformed]);
        $innovationSearchUrl = "https://staging.innovation.scio.services/".$uuidTransformed;


        switch ($workflowState)
        {
            case 1:
                $subject = "New innovation submitted - Congratulations!";
                $body = "A new innovation has been submitted. It will be assigned to an available reviewer and commented within the next 4 weeks.";
                Log::info($body);
                break;
            case 2:
                $subject = "New Innovation Submitted – Please Assign a Reviewer ";
                $body = "A new innovation has been submitted. Please login to the RTB Innovation Catalog and assign it to a reviewer for review.";
                Log::info($body);
                break;
            case 3:
                $subject = "New Innovation Assigned for Review";
                $body = "A new innovation has been assigned to you for review. Please login to the RTB Innovation Catalog and directly edit the innovation or provide your comments.";
                Log::info($body);
                break;
            case 4:
                $subject = "New Innovation for Final Decision";
                $body = "A new innovation has been reviewed and final decision is pending. Please login to the RTB Innovation Catalog and take the final decision for publishing it, rejecting it or request scaling readiness assessment by a relevant expert.";
                Log::info($body);
                break;
            case 5:
                $subject = "New Innovation for Scaling Readiness Assessment";
                $body = "A new innovation has been assigned to you for assessing its scaling readiness. Please login to the RTB Innovation Catalog and assess the innovation.";
                Log::info($body);
                break;
            case 6:
                $subject = "Your innovation – Request for Revisions ";
                $body = "Your submitted innovation entitled ".$title." has been reviewed and revisions have been requested. Please login to the RTB Innovation Catalog and address the review comments. ";
                Log::info($body);
                break;
            case 7:
                $subject = "Your innovation – Published!";
                $body = "Your submitted innovation entitled ".$title." has been reviewed and it is now published. You can access it here: ".$innovationSearchUrl;
                Log::info($body);
                break;
            default:
                Log::warning("No case was triggered, state out of bounds", [$workflowState]);
        }


        $data=[
            "sender" => [
                "name" => "Innovation Catalog",
              "email" => "innovation_noreply@scio.systems"
            ],
            "to" => [
                [
                    "email" => $sendToEmail,
                    "name" => $sendToName
                ]
            ],
            "subject" => $subject,
            "htmlContent" => $body
        ];
        $data = json_encode($data);
        curl_setopt($curl, CURLOPT_POSTFIELDS, $data);
        $resp = curl_exec($curl);

        Log::info("Email has been sent to: ", [$sendToEmail, $resp]);
        return response()->json(["result" => $resp], 200);

    }
}
