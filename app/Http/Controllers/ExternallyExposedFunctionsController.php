<?php

namespace App\Http\Controllers;

use Elastic\Elasticsearch\ClientBuilder;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;
use stdClass;

class ExternallyExposedFunctionsController extends Controller
{
    public function getSingleInnovation($innovation_id)
    {
        //Validating the input
        $validator = Validator::make(["innovation_id" => $innovation_id], [
            'innovation_id' => 'required|string',
        ]);
        if ($validator->fails()) {
            Log::error('Resource Validation Failed: ', [$validator->errors(), $innovation_id]);
            return response()->json(["result" => "failed","errorMessage" => $validator->errors()], 400);
        }

        //Elasticsearch client
        $client = ClientBuilder::create()
            ->setHosts([env('INNOVATIONS_RTB_ES','')])
            ->build();


        $params = [
            'index' => 'rtb_innovations',
            'body'  => [
                "query" => [
                    "match" => [
                        "innovation_uuid" => "E48F4E7CA6F09F4AE0EFD1EDBED26BF3"
                    ]
                ]
            ]
        ];

        $response = $client->search($params);
        Log::info("Response from elastic", [$response]);
        return response()->json(["result" => "ok", "innovation" => $response["hits"]["hits"][0]], 200);
    }

    public function getAllInnovations()
    {
        //Elasticsearch client
        $client = ClientBuilder::create()
            ->setHosts([env('INNOVATIONS_RTB_ES','')])
            ->build();


        $params = [
            'index' => 'rtb_innovations',
            "scroll" => "50s",
            'body'  => [
                "query" => [
                    "match_all" => new StdClass
                ],
            ]
        ];

        $response = $client->search($params);
        Log::info("Response from elastic", [$response]);

        $allResults = array();
        $count = 0;
        while(count($response["hits"]["hits"]) > 0)
        {
            $allResults = array_merge($allResults, $response["hits"]["hits"]);
            $scrollParams = [
                'scroll_id' => $response->asObject()->_scroll_id,
                'scroll' => '1m'
            ];
            $response = $client->scroll($scrollParams);
            $count++;
        }
        Log::info("Used the scroll ID this many times:", [$count]);

        return response()->json(["result" => "ok", "innovations" => $allResults], 200);
    }
}
