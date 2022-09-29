<?php

namespace App\Http\Controllers;

use App\Models\Innovation;
use App\Models\User;
use DateTime;
use Elastic\Elasticsearch\ClientBuilder;
use Elastic\Elasticsearch\Response\Elasticsearch;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use GuzzleHttp\Psr7;
use stdClass;
use function PHPUnit\Framework\isEmpty;

class ElasticPopulationController extends Controller
{
    public function publishToElastic($innovation_id)
    {
        ini_set('max_execution_time', 1500);
        //Fields that follow the standard case, copying the value
        $mapping_index = array("1.1" => "innovation_common_name",       //Missing 1.7 & 1.8 1.13, requires transformation
            "1.2" => "long_innovation_description",
            "1.3" => "business_category",
            "1.4" => "administrative_scale_of_innovations",
            "1.5" => "related_keywords",
            "1.6" => "innovation_url",
            "1.9" => "technical_fields",
            "1.10" => "innovation_type_old",
            "1.11" => "innovation_type_new",
            "1.12" => "gov_type_of_solution",
            "2.1" => "CGIAR_action_areas_name",                         //Extra SDG fields that require population. Missing 2.6 & 2.7 require transformation
            "2.2" => "value_added",
            "2.3" => "main_advantages",
            "2.4" => "main_disadvantages",
            "2.9" => "environmental_benefits",
            "3.1" => "locations_of_implementation",                     //Missing 3.2 & 3.3 requires transformation
            "3.4" => "applied_evidence_locations",
            "3.5" => "impact_evidence_locations",
            "4.2" => "technology_appraisal",                            //Missing 4.1 & 4.3 requires heavy transformation
            "5.1" => "patent_member_type",                              //Missing 5.4 requires transformation
            "5.2" => "patent_number",
            "6.2" => "intervention_total_budget",                       //Missing 6.1 & 6.3 requires transformation
            "6.4" => "challenge_statement",
            "6.5" => "objective_statement",
            "6.6" => "long_intervention_description",
            "7.1" => "technology_dev_project_summary",
            "7.2" => "investment_sought_type",
            "7.3" => "investment_sought",
            "8.1" => "technology_dev_stage",
            "8.2" => "technology_dev_project_summary",
            "8.3" => "innovation_readiness_levels_of_component",
            "8.4" => "innovation_use_levels_of_components",
            "8.5" => "scaling_readiness_level",
            "8.6" => "scaling_readiness_score",
            "9.1" => "innovation_users",                                //Missing 9.4 & 9.5 & 9.6
            "9.2" => "innovation_beneficiaries");


        $specialCaseMappingIndex = array("1.7" => "innovation_image",   //6.1, splits in 2 fields
            "1.8" => "innovation_image_component",
            "1.13" => "uuids_of_related_innovations",
            "2.5" => "problem_to_be_solved",
            "2.6" => "SDG_targets",
            "2.7" => "CGIAR_impact_targets",
            "2.8" => "initiative_defined_outcome",
            "3.2" => "work_start_date",
            "3.3" => "work_end_date",
            "4.1" => "innovation_reference_materials",
            "4.3" => "technology_appraisal_image",
            "4.4" => "documentation",
            "5.3" => "patent_office",
            "5.4" => "patent_know_how_info",
            "6.1" => "intervention_full_name",
            "6.3" => "intervention_team_members",
            "9.3" => "innovation_sponsors",
            "9.4" => "key_innovation_partners",
            "9.5" => "key_demand_partners",
            "9.6" => "key_scaling_partners");


        //Elasticsearch client
        $client = ClientBuilder::create()
            ->setHosts([env('INNOVATIONS_RTB_ES_PROD','')])
            ->build();


        //Fetch the requested innovation
        $innovation = Innovation::where('innovId', $innovation_id)
            ->where('deleted', false)
            ->where('status', "PUBLISHED")
            ->orderBy('version', 'desc')
            ->first();

        if( $innovation == null)
        {
            Log::warning('Requested innovation not found', [$innovation_id]);
            return response()->json(["result" => "failed","errorMessage" => 'Requested innovation not found'], 202);
        }

        //Fetch the author of the innovation
        $user = User::find($innovation["userIds"][0]);
        if($user == null)
        {
            Log::error("User not found in database", [$innovation["userIds"][0]]);
            return response()->json(["result" => "failed","errorMessage" => "User not found"], 404);
        }

        $elasticDocument = array();
        $elasticDocumentSpecialCase = array();
        //Log::info("ALL DATA BOIIIII", $innovation->formData);
        foreach ($innovation->formData as $singleField)
        {
            $indexingKey = $singleField["id"];
            //General Case, no transformation or extra handling required
            if(array_key_exists($indexingKey, $mapping_index)){
                $elasticField = [$mapping_index[$indexingKey] => $singleField["value"]];
                $elasticDocument = array_merge($elasticDocument, $elasticField);
            }
            elseif(array_key_exists($indexingKey, $specialCaseMappingIndex))
            {
                switch ($indexingKey)
                {
                    case "1.7":
                    case "4.3":
                    case "5.4":
                        if (empty($singleField["value"]) || is_null($singleField["value"]))
                        {
                            $elasticField = [$specialCaseMappingIndex[$indexingKey] =>""];
                        }
                        else{
                            $elasticField = [$specialCaseMappingIndex[$indexingKey] => $singleField["value"][0]["name"]];
                        }
                        $elasticDocument = array_merge($elasticDocument, $elasticField);
                        break;
                    case "1.8":
                        //Image strings are seperated by double semicolons
                        $transformedValue = "";
                        foreach ($singleField["value"] as $singleImage)
                        {
                            if(empty($transformedValue))
                            {
                                $transformedValue = $singleImage["name"];
                            }
                            else{
                                $transformedValue = $singleImage["name"]." ;; ".$transformedValue;
                            }
                        }
                        $elasticField = [$specialCaseMappingIndex[$indexingKey] => $transformedValue];
                        $elasticDocument = array_merge($elasticDocument, $elasticField);
                        break;
                    case "1.13":
                        //Array of innovation id & name, useful data are the names of each innovation
                        $transformedValue = array();
                        foreach ($singleField["value"] as $singleInnovation)
                        {
                            $counter = 0;
                            $uuidTransformed = "";
                            $explodedUUID = explode("-",$singleInnovation["innovation_id"]);
                            foreach ($explodedUUID as $subId)
                            {
                                if($counter != 0)
                                {
                                    $uuidTransformed = $uuidTransformed.$subId;
                                }
                                $counter++;
                            }
                            array_push($transformedValue, $uuidTransformed);
                        }
                        $elasticField = [$specialCaseMappingIndex[$indexingKey] => $transformedValue];
                        $elasticDocument = array_merge($elasticDocument, $elasticField);
                        break;
                    case "2.5":
                    case "2.8":
                    case "4.4":
                        //Transform to list with one element
                        $transformedValue = [$singleField["value"]];
                        $elasticField = [$specialCaseMappingIndex[$indexingKey] => $transformedValue];
                        $elasticDocument = array_merge($elasticDocument, $elasticField);
                        break;
                    case "2.6":
                    case "2.7":
                        //Array with objects, useful data are stored in "value" field
                        $targetsArray = array();
                        foreach ($singleField["value"] as $singleTarget)
                        {
                            foreach ($singleTarget["value"] as $singleValue)
                            {
                                array_push($targetsArray, $singleValue["value"]);
                            }
                        }
                        $elasticField = [$specialCaseMappingIndex[$indexingKey] => $targetsArray];
                        $elasticDocument = array_merge($elasticDocument, $elasticField);
                        break;
                    case "3.2":
                    case "3.3":
                        //String with the complete date, only need the year
                        //Log::info("trying to figure staffff out", $singleField["value"]);
                        if((isEmpty($singleField["value"])) || strcmp($singleField["value"],"Invalid Date") == 0)
                        {
                            $elasticField = [$specialCaseMappingIndex[$indexingKey] =>[0]];
                        }
                        else{

                            $explodedDate = explode(" ",$singleField["value"]);
                            $elasticField = [$specialCaseMappingIndex[$indexingKey] => [$explodedDate[3]]];
                        }
                        $elasticDocument = array_merge($elasticDocument, $elasticField);
                        break;
                    case "4.1":
                        //References is an array of strings, each string is made out of title and name separated by double #
                        $referencesArray = array();
                        foreach ($singleField["value"] as $singleReference)
                        {
                            $transformedValue = $singleReference["title"]."## ".$singleReference["name"];
                            array_push($referencesArray, $transformedValue);
                        }
                        $elasticField = [$specialCaseMappingIndex[$indexingKey] => $referencesArray];
                        $elasticDocument = array_merge($elasticDocument, $elasticField);
                        break;
                    case "5.3":
                        //Transform int to string
                        $transformedValue = strval($singleField["value"]);
                        $elasticField = [$specialCaseMappingIndex[$indexingKey] => $transformedValue];
                        $elasticDocument = array_merge($elasticDocument, $elasticField);
                        break;
                    case "6.1":
                        //Intervention short name, long name and full name
                        $shortNamesArray = array();
                        $longNamesArray = array();
                        $fullNamesArray = array();
                        foreach ($singleField["value"] as $singleName)
                        {
                            array_push($shortNamesArray, $singleName["value"][0]);
                            array_push($longNamesArray, $singleName["value"][1]);
                            $transformedValue = $singleName["value"][1]."(".$singleName["value"][0].")";
                            array_push($fullNamesArray, $transformedValue);
                        }
                        $elasticField = [$specialCaseMappingIndex[$indexingKey] => $fullNamesArray];
                        $elasticDocument = array_merge($elasticDocument, $elasticField);
                        $elasticField = ["intervention_long_name" => $longNamesArray];
                        $elasticDocument = array_merge($elasticDocument, $elasticField);
                        $elasticField = ["intervention_short_name" => $shortNamesArray];
                        $elasticDocument = array_merge($elasticDocument, $elasticField);
                        break;
                    case "6.3":
                        $membersArray = array();
                        foreach ($singleField["value"] as $singleMember)
                        {
                            $transformedValue = $singleMember["value"][0]["name"];
                            array_push($membersArray, $transformedValue);
                        }
                        $elasticField = [$specialCaseMappingIndex[$indexingKey] => $membersArray];
                        $elasticDocument = array_merge($elasticDocument, $elasticField);
                        break;
                    case "9.3":
                        //Convert list of strings to a single string
                        $transformedValue = "";
                        $counter = 0;
                        foreach($singleField["value"] as $singleMember)
                        {
                            if($counter == 0)
                            {
                                $transformedValue = $singleField["value"];
                            }
                            else
                            {
                                $transformedValue = $transformedValue.", ".$singleField["value"];
                            }
                            $counter++;
                        }
                        $elasticField = [$specialCaseMappingIndex[$indexingKey] => $transformedValue];
                        $elasticDocument = array_merge($elasticDocument, $elasticField);
                        break;
                    case "9.4":
                    case "9.5":
                    case "9.6":
                        $keysArray = array();
                        foreach ($singleField["value"] as $singleKey)
                        {
                            $transformedValue = $singleKey["value"];
                            array_push($keysArray, $transformedValue);
                        }
                    $elasticField = [$specialCaseMappingIndex[$indexingKey] => $keysArray];
                    $elasticDocument = array_merge($elasticDocument, $elasticField);
                        break;
                    default:
                        $elasticField = [$specialCaseMappingIndex[$indexingKey] => $singleField["value"]];
                        $elasticDocumentSpecialCase = array_merge($elasticDocumentSpecialCase, $elasticField);
                }
            }
        }

        //Innovation General Data
        //UUID
        $counter = 0;
        $transformedValue = "";
        $explodedUUID = explode("-",$innovation_id);
        foreach ($explodedUUID as $subId)
        {
            if($counter != 0)
            {
                $transformedValue = $transformedValue.$subId;
            }
            $counter++;
        }
        $uuidTransformed = $transformedValue;
        $elasticField = ["innovation_uuid" => $transformedValue];
        $elasticDocument = array_merge($elasticDocument, $elasticField);

        $explodedName = explode(" ",$user["fullName"]);
        //submitter_first_name
        $elasticField = ["submitter_first_name" => $explodedName[0]];
        $elasticDocument = array_merge($elasticDocument, $elasticField);
        //submitter_last_name
        $counter = 0;
        $transformedValue = "";
        foreach ($explodedName as $subName)
        {
            if($counter != 0)
            {
                $transformedValue = $transformedValue.$subName;
            }
            $counter++;
        }
        $elasticField = ["submitter_last_name" => $transformedValue];
        $elasticDocument = array_merge($elasticDocument, $elasticField);
        //submitter_company_name
        $elasticField = ["submitter_company_name" => $user["organization"]];
        $elasticDocument = array_merge($elasticDocument, $elasticField);
        //submitter_website
        $elasticField = ["submitter_website" => $user["website"]];
        $elasticDocument = array_merge($elasticDocument, $elasticField);
        //submitter_country
        $explodedName = explode(",",$user["country"]);
        $transformedValue = $explodedName[0];
        $elasticField = ["submitter_country" => $transformedValue];
        $elasticDocument = array_merge($elasticDocument, $elasticField);
        //submitter_email
        $elasticField = ["submitter_email" => $user["email"]];
        $elasticDocument = array_merge($elasticDocument, $elasticField);
        //"organizational_logo"
        $elasticField = ["organizational_logo" => $user["organizationLogo"]];
        $elasticDocument = array_merge($elasticDocument, $elasticField);
        //"mel_user_id_who_reported_innovation"
        $elasticField = ["mel_user_id_who_reported_innovation" => $user["fullName"]];
        $elasticDocument = array_merge($elasticDocument, $elasticField);
        //"user_id"
        $elasticField = ["user_id" => $innovation["userIds"]];
        $elasticDocument = array_merge($elasticDocument, $elasticField);
        //"innovation_image_id"
        $elasticField = ["innovation_image_id" => 0];
        $elasticDocument = array_merge($elasticDocument, $elasticField);
        //"HDL"
        if(empty($innovation["persistId"]) || is_null($innovation["persistId"]))
        {
            $elasticField = ["HDL" => ""];
        }
        else
        {
            $elasticField = ["HDL" => $innovation["persistId"][0]];
        }
        $elasticDocument = array_merge($elasticDocument, $elasticField);
        //"experimental_evidence_locations"
        $elasticField = ["experimental_evidence_locations" => [""]];
        $elasticDocument = array_merge($elasticDocument, $elasticField);
        //"estimated_amount_sought"
        $elasticField = ["estimated_amount_sought" => 0];
        $elasticDocument = array_merge($elasticDocument, $elasticField);
        //"mel_id_single"
        $elasticField = ["mel_id_single" => 0];
        $elasticDocument = array_merge($elasticDocument, $elasticField);
        //"mel_version_id_single"
        $elasticField = ["mel_version_id_single" => 0];
        $elasticDocument = array_merge($elasticDocument, $elasticField);
        //"mel_ids_of_related_innovations"
        $elasticField = ["mel_ids_of_related_innovations" => [""]];
        $elasticDocument = array_merge($elasticDocument, $elasticField);
        //"last_updated"
        $d = new DateTime( '@'. $innovation["updatedAt"]/1000 );
        $transformedValue =  $d->format("d/m/Y");
        $elasticField = ["last_updated" => $transformedValue];
        $elasticDocument = array_merge($elasticDocument, $elasticField);
        //Suggesters
        //phrase_suggester
        $elasticField = ["phrase_suggester" => $elasticDocument["innovation_common_name"]];
        $elasticDocument = array_merge($elasticDocument, $elasticField);
        //completion_suggester
        $elasticField = ["completion_suggester" => $elasticDocument["innovation_common_name"]];
        $elasticDocument = array_merge($elasticDocument, $elasticField);
        //term_suggester
        $elasticField = ["term_suggester" => $elasticDocument["innovation_common_name"]];
        $elasticDocument = array_merge($elasticDocument, $elasticField);
        //ngram_tokenizer
        $elasticField = ["ngram_tokenizer" => $elasticDocument["innovation_common_name"]];
        $elasticDocument = array_merge($elasticDocument, $elasticField);

        //Population based on results from elasticsearch queries


        /*$params = [
            'index' => 'rtb_innovations',
            'body'  => [
                "size" => 1
            ]
        ];*/

        //region
        $elasticResponses = array();
        foreach ($elasticDocument["locations_of_implementation"] as $singleLocation){
            $params = [
                'index' => 'dev_db_clarisa_countries',
                'body'  => [
                    "query" => [
                        "match" => [
                            "name" => [
                                "query" => "'".$singleLocation."'"
                            ]
                        ]
                    ]
                ]
            ];


            $response = $client->search($params);
            $response = $response->asObject();
            $regionArray = array();
            foreach ($response->hits->hits as $singleCountry)
            {
                array_push($regionArray, $singleCountry->_source->regionDTO->name);
            }

            $elasticResponses = array_merge($elasticResponses, $regionArray);
        }
        $elasticField = ["region" => $elasticResponses];
        $elasticDocument = array_merge($elasticDocument, $elasticField);

        //impact_areas
        $elasticResponses = array();
        foreach ($elasticDocument["CGIAR_impact_targets"] as $singleImpact){
            $params = [
                'index' => 'dev_db_clarisa_impact_areas',
                'body'  => [
                    "query" => [
                        "match_phrase" => [
                            "description" => "'".$singleImpact."'"
                        ]
                    ]
                ]
            ];

            $response = $client->search($params);
            $response = $response->asObject();
            if($response->hits->hits != null)
            {
                array_push($elasticResponses, ($response->hits->hits[0])->_source->name);
            }
        }
        $elasticField = ["impact_areas" => $elasticResponses];
        $elasticDocument = array_merge($elasticDocument, $elasticField);



        //All sdg fields, splitting the targets is required to get the proper string for the elasticsearch query
        $elasticResponses = array();
        $elasticResponsesUI = array();
        foreach ($elasticDocument["SDG_targets"] as $singleTarget) {
            $explodedTarget = explode(" - ", $singleTarget);
            $params = [
                'index' => 'dev_db_clarisa_sdg_targets',
                'body'  => [
                    "query" => [
                        "match_phrase" => [
                            "sdgTarget" => [
                                "query" => "'".$explodedTarget[1]."'"
                            ]
                        ]
                    ]
                ]
            ];

            $response = $client->search($params);
            $response = $response->asObject();
            $elasticTargetData = $response->hits->hits;
            if($elasticTargetData != null)
            {
                $transformedValue = ["fullName" => $elasticTargetData[0]->_source->sdg->fullName, "shortName" => $elasticTargetData[0]->_source->sdg->shortName];
                array_push($elasticResponses, $transformedValue);
                $transformedValue = ["sdg_unsd_code" => $elasticTargetData[0]->_source->sdg->usndCode, "sdg_target" => $elasticTargetData[0]->_source->sdgTarget, "sdg_short_name" => $elasticTargetData[0]->_source->sdg->shortName, "sdg_target_code" => $elasticTargetData[0]->_source->sdgTargetCode, "sdg_full_name" => $elasticTargetData[0]->_source->sdg->fullName];
                array_push($elasticResponsesUI, $transformedValue);
            }
        }
        //SDG
        $elasticField = ["SDG" => $elasticResponses];
        $elasticDocument = array_merge($elasticDocument, $elasticField);
        //sdg_target_ui
        $elasticField = ["sdg_target_ui" => $elasticResponsesUI];
        $elasticDocument = array_merge($elasticDocument, $elasticField);


        //Find and delete previous version
        $params = [
            'index' => 'rtb_innovations',
            'body'  => [
                "query" => [
                    "match" => [
                        "innovation_uuid" => $uuidTransformed
                    ]
                ]
            ]
        ];
        $response = $client->search($params);
        $documentInElastic = $response->asObject()->hits->hits;
        if(empty($documentInElastic))
        {
            Log::info("Innovation does not exist in Elasticsearch", $documentInElastic);
        }
        else{
            Log::info("Found the innovation in Elasticsearch", $documentInElastic);
            $params = [
                'index' => 'rtb_innovations',
                'id'    => $documentInElastic[0]->_id
            ];
            $response = $client->delete($params);
            Log::info("Deleted the innovation", [$response]);
        }


        $params = [
            'index' => 'rtb_innovations',
            'body'  => $elasticDocument
        ];
        $response = $client->index($params);
        Log::info("Innovation published to elasticsearch", [$response]);


        return response()->json(["result" => "ok", "elasticDoco" => $elasticDocument,"response" => $response->asObject()], 200);

        //return response()->json(["result" => "ok", "elasticDoco" => $elasticDocument]);
    }

