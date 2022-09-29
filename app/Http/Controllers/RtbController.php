<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use App\Http\Traits\RequestHelperTrait;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\Rule;

class RtbController extends Controller
{
    //use RequestHelperTrait;

    public function rtb_search(Request $request)
    {
        /*if(env('APP_STATE', '') == 'dev')
        {
            $urlString = '/api/v2/search/dev/rtb/innovations';
        }
        else
        {
            $urlString = '/api/v2/search/rtb/innovations';
        }*/

        $urlString = '/api/v2/search/rtb/innovations';
        $header = ['Content-Type: application/json'];
        $body = $request->getContent();
        $response = Http::withHeaders($header)->withBody($body, 'json')->post(env('INNOVATION_ES','').$urlString);

        $json = json_decode($response);



        /*
         * Change this in order to allow the application to handle Request data instead of a JSON file (like it does for testing purposes)
         */
//        $filename = 'innovation_catalogue_dummy';
//        $path = storage_path() . "/json/${filename}.json";
//        $json = json_decode(file_get_contents($path));

        /*
         * Expected result:
         * -version
         * -results (Array)
         * --innovation_id
         * --title
         * --submitter (Object, multiple)
         * ---submitter_first_name
         * ---submitter_last_name
         * ---submitter_email
         * --summary
         * --last_updated
         *--summaries (Object)
         * ---CGIAR_Action_Areas (Array)
         *
         * */

        /*
         * $innovations is the main array that holds all the filtered data that will be passed on to the front
         * */

        if ($json->response->documents == null) {
            $innovation = null;
            $innovations[] = $innovation;
        } else {
            foreach ($json->response->documents as $dataArray) {
                $innovation = (object)[
                    'innovation_id' => $dataArray->innovation_uuid,
                    'title' => $dataArray->innovation_common_name,
                    'submitter' => (object)[
                        'submitter_first_name' => $dataArray->submitter_first_name,
                        'submitter_last_name' => $dataArray->submitter_last_name,
                        'submitter_email' => $dataArray->submitter_email
                    ],
                    'summary' => $dataArray->long_innovation_description,
                    'last_updated' => $dataArray->last_updated
                ];

                //Shove each innovation in a large innovations array

                $innovations[] = $innovation;
                //let's make the results first
            }
        }

        //All innovations
        //        dd($innovations);


        //All the data that will pass from here are:
        /*
         * summaries
         * Regions
         * Environmental_benefits
         * Type_of_innovation
         * Business_category
         * Technical_field
         * Gοvernance_type
         * CGIAR_impact_area
         * sdg
         * Countries
         * keywords
         * And I hope that's all
         * */
        foreach ($json->response->aggregations as $otherValues) {

            if ($otherValues->value_doc_count == null) {

                $dataofOtherValues = [
                    'value' => null,
                    'freq' => null
                ];

                $someOtherValues[] = $dataofOtherValues;
            }
            foreach ($otherValues->value_doc_count as $summariesData) {

                $dataofOtherValues = [
                    'value' => $summariesData->value,
                    'freq' => $summariesData->doc_count
                ];

                $someOtherValues[] = $dataofOtherValues;
            }
            $completedLoopOfOneValueData[] = [$otherValues->key => $someOtherValues];

            $someOtherValues = null;

        }

        //All of the above are not into one lovely, messy JSON (which I don't wanna touch)
        $allOtherValues = $completedLoopOfOneValueData;

        return response()->json(['data' => ['version' => '2.0', 'total' => $json->response->total, 'results' => $innovations, 'summaries' => $allOtherValues]], 200);

    }

