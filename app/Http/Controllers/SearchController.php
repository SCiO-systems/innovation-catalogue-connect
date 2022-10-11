<?php

namespace App\Http\Controllers;

use Elastic\Elasticsearch\ClientBuilder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use PhpParser\Node\Expr\Cast\Object_;
use stdClass;

class SearchController extends Controller
{
    /*
    //////////////SEARCH/////////////
    */
    //Creates the aggregations component for the Elasticsearch query
    private function handleAggregations() :array
    {
        //All have size 100 except "related_keywords.keyword"
        $aggQueryFields = array(
            "cgiar_action_areas" => "CGIAR_action_areas_name.keyword",
            "region" => "region.keyword",
            "submitter_company_name" => "submitter_company_name.keyword",
            "env_benefits" => "environmental_benefits.keyword",
            "type_of_innovation" => "innovation_type_new.keyword",
            "business_category" => "business_category.keyword",
            "technical_fields" => "technical_fields.keyword",
            "gov_type" => "gov_type_of_solution.keyword",
            "impact_areas" => "impact_areas.keyword",
            "countries" => "locations_of_implementation.keyword",
            "sdgs" => "SDG.fullName.keyword",
            "keywords" => "related_keywords.keyword", //this has size 2000
            "sdg_targets" => "SDG_targets.keyword"
        );

        $aggregationsArray = array();
        foreach ($aggQueryFields as $aggKey => $aggValue)
        {
            if(strcmp($aggValue,"related_keywords.keyword") == 0)
            {
                $termArray = [
                    "field" => $aggValue,
                    "size" => 100
                ];
            }
            else
            {
                $termArray = [
                    "field" => $aggValue,
                    "size" => 2000
                ];
            }

            $singleAggregation = [
                $aggKey => [
                    "terms" => $termArray
                ]
            ];
            $aggregationsArray = array_merge($aggregationsArray,$singleAggregation);
        }
        //Log::info("This is the aggregationsArray", [$aggregationsArray]);

        return $aggregationsArray;
    }

    //Creates the terms components based on the filters given
    private function handleTerms($filters) :array
    {
        $filterKeyMapping = array(
            "title" => "innovation_common_name",
            "last_updated" => "work_end_date",
            "cgiar_action_areas" => "CGIAR_action_areas_name",
            "region" => "region",
            "submitter_company_name" => "submitter_company_name",
            "env_benefits" => "environmental_benefits",
            "type_of_innovation" => "innovation_type_new",
            "business_category" => "business_category",
            "technical_fields" => "technical_fields",
            "gov_type" => "gov_type_of_solution",
            "impact_areas" => "impact_areas",
            "countries" => "locations_of_implementation",
            "keywords" => "related_keywords",
            "sdg_targets" => "sdg_targets",
            "sdgs" => "SDG.fullName"
        );

        $shouldArray = array();
        foreach ($filters as $filter){
            $key = $filter["key"];
            if (array_key_exists($key, $filterKeyMapping))
            {
                $term = array(
                    $filterKeyMapping[$key].".keyword" => [
                        "value" => $filter["value"]
                    ]
                );
                array_push($shouldArray, ["term" => $term]);
            }

        }

        return $shouldArray;
    }

    //Handle ordered search
    private function handleOrderedSearch($orderInfo) :array
    {
        //Check for the field that will be used for the ordered search
        if(strcmp($orderInfo["field"], "title") == 0){
            $fieldForOrdering = "innovation_common_name.keyword";
        }
        else if(strcmp($orderInfo["field"], "last_updated") == 0)
        {
            $fieldForOrdering = "work_end_date";
        }
        else
        {
            //Invalid argument return empty array
            Log::error("Wrong ordering arguments given");
            return array();

        }
        $sortArray = array([
            $fieldForOrdering => [
                "order" => $orderInfo["sort"]
            ]
        ]);
        return $sortArray;
    }

