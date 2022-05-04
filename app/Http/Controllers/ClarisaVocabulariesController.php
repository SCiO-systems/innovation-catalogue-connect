<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;

class ClarisaVocabulariesController extends Controller
{
//Clarisa vocabulary results
    public function getClarisaResults()
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
            "clarisa_innovation_use_levels",
            "clarisa_technology_development_stage"
        );

        $vocabToArray = (array)$result;
        $clarisa_vocabulary = array();

        //Cases with { id , value}
        foreach ($usefulHeaders as $header)
        {
            //Log::info("HERE'S THE VOCAB", $vocabToArray[$header]);
            $value = array();
            foreach ($vocabToArray[$header] as $fields)
            {
                if(strcmp($header, "clarisa_administrative_scale") == 0 || strcmp($header, "clarisa_innovation_type") == 0)
                {
                    $valueProperty = array("id" => $fields->code, "value" => $fields->name);
                }
                elseif (strcmp($header, "clarisa_countries") == 0)
                {
                    $valueProperty = array("id" => $fields->isoAlpha2, "value" => $fields->name);
                }
                elseif (strcmp($header, "clarisa_technology_development_stage") == 0)
                {
                    $valueProperty = array("id" => $fields->id, "value" => $fields->officialCode." - ".$fields->name);
                }
                else{
                    $valueProperty = array("id" => $fields->id, "value" => $fields->name);
                }
                array_push($value, $valueProperty);

            }
            $singleHeader = array("header" => $header, "value" => $value);
            array_push($clarisa_vocabulary, $singleHeader);
        }

        //Cases with objects in value
        //TODO: titles could be done with less code (use the key as the value)
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

    //Autocomplete clarisa organizations
    public function autocompleteOrganization(Request $request)
    {
        //Validating the input
        $validator = Validator::make(["organizations" => $request->autocomplete], [
            'organizations' => 'required|string',
        ]);
        if ($validator->fails()) {
            Log::error('Resource Validation Failed: ', [$validator->errors(), $request->autocomplete]);
            return response()->json(["result" => "failed","errorMessage" => $validator->errors()], 400);
        }

        $result =  Http::post(env('SCIO_ORGANIZATION_AUTOCOMPLETE',''), [
            "autocomplete" => $request->autocomplete,             //string given for autocomplete
            "alias" => "clarisa_institutions_ontology",
            "field"=> "ngram_tokenizer"
        ]);

        if($result->status() != 200)
        {
            Log::error("Error from autocomplete API", [$result]);
            return response()->json(["result" => "failed", "errorMessage" => "Internal Server Error"], 500);
        }

        $organizations = array();
        foreach ($result["response"]["suggestions"] as $fields) {
            $valueProperty = array("id" => $fields["code"], "value" => $fields["name"]);
            array_push($organizations, $valueProperty);
        }

        return response()->json(["organizations" => $organizations], 200);
    }
}