    public function migrateToMongo()
    {

        //TODO: This is a note, all fields valid:true
        ini_set('max_execution_time', 1500);

        //Fields that follow the standard case, copying the value
        $mapping_index = array("1.1" => ["innovation_common_name", "true"],       //Missing 1.7 & 1.8 1.13, requires transformation
            "1.2" => ["long_innovation_description", "true"],
            "1.3" => ["business_category", "true"],
            "1.4" => ["administrative_scale_of_innovations", "true"],
            "1.5" => ["related_keywords", "true"],
            "1.6" => ["innovation_url", "true"],
            "1.9" => ["technical_fields", "true"],
            "1.10" => ["innovation_type_old", "false"],
            "1.11" => ["innovation_type_new", "true"],
            "1.12" => ["gov_type_of_solution", "true"],
            "2.1" => ["CGIAR_action_areas_name", "true"],                         //Extra SDG fields that require population. Missing 2.6 & 2.7 require transformation
            "2.2" => ["value_added", "true"],
            "2.3" => ["main_advantages", "true"],
            "2.4" => ["main_disadvantages", "true"],
            "2.5" => ["problem_to_be_solved", "true"],
            "2.8" => ["initiative_defined_outcome", "true"],
            "2.9" => ["environmental_benefits", "true"],
            "3.1" => ["locations_of_implementation", "true"],                     //Missing 3.2 & 3.3 requires transformation
            "3.4" => ["applied_evidence_locations", "false"],
            "3.5" => ["impact_evidence_locations", "false"],
            "4.2" => ["technology_appraisal", "false"],                            //Missing 4.1 & 4.3 requires heavy transformation
            "4.4" => ["documentation", "false"],
            "5.1" => ["patent_member_type", "false"],                              //Missing 5.4 requires transformation
            "5.2" => ["patent_number", "false"],
            "5.3" => ["patent_office", "false"],
            "6.2" => ["intervention_total_budget", "false"],                       //Missing 6.1 & 6.3 requires transformation
            "6.4" => ["challenge_statement", "false"],
            "6.5" => ["objective_statement", "false"],
            "6.6" => ["long_intervention_description", "false"],
            "7.1" => ["technology_dev_project_summary", "false"],
            "7.2" => ["investment_sought_type", "false"],
            "7.3" => ["investment_sought", "false"],
            "8.1" => ["technology_dev_stage", "false"],
            "8.2" => ["technology_dev_project_summary", "false"],
            "8.3" => ["innovation_readiness_levels_of_component", "false"],
            "8.4" => ["innovation_use_levels_of_components", "false"],
            "8.5" => ["scaling_readiness_level", "false"],
            "8.6" => ["scaling_readiness_score", "false"],
            "9.1" => ["innovation_users", "false"],                                //Missing 9.4 & 9.5 & 9.6
            "9.2" => ["innovation_beneficiaries", "false"],
            "9.3" => ["innovation_sponsors", "true"]);

        $specialCaseMappingIndex = array("1.7" => ["innovation_image", "false"],   //Missing 6.1, splits in 2 fields
            "1.8" => ["innovation_image_component", "false"],
            "1.13" => ["mel_ids_of_related_innovations", "true"],
            "2.6" => ["sdg_target_ui", "false"],
            "2.7" => ["impact_areas", "false"],
            "3.2" => ["work_start_date", "false"],
            "3.3" => ["work_end_date", "false"],
            "4.1" => ["innovation_reference_materials", "false"],
            "4.3" => ["technology_appraisal_image", "false"],
            "5.4" => ["patent_know_how_info", "false"],
            "6.1" => ["intervention_full_name", "false"],
            "6.3" => ["intervention_team_members", "false"],
            "9.4" => ["key_innovation_partners", "true"],
            "9.5" => ["key_demand_partners", "false"],
            "9.6" => ["key_scaling_partners", "true"]);

        //Elasticsearch client
        $client = ClientBuilder::create()
            ->setHosts([env('INNOVATIONS_RTB_ES','')])
            ->build();

        $params = [
            'index' => 'rtb_innovations',
            'body'  => [
                "query" => [
                    "match_all" => new StdClass
                ],
                "size" => 100
            ]
        ];

        //Get all the elastic docos
        $response = $client->search($params);
        //Mongo Migration for each one

        foreach ($response->asObject()->hits->hits as $singleElasticDoco)
        {
            $elasticInnovationData = $singleElasticDoco->_source;
            $formData = array();
            foreach ($mapping_index as $key => $data)
            {
                $fieldName = $data[0];
                $formField = ["id" => $key, "value"=> $elasticInnovationData->$fieldName, "mandatory" => $data[1], "valid" => "true"];
                array_push($formData, $formField);
            }


            $formDataSpecial = array();
            foreach ($specialCaseMappingIndex as $key => $data)
            {
                switch ($key)
                {
                    case "1.7":
                        $fieldName = $data[0];
                        $transformedValue = ["type" => "image", "title" => "", "name" => $elasticInnovationData->$fieldName];
                        $formField = ["id" => $key, "value"=> [$transformedValue], "mandatory" => $data[1], "valid" => "true"];
                        array_push($formData, $formField);
                        break;
                    case "1.8":
                        $fieldName = $data[0];
                        $explodedImages = explode(" ;; ",$elasticInnovationData->$fieldName);
                        $imageObjectsArray = array();
                        foreach ($explodedImages as $singleImage)
                        {
                            $transformedValue = ["type" => "image", "title" => "", "name" => $singleImage];
                            array_push($imageObjectsArray, $transformedValue);
                        }
                        $formField = ["id" => $key, "value"=> $imageObjectsArray, "mandatory" => $data[1], "valid" => "true"];
                        array_push($formData, $formField);
                        break;
                    case "1.13":
                        $fieldName = $data[0];
                        $relatedInnovationArray = array();
                        foreach ($elasticInnovationData->$fieldName as $single_mel_id)
                        {
                            if($single_mel_id != null || empty($single_mel_id) == 0){
                                //Get innovation from elastic based on mel id
                                $params = [
                                    'index' => 'rtb_innovations',
                                    'body'  => [
                                        "query" => [
                                            "match" => [
                                                "mel_id_single" => $single_mel_id
                                            ]
                                        ]
                                    ]
                                ];
                                Log::info("I GOT THIS FAR", [$single_mel_id]);
                                $singleInnovation = $client->search($params);
                                $usefulData = $singleInnovation->asObject()->hits->hits[0]->_source;
                                $uuid = $usefulData->innovation_uuid;
                                $transformedUUID = "INNOV-".substr($uuid, 0, 8)."-".substr($uuid, 8, 4)."-".substr($uuid, 12, 4)."-".substr($uuid, 16, 4)."-".substr($uuid, 20, 12);
                                $transformedValue = ["innovation_id" => $transformedUUID, "name" => $usefulData->innovation_common_name];
                                array_push($relatedInnovationArray, $transformedValue);
                            }

                        }
                        $formField = ["id" => $key, "value"=> $relatedInnovationArray, "mandatory" => $data[1], "valid" => "true"];
                        array_push($formData, $formField);
                        break;
                    case "2.6":
                        $fieldName = $data[0];
                        $targetsArray = array();
                        foreach ($elasticInnovationData->$fieldName as $singleSdgTarget)
                        {
                            if(array_key_exists($singleSdgTarget->sdg_full_name, $targetsArray))
                            {
                                $newTarget = $targetsArray[$singleSdgTarget->sdg_full_name];
                                array_push($newTarget, ["id" => uniqid('', true), "value" =>$singleSdgTarget->sdg_target_code." - ".$singleSdgTarget->sdg_target]);
                                $targetsArray[$singleSdgTarget->sdg_full_name] = $newTarget;
                            }
                            else{
                                $targetsArray[$singleSdgTarget->sdg_full_name] = [["id" => uniqid('', true), "value" =>$singleSdgTarget->sdg_target_code." - ".$singleSdgTarget->sdg_target]];
                            }
                        }
                        $transformedValue = array();
                        foreach ($targetsArray as $sdgKey => $item)
                        {
                            array_push($transformedValue, ["id" => uniqid('', true), "value" => $item, "title" => $sdgKey]);
                        }
                        $formField = ["id" => $key, "value"=> $transformedValue, "mandatory" => $data[1], "valid" => "true"];
                        array_push($formData, $formField);
                        break;
                    case "2.7":
                        $fieldName = $data[0];
                        $count = 0;
                        $targetsArray = array();
                        foreach ($elasticInnovationData->$fieldName as $impactTitle)
                        {
                            if($impactTitle != null || empty($impactTitle) == 0)
                            {
                                $targetValue = $elasticInnovationData->CGIAR_impact_targets[$count];
                                if(array_key_exists($impactTitle, $targetsArray))
                                {
                                    $newTarget = $targetsArray[$impactTitle];
                                    array_push($newTarget, ["id" => uniqid('', true), "value" => $targetValue]);
                                    $targetsArray[$impactTitle] = $newTarget;
                                }
                                else{
                                    $targetsArray[$impactTitle] = [["id" => uniqid('', true), "value" => $targetValue]];
                                }
                            }
                            $count++;
                        }
                        $transformedValue = array();
                        foreach ($targetsArray as $impactKey => $item)
                        {
                            array_push($transformedValue, ["id" => uniqid('', true), "value" => $item, "title" => $impactKey]);
                        }
                        $formField = ["id" => $key, "value"=> $transformedValue, "mandatory" => $data[1], "valid" => "true"];
                        array_push($formData, $formField);
                        break;
                    case "3.2":
                    case "3.3":
                        //Cast date to string
                        $fieldName = $data[0];
                        $formField = ["id" => $key, "value"=> (string)($elasticInnovationData->$fieldName[0]), "mandatory" => $data[1], "valid" => "true"];
                        array_push($formData, $formField);
                        break;
                    case "4.1":
                        $fieldName = $data[0];
                        $referencesArray = array();
                        foreach ($elasticInnovationData->$fieldName as $singleReference)
                        {
                            $explodedReferences = explode("##",$singleReference);
                            Log::info("these are the exploded elements", $explodedReferences);
                            if(sizeof($explodedReferences, 0) == 1)
                            {
                                $referenceType = "other";
                                $resource = "";
                            }
                            else
                            {
                                if (str_contains($explodedReferences[1], ".jpeg") || str_contains($explodedReferences[1], ".png"))
                                {
                                    $referenceType = "image";
                                }
                                elseif (str_contains($explodedReferences[1], "http"))
                                {
                                    $referenceType = "url";
                                }
                                else{
                                    $referenceType = "file";
                                }
                                $resource = $explodedReferences[1];
                            }
                            $transformedValue = ["type" => $referenceType, "title" => $explodedReferences[0], "name" => $resource];
                            array_push($referencesArray, $transformedValue);
                        }
                        $formField = ["id" => $key, "value"=> $referencesArray, "mandatory" => $data[1], "valid" => "true"];
                        array_push($formData, $formField);
                        break;
                    case "4.3":
                        //Need to construct an object
                        $fieldName = $data[0];
                        $transformedValue = ["type" => "image", "title" => "", "name" => $elasticInnovationData->$fieldName];
                        $formField = ["id" => $key, "value"=> $transformedValue, "mandatory" => $data[1], "valid" => "true"];
                        array_push($formData, $formField);
                        break;
                    case "5.4":
                        $fieldName = $data[0];
                        $patentValue = $elasticInnovationData->$fieldName;
                        if($patentValue != null || empty($patentValue) == 0)
                        {
                            if (str_contains($patentValue, ".jpeg") || str_contains($patentValue, ".png"))
                            {
                                $referenceType = "image";
                            }
                            elseif (str_contains($patentValue, "http"))
                            {
                                $referenceType = "url";
                            }
                            else{
                                $referenceType = "file";
                            }
                            $transformedValue = ["type" => $referenceType, "title" => "", "name" => $patentValue];
                        }
                        else{
                            $transformedValue = array();
                        }
                        $formField = ["id" => $key, "value"=> $transformedValue, "mandatory" => $data[1], "valid" => "true"];
                        array_push($formData, $formField);
                        break;
                    case "6.1":
                        $fieldName = $data[0];
                        $acronymsArray = array();
                        foreach ($elasticInnovationData->$fieldName as $interventionFull)
                        {
                            if($interventionFull != null || empty($interventionFull) == 0)
                            {
                                $explodedNames = explode("(", $interventionFull);
                                $interventionName = $explodedNames[0];
                                $explodedNames = explode(")", $explodedNames[1]);
                                $interventionAcronym = $explodedNames[0];
                                $transformedValue = ["id" => uniqid('', true), "value" => [$interventionAcronym, $interventionName]];
                                array_push($acronymsArray, $transformedValue);
                            }
                        }
                        $formField = ["id" => $key, "value"=> $acronymsArray, "mandatory" => $data[1], "valid" => "true"];
                        array_push($formData, $formField);
                        break;
                    case "6.3":
                        $fieldName = $data[0];
                        $usersArray = array();
                        foreach($elasticInnovationData->$fieldName as $singleMember)
                        {
                            $transformedValue = ["id" => uniqid('', true), "value" => [["name" => $singleMember, "photo" => ""]]];
                            array_push($usersArray, $transformedValue);
                        }
                        $formField = ["id" => $key, "value"=> $usersArray, "mandatory" => $data[1], "valid" => "true"];
                        array_push($formData, $formField);
                        break;
                    case "9.4":
                    case "9.5":
                    case "9.6":
                        $fieldName = $data[0];
                        $keysArray = array();
                        foreach($elasticInnovationData->$fieldName as $singleKey)
                        {
                            if($singleKey != null || empty($singleKey) == 0)
                            {
                                $transformedValue = ["id" => uniqid('', true), "value" => $singleKey];
                                array_push($keysArray, $transformedValue);
                            }
                        }
                        $formField = ["id" => $key, "value"=> $keysArray, "mandatory" => $data[1], "valid" => "true"];
                        array_push($formData, $formField);
                        break;
                    default:
                        $fieldName = $data[0];
                        $formField = ["id" => $key, "value"=> $elasticInnovationData->$fieldName, "mandatory" => $data[1], "valid" => "true"];
                        array_push($formDataSpecial, $formField);
                        break;
                }
            }

            $innovation = new Innovation;
            //Innovation data, mongo metadata
            //innovId , "innovation_uuid"
            $uuid = $elasticInnovationData->innovation_uuid;
            $transformedUUID = "INNOV-".substr($uuid, 0, 8)."-".substr($uuid, 8, 4)."-".substr($uuid, 12, 4)."-".substr($uuid, 16, 4)."-".substr($uuid, 20, 12);
            $innovation->innovId = $transformedUUID;
            //userIds, user_id
            $transformedIdsArray = array();
            foreach ($elasticInnovationData->user_id as $singleId)
            {
                array_push($transformedIdsArray, (string)$singleId);
            }
            $innovation->userIds = $transformedIdsArray;
            //status
            $innovation->status = "PUBLISHED";
            //version
            $innovation->version = 1;
            //HDL
            $innovation->persistId = [$elasticInnovationData->HDL];
            //deleted
            $innovation->deleted = false;
            //reviewers
            $innovation->reviewers = [];
            //scalingReadinessExpert
            $innovation->scalingReadinessExpert = [];
            //comments
            $innovation->comments = "";
            $currentTime = round(microtime(true) * 1000);
            //createdAt
            $innovation->createdAt = $currentTime;
            //updatedAt
            $innovation->updatedAt = $currentTime;
            //assignedAt
            $innovation->assignedAt = "";
            $innovation->formData = $formData;

            //Save innovation and Log
            $innovation->save();
            Log::info('Adding new innovation with id: ', [$innovation->innovId]);

        }



        return response()->json(["result" => "ok"], 200);

    }
}