    public function searchInnovationIndex(Request $request)
    {


        //Elasticsearch client build
        $client = ClientBuilder::create()
            ->setHosts([env('INNOVATIONS_RTB_ES','')])
            ->build();

        //Get the body from the request
        $requestBody = $request->toArray();

        //Transform it to the correct query format for elasticsearch

        //Create the aggregations
        $aggregationsArray = (new SearchController())->handleAggregations();
        if(!empty($aggregationsArray))
        {
            $aggregatesAsObject = (object) $aggregationsArray;
            Log::info("These are the aggregations", [$aggregatesAsObject]);
        }
        else
        {
            Log::error("Something went wrong with the aggregations", [$aggregationsArray]);
            return response()->json(["result" => "failed", "errorMessage" => "Internal Server Error"], 500);
        }

        $boolArray = [
            "must" => [
                "multi_match" => [
                    "query" => $requestBody["keyword"],
                    "type" => "phrase"
                ]
            ]
        ];



        //Check for filters and make the correct terms
        $filters = $requestBody["operation"]["details"]["filter"];
        if(!empty($filters))
        {
            Log::info($filters);
            $shouldArray = (new SearchController())->handleTerms($filters);

            $boolArray = array_merge($boolArray, ["should" => $shouldArray]);
            //Log::info("ITS ALIVE", ["should" =>$shouldArray]);
        }

        //Check for ordered search
        $orderedSearchFlag = 0;
        if (strcmp($requestBody["operation"]["action"], "ordered_search") == 0)
        {
            $orderedSearchFlag = 1;
            $orderInfo = $requestBody["operation"]["details"]["order"];
            $sortArray = (new SearchController())->handleOrderedSearch($orderInfo);
            if(empty($sortArray))
            {
                return response()->json(["result" => "failed", "errorMessage" => "Wrong ordering arguments given"], 400);
            }
        }

        if($orderedSearchFlag)
        {
            //Log::info("This is the sortArray", $sortArray);
            $params = [
                'index' => 'rtb_innovations',
                'body'  => [
                    "sort" => $sortArray,
                    "query"=> [
                        "bool" => $boolArray
                    ],
                    "size" => $requestBody["operation"]["details"]["size"],
                    "from"=> $requestBody["operation"]["details"]["from"],
                    "aggs" => $aggregatesAsObject
                ]
            ];
        }
        else{
            $params = [
                'index' => 'rtb_innovations',
                'body'  => [
                    "query"=> [
                        "bool" => $boolArray
                    ],
                    "size" => $requestBody["operation"]["details"]["size"],
                    "from"=> $requestBody["operation"]["details"]["from"],
                    "aggs" => $aggregatesAsObject
                ]
            ];
        }

        /*Log::info("////////////////////////////////////////////////////");
        Log::info("This is about to be sent to elasticsearch", [$params]);
        Log::info("////////////////////////////////////////////////////");*/
        //Get the elasticsearch result
        $response = $client->search($params);

        //Transform data for the UI
        Log::info("This is the elasticstatus code", [$response->getStatusCode()]);
        if ($response->getStatusCode() != 200)
        {
            return response()->json(["result" => "failed", "errorMessage" => "Internal Server Error"], 500);
        }
        else
        {
            //Check elasticsearch provided some innovations
            $elasticData = $response->asObject()->hits->hits;
            if (empty($elasticData))
            {
                $innovationsEmpty = 1;
                Log::info("No innovations found");
            }
            else{
                $innovationsEmpty = 0;
            }
        }

        //Innovation important data
        $innovations = array();
        if(!$innovationsEmpty)
        {
            foreach ($elasticData as $singleData)
            {
                $singleInnovationMetadata = (object)[
                    'innovation_id' => $singleData->_source->innovation_uuid,
                    'title' => $singleData->_source->innovation_common_name,
                    'submitter' => (object)[
                        'submitter_first_name' => $singleData->_source->submitter_first_name,
                        'submitter_last_name' => $singleData->_source->submitter_last_name,
                        'submitter_email' => $singleData->_source->submitter_email
                    ],
                    'summary' => $singleData->_source->long_innovation_description,
                    'last_updated' => $singleData->_source->last_updated
                ];
                array_push($innovations, $singleInnovationMetadata);
            }
        }

        //General data, summaries and metadata
        $aggregationsInformation = (array) $response->asObject()->aggregations;
        //Log::info("THESE ARE THE AGGREGATIONS FROM THE ELASTIC", [$aggregationsInformation]);
        $summariesArray = array();
        foreach ($aggregationsInformation as $aggregationName => $singleAggregation)
        {
            $singleAggregationData = array();
            foreach ($singleAggregation->buckets as $singleDocCount)
            {
                array_push($singleAggregationData, ["value" => $singleDocCount->key, "freq" => $singleDocCount->doc_count]);
            }
            array_push($summariesArray, [$aggregationName => $singleAggregationData]);
        }

        $responseBody = ['version' => '2.0', "total" => $response->asObject()->hits->total->value, "results" => $innovations, "summaries" => $summariesArray];


        return response()->json(["data" => $responseBody], 200);

    }