    public function rtb_retrieve_document(Request $request)
    {
        /*
        if(env('APP_STATE', '') == 'dev')
        {
            $urlString = '/api/v1/search/dev/retrivebyid';
        }
        else
        {
            $urlString = '/api/v1/search/retrivebyid';
        }*/
        $urlString = '/api/v1/search/retrivebyid';
        $header = ['Content-Type: application/json'];
        $body = $request->getContent();
        $requiredBodyData = [
            'id' => 'required|String',
            'alias' => 'required|String',
        ];

        $response = Http::withHeaders($header)->withBody($body, 'json')->post(env('INNOVATION_ES','').$urlString);

        $json = json_decode($response);


//        dd($json);

        switch ($request->alias) {
            case ('rtb_innovations'):
                if ($json->code == 404) {
                    return response()->json(['data' => 'There was an error processing your request: ' . $json->response], 404);
                } else if ($json->code == 200) {

//            dd(json_decode($response->body()));


                    $shortNameValues = $json->response->intervention_short_name;
                    $longNameValues = $json->response->intervention_long_name;
                    $shortNameCount = count($json->response->intervention_short_name);
                    $longNameCount = count($json->response->intervention_long_name);


                    switch ($shortNameCount) {
                        case $shortNameCount == $longNameCount:

                            $intervention_name = $this->createCombinationalArray($shortNameValues, $longNameValues);
                            break;
                        case $shortNameCount < $longNameCount:

                            //shortName is smaller than longName
                            $missingValuesCount = $longNameCount - $shortNameCount;
                            $shortNameValues = $this->addMissingValues($shortNameValues, $missingValuesCount);
                            $intervention_name = $this->createCombinationalArray($shortNameValues, $longNameValues);
                            break;
                        case $shortNameCount > $longNameCount:

                            //longName is smaller than shortName
                            $missingValuesCount = $shortNameCount - $longNameCount;
                            $longNameValues = $this->addMissingValues($longNameValues, $missingValuesCount);
                            $intervention_name = $this->createCombinationalArray($shortNameValues, $longNameValues);
                            break;
                    }


                    $allresponse = [

                        'response' => [
//                    'MEL_user_id' => $json->response->user_mel_id,
                            'MEL_user_id' => $json->response->mel_user_id_who_reported_innovation,
                            'MEL_innovation_id' => $json->response->innovation_uuid,
                            'related_innovations' => $json->response->mel_ids_of_related_innovations,
                            'title' => $json->response->innovation_common_name,
                            'summary' => $json->response->long_innovation_description,
                            'business_category' => $json->response->business_category,
                            'keywords' => $json->response->related_keywords,
                            'innovation_URL' => $json->response->innovation_url,
                            'image_of_the_innovation' => $json->response->innovation_image,
                            'image_of_the_innovation_component' => $json->response->innovation_image_component,
                            'technical_field' => $json->response->technical_fields,
                            'type_of_innovation_old' => $json->response->innovation_type_old,
                            'type_of_innovation_new' => $json->response->innovation_type_new,
                            'governance_type' => $json->response->gov_type_of_solution,
                            'intervention_name' => $intervention_name,
                            'total_budget_of_interventions' => $json->response->intervention_total_budget,
                            'intervention_team_members' => $json->response->intervention_team_members,
                            'challenge_statement' => $json->response->challenge_statement,
                            'objective_statement' => $json->response->objective_statement,
                            'long_intervention_description' => $json->response->long_intervention_description,
                            'CGIAR_action_areas' => $json->response->CGIAR_action_areas_name,
                            'innovation_submitter' => [
                                'first_name' => $json->response->submitter_first_name,
                                'last_name' => $json->response->submitter_last_name,
                                'email' => $json->response->submitter_email,
                                'company' => $json->response->submitter_company_name,
                                'country' => $json->response->submitter_country,
                                'website' => $json->response->submitter_website,
                                'organizational_logo' => $json->response->organizational_logo
                            ],
                            'key_innovation_partners' => $json->response->key_innovation_partners,
                            'key_scaling_partners' => $json->response->key_scaling_partners,
                            'key_demand_partners' => $json->response->key_demand_partners,
                            'locations_of_implementation' => $json->response->locations_of_implementation,
                            'start_date' => $json->response->work_start_date,
                            'end_date' => $json->response->work_end_date,
                            'SDG_target' => $json->response->sdg_target_ui,
                            'CGIAR_impact_target' => $json->response->CGIAR_impact_targets,
                            'initiative_defined_outcome' => $json->response->initiative_defined_outcome,
                            'environmental_benefits' => $json->response->environmental_benefits,
                            'technology_development_stage' => $json->response->technology_dev_stage,
                            'technology_development_project_summary' => $json->response->technology_dev_project_summary,
                            'innovation_readiness_levels_of_the_components' => $json->response->innovation_readiness_levels_of_component,
                            'locations_of_applied_evidence' => $json->response->applied_evidence_locations,
                            'locations_of_experimental_evidence' => $json->response->experimental_evidence_locations,
                            'locations_of_impact' => $json->response->impact_evidence_locations,
                            'innovation_reference_materials' => $json->response->innovation_reference_materials,
                            'technology_appraisal' => $json->response->technology_appraisal,
                            'technology_appraisal_image' => $json->response->technology_appraisal_image,
                            'documentation_to_potential_investors' => $json->response->documentation,
                            'type_of_patent_number' => $json->response->patent_member_type,
                            'patent_number' => $json->response->patent_number,
                            'patent_office' => $json->response->patent_office,
                            'patent_knowhow_info' => $json->response->patent_know_how_info,
                            'administrative_scale_of_the_innovations' => $json->response->administrative_scale_of_innovations,
                            'users_of_the_innovation' => $json->response->innovation_users,
                            'beneficiaries_of_the_innovation' => $json->response->innovation_beneficiaries,
                            'sponsors_of_the_innovation' => $json->response->innovation_sponsors,
                            'value_added_of_the_innovation' => $json->response->value_added,
                            'main_advantages' => $json->response->main_advantages,
                            'main_disadvantages' => $json->response->main_disadvantages,
                            'investment_sought' => $json->response->investment_sought,
                            'type_of_investment_sought' => $json->response->investment_sought_type,
                            'estimated_amount_sought' => $json->response->estimated_amount_sought,
                            'innovation_use_levels_of_the_components' => $json->response->innovation_use_levels_of_components,
                            'scaling_readiness_level' => $json->response->scaling_readiness_level,
                            'scaling_readiness_score' => $json->response->scaling_readiness_score,
                            'problem_the_innovation_provides_solution' => $json->response->problem_to_be_solved

                        ]
                    ];


                    return response()->json(['data' => $allresponse], 200);

                } else if ($json->code == 400) {
                    $allresponse = [
                        'response' => [
                            'MEL_user_id' => null,
                            'MEL_innovation_id' => null,
                            'related_innovations' => null,
                            'title' => null,
                            'summary' => null,
                            'business_category' => null,
                            'keywords' => null,
                            'innovation_URL' => null,
                            'image_of_the_innovation' => null,
                            'image_of_the_innovation-component' => null /*???*/,
                            'technical_field' => null,
                            'type_of_innovation_old' => null,
                            'type_of_innovation_new' => null,
                            'governance_type' => 0,
                            'intervention_name' => 0,
                            'total_budget_of_interventions' => null,
                            'intervention_team_members' => null,
                            'challenge_statement' => null,
                            'objective_statement' => null,
                            'long_intervention_description' => null,
                            'CGIAR_action_areas' => null,
                            'innovation_submitter' => [
                                'first_name' => null,
                                'last_name' => null,
                                'email' => null,
                                'company' => null,
                                'country' => null,
                                'website' => null,
                                'organizational_logo' => null,

                            ],
                            'key_innovation_partners' => null,
                            'key_scaling_partners' => null,
                            'key_demand_partners' => null,
                            'locations_of_implementation' => null,
                            'start_date' => null,
                            'end_date' => null,
                            'SDG_target' => null,
                            'CGIAR_impact_target' => null,
                            'initiative_defined_outcome' => null,
                            'environmental_benefits' => null,
                            'technology_development_stage' => null,
                            'technology_development_project_summary' => null,
                            'innovation_readiness_levels_of_the_components' => null,
                            'locations_of_applied_evidence' => null,
                            'locations_of_experimental_evidence' => null,
                            'locations_of_impact' => null,
                            'innovation_reference_materials' => null,
                            'technology_appraisal' => null,
                            'technology_appraisal_image' => null,
                            'documentation_to_potential_investors' => null,
                            'type_of_patent_number' => null,
                            'patent_number' => null,
                            'patent_office' => null,
                            'patent_knowhow_info' => null,
                            'administrative_scale_of_the_innovations' => null,
                            'users_of_the_innovation' => null,
                            'beneficiaries_of_the_innovation' => null,
                            'sponsors_of_the_innovation' => null,
                            'value_added_of_the_innovation' => null,
                            'main_advantages' => null,
                            'main_disadvantages' => null,
                            'investment_sought' => null,
                            'type_of_investment_sought' => null,
                            'estimated_amount_sought' => null,
                            'innovation_use_levels_of_the_components' => null,
                            'scaling_readiness_level' => null,
                            'scaling_readiness_score' => null,
                            'problem_the_innovation_provides_solution' => null

                        ]
                    ];

                    return response()->json(['data' => $allresponse], 202);
                } else {
                    return response()->json(['data' => 'An unknown error occurred'], 500);
                }
                break;
            case ('ldn_indexer'):
                /*ldn_indexer*/
                if ($json->code == 400) {
                    return response()->json(['data' => $json], 200);
                } else if ($json->code == 200) {
                    return response()->json(['data' => $json], 200);
                }
            /*ldn_indexer*/
            case ('gardian_index'):
                if ($json->code == 400) {
                    return response()->json(['data' => $json], 200);
                } else if ($json->code == 200) {
                    return response()->json(['data' => $json], 200);
                }

        }
    }

