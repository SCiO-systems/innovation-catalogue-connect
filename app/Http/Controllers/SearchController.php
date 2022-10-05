<?php

namespace App\Http\Controllers;

use Elastic\Elasticsearch\ClientBuilder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use stdClass;

class SearchController extends Controller
{
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
        //$aggregatesAsObject = (object) $aggregationsArray;
        Log::info("This is the aggregationsArray", [$aggregationsArray]);

        return $aggregationsArray;
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

        //Create the aggregations
        $aggregatesAsObject = (object) (new SearchController())->handleAggregations();
        //$aggregatesAsObject = (object) $aggregationsArray;
        Log::info("This is the aggregationsArray", [$aggregatesAsObject]);


        //Check for filters and make the correct terms
        $filters = $requestBody["operation"]["details"]["filter"];
        if(!empty($filters))
        {
            Log::info($filters);

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
            Log::info("ITS ALIVE", ["should" =>$shouldArray]);
        }


        //Check for ordered search
        $orderedSearchFlag = 0;
        if (strcmp($requestBody["operation"]["action"], "ordered_search") == 0)
        {
            $orderedSearchFlag = 1;
            $orderInfo = $requestBody["operation"]["details"]["order"];
            if(strcmp($orderInfo["field"], "title") == 0){
                $fieldForOrdering = "innovation_common_name.keyword";
            }
            else if(strcmp($orderInfo["field"], "last_updated") == 0)
            {
                $fieldForOrdering = "work_end_date";
            }
            else
            {
                Log::error("Wrong ordering arguments given");
                return response()->json(["result" => "failed", "errorMessage" => "Wrong ordering arguments given"], 400);
            }
            $sortArray = array([
                $fieldForOrdering => [
                    "order" => $orderInfo["sort"]
                ]
            ]);
        }

        if($orderedSearchFlag)
        {
            Log::info("This is the sortArray", $sortArray);
            $params = [
                'index' => 'rtb_innovations',
                'body'  => [
                    "sort" => $sortArray,
                    "query"=> [
                        "bool" => [
                            "must" => [
                                [
                                    "multi_match" => [
                                        "query" => $requestBody["keyword"],
                                        "type" => "phrase"
                                    ]
                                ]
                            ],
                            "should" => $shouldArray
                        ]
                    ],
                    "size" => 20,
                    "from"=> 0,
                    "aggs" => $aggregatesAsObject
                ]
            ];
        }
        else{
            $params = [
                'index' => 'rtb_innovations',
                'body'  => [
                    "query"=> [
                        "bool" => [
                            "must" => [
                                [
                                    "multi_match" => [
                                        "query" => $requestBody["keyword"],
                                        "type" => "phrase"
                                    ]
                                ]
                            ],
                            "should" => $shouldArray
                        ]
                    ],
                    "size" => 20,
                    "from"=> 0,
                    "aggs" => $aggregatesAsObject
                ]
            ];
        }


        Log::info("////////////////////////////////////////////////////");
        Log::info("This is about to be sent to elasticsearch", [$params]);



        //Get the elasticsearch result
        $response = $client->search($params);
        Log::info("Got a response from elasticsearch", [$response->asObject()->hits->total]);


        //TODO:Transform it to the UI specified format

        return response()->json(["result" => "ok", "elasticDoco" => $response->asObject()], 200);

    }
}