    /*
    //////////////SEARCH BY TITLE/////////////
    */

    public function searchInnovationByTitle(Request $request)
    {
        //Elasticsearch client build
        $client = ClientBuilder::create()
            ->setHosts([env('INNOVATIONS_RTB_ES','')])
            ->build();


        $params = [
            'index' => $request->alias,
            'body'  => [
                "query" => [
                    "match" => [
                        "innovation_common_name.keyword" => $request->title
                    ]
                ]
            ]
        ];
        Log::info("These parameters will be sent to elasticsearch", $params);

        //Get the elasticsearch result
        $response = $client->search($params);

        if ($response->getStatusCode() != 200)
        {
            Log::info("Response from elasticsearch not as expected", [$response->asObject()]);
            return response()->json(["result" => "failed", "errorMessage" => "Internal Server Error"], 500);
        }

        $innovationFromElastic = $response->asObject()->hits;
        if($innovationFromElastic->total->value == 0)
        {
            $data = new StdClass;
        }
        else
        {
            $data = $innovationFromElastic->hits[0]->_source;
        }


        return response()->json(["code" => "200", "response" => $data], 200);
    }

    /*
    //////////////SEARCH BY ID/////////////
    */

    public function searchInnovationById(Request $request)
    {
        //Elasticsearch client build
        $client = ClientBuilder::create()
            ->setHosts([env('INNOVATIONS_RTB_ES','')])
            ->build();

        $params = [
            'index' => $request->alias,
            'body'  => [
                "query" => [
                    "match" => [
                        "innovation_uuid.keyword" => $request->id
                    ]
                ]
            ]
        ];
        //Get the elasticsearch result
        $response = $client->search($params);

        if ($response->getStatusCode() != 200)
        {
            return response()->json(["result" => "failed", "errorMessage" => "Internal Server Error"], 500);
        }

        $innovationFromElastic = $response->asObject()->hits;
        if($innovationFromElastic->total->value == 0)
        {
            $data = new StdClass;
        }
        else
        {
            $data = $innovationFromElastic->hits[0]->_source;
        }

        //Log::info("This is the response decoded", [$data]);

//        switch ($request->alias) {
//            case ('rtb_innovations'):
//                break;
//            case ('ldn_indexer'):
//                /*ldn_indexer*/
//                if ($response->getStatusCode() == 400) {
//                    return response()->json(['data' => $json], 200);
//                } else if ($response->getStatusCode() == 200) {
//                    return response()->json(['data' => $json], 200);
//                }
//                break;
//            /*ldn_indexer*/
//            case ('gardian_index'):
//                if ($response->getStatusCode() == 400) {
//                    return response()->json(['data' => $json], 200);
//                } else if ($response->getStatusCode() == 200) {
//                    return response()->json(['data' => $json], 200);
//                }
//                break;
//        }

        if ($response->getStatusCode() == 400)
        {

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
        }

        $shortNameValues = $data->intervention_short_name;
        $longNameValues = $data->intervention_long_name;
        $counter = 0;
        $combinationalArray = array();
        while (($counter < count($shortNameValues)) || $counter < count($longNameValues))
        {
            if($counter > count($shortNameValues))
            {
                $combinationalArray[] = [
                    'short' => "",
                    'long' => $longNameValues[$counter]
                ];
            }
            else if($counter > count($longNameValues))
            {
                $combinationalArray[] = [
                    'short' => $shortNameValues[$counter],
                    'long' => ""
                ];
            }
            else {
                $combinationalArray[] = [
                    'short' => $shortNameValues[$counter],
                    'long' => $longNameValues[$counter]
                ];
            }
            $counter++;
        }

        $allresponse = [
            'response' => [
                'MEL_user_id' => $data->mel_user_id_who_reported_innovation,
                'MEL_innovation_id' => $data->innovation_uuid,
                'related_innovations' => $data->mel_ids_of_related_innovations,
                'title' => $data->innovation_common_name,
                'summary' => $data->long_innovation_description,
                'business_category' => $data->business_category,
                'keywords' => $data->related_keywords,
                'innovation_URL' => $data->innovation_url,
                'image_of_the_innovation' => $data->innovation_image,
                'image_of_the_innovation_component' => $data->innovation_image_component,
                'technical_field' => $data->technical_fields,
                'type_of_innovation_old' => $data->innovation_type_old,
                'type_of_innovation_new' => $data->innovation_type_new,
                'governance_type' => $data->gov_type_of_solution,
                'intervention_name' => $combinationalArray,
                'total_budget_of_interventions' => $data->intervention_total_budget,
                'intervention_team_members' => $data->intervention_team_members,
                'challenge_statement' => $data->challenge_statement,
                'objective_statement' => $data->objective_statement,
                'long_intervention_description' => $data->long_intervention_description,
                'CGIAR_action_areas' => $data->CGIAR_action_areas_name,
                'innovation_submitter' => [
                    'first_name' => $data->submitter_first_name,
                    'last_name' => $data->submitter_last_name,
                    'email' => $data->submitter_email,
                    'company' => $data->submitter_company_name,
                    'country' => $data->submitter_country,
                    'website' => $data->submitter_website,
                    'organizational_logo' => $data->organizational_logo
                ],
                'key_innovation_partners' => $data->key_innovation_partners,
                'key_scaling_partners' => $data->key_scaling_partners,
                'key_demand_partners' => $data->key_demand_partners,
                'locations_of_implementation' => $data->locations_of_implementation,
                'start_date' => $data->work_start_date,
                'end_date' => $data->work_end_date,
                'SDG_target' => $data->sdg_target_ui,
                'CGIAR_impact_target' => $data->CGIAR_impact_targets,
                'initiative_defined_outcome' => $data->initiative_defined_outcome,
                'environmental_benefits' => $data->environmental_benefits,
                'technology_development_stage' => $data->technology_dev_stage,
                'technology_development_project_summary' => $data->technology_dev_project_summary,
                'innovation_readiness_levels_of_the_components' => $data->innovation_readiness_levels_of_component,
                'locations_of_applied_evidence' => $data->applied_evidence_locations,
                'locations_of_experimental_evidence' => $data->experimental_evidence_locations,
                'locations_of_impact' => $data->impact_evidence_locations,
                'innovation_reference_materials' => $data->innovation_reference_materials,
                'technology_appraisal' => $data->technology_appraisal,
                'technology_appraisal_image' => $data->technology_appraisal_image,
                'documentation_to_potential_investors' => $data->documentation,
                'type_of_patent_number' => $data->patent_member_type,
                'patent_number' => $data->patent_number,
                'patent_office' => $data->patent_office,
                'patent_knowhow_info' => $data->patent_know_how_info,
                'administrative_scale_of_the_innovations' => $data->administrative_scale_of_innovations,
                'users_of_the_innovation' => $data->innovation_users,
                'beneficiaries_of_the_innovation' => $data->innovation_beneficiaries,
                'sponsors_of_the_innovation' => $data->innovation_sponsors,
                'value_added_of_the_innovation' => $data->value_added,
                'main_advantages' => $data->main_advantages,
                'main_disadvantages' => $data->main_disadvantages,
                'investment_sought' => $data->investment_sought,
                'type_of_investment_sought' => $data->investment_sought_type,
                'estimated_amount_sought' => $data->estimated_amount_sought,
                'innovation_use_levels_of_the_components' => $data->innovation_use_levels_of_components,
                'scaling_readiness_level' => $data->scaling_readiness_level,
                'scaling_readiness_score' => $data->scaling_readiness_score,
                'problem_the_innovation_provides_solution' => $data->problem_to_be_solved

            ]
        ];


        return response()->json(["data" => $allresponse], 200);


    }

}