    public function rtb_retrievedocument_by_title(Request $request)
    {
        /*
        if(env('APP_STATE', '') == 'dev')
        {
            $urlString = '/api/v1/search/dev/retrievebytitle';
        }
        else
        {
            $urlString = '/api/v1/search/retrievebytitle';
        }*/
        $urlString = '/api/v1/search/retrievebytitle';
        $requestType = 'POST';
        $header = ['Content-Type: application/json'];
        $requiredBodyData = [
            'title' => 'required|String',
            'alias' => 'required|String',
        ];

        $requestData = json_decode($request->getContent(), true);

        /*$validation = $this->validateIncomingData($requiredBodyData, $requestData);

        $checkValidationResponse = $this->checkValidationState($validation->status(), $validation->getContent());
        if ($checkValidationResponse == true) {
            return $this->makeRequestToBackend($urlString, $requestType, $header, $validation->getContent());
        } else {
            return response()->json(['error' => $validation->getContent()], 400);
        }*/
        $response = Http::withHeaders($header)->withBody($request->getContent(), 'json')->$requestType(env('INNOVATION_ES','').$urlString)->json();
        return response()->json($response,200);

    }

    private function addMissingValues($incomingShortArray, $shortnessValue)
    {
        //shortnessValue is how many array values will be added to the short array to match the bigger one
        // (eg. the incoming array has a count of 5 and 5 null items will be added to it in order to match the other array)
        for ($i = 0; $i != $shortnessValue; $i++) {
            array_push($incomingShortArray, "");
        }

        return $incomingShortArray;
    }

    private function createCombinationalArray(array $shortNameArray, array $longNameArray)
    {

        $combinationalArray = [];
        for ($i = 0; $i < sizeof($longNameArray); $i++) {
            $combinationalArray[] = [
                'short' => $shortNameArray[$i],
                'long' => $longNameArray[$i]
            ];
        }

        return $combinationalArray;
    }

}
