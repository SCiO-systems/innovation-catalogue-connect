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
            "2.5" => "problem_to_be_solved",
            "2.8" => "initiative_defined_outcome",
            "2.9" => "environmental_benefits",
            "3.1" => "locations_of_implementation",                     //Missing 3.2 & 3.3 requires transformation
            "3.4" => "applied_evidence_locations",
            "3.5" => "impact_evidence_solutions",
            "4.2" => "technology_appraisal",                            //Missing 4.1 & 4.3 requires heavy transformation
            "4.4" => "documentation",
            "5.1" => "patent_member_type",                              //Missing 5.4 requires transformation
            "5.2" => "patent_number",
            "5.3" => "patent_office",
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
            "8.5" => "scaling_readiness_leve",
            "8.6" => "scaling_readiness_score",
            "9.1" => "innovation_users",                                //Missing 9.4 & 9.5 & 9.6
            "9.2" => "innovation_beneficiaries",
            "9.3" => "innovation_sponsors");


        $specialCaseMappingIndex = array("1.7" => "innovation_image",   //Missing 6.1, splits in 2 fields
            "1.8" => "innovation_image_component",
            "1.13" => "uuids_of_related_innovations",
            "2.6" => "SDG_targets",
            "2.7" => "CGIAR_impact_targets",
            "3.2" => "work_start_date",
            "3.3" => "work_end_date",
            "4.1" => "innovation_reference_materials",
            "4.3" => "technology_appraisal_image",
            "5.4" => "patent_know_how_info",
            "6.1" => "intervention_full_name",
            "6.3" => "intervention_team_members",
            "9.4" => "key_innovation_partners",
            "9.5" => "key_demand_partners",
            "9.6" => "key_scaling_partners");


        //Elasticsearch client
        $client = ClientBuilder::create()
            ->setHosts(['dev.elasticsearch.scio.services:9200'])
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
                        $elasticField = [$specialCaseMappingIndex[$indexingKey] => $singleField["value"][0]["name"]];
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
                        $explodedDate = explode(" ",$singleField["value"]);
                        $elasticField = [$specialCaseMappingIndex[$indexingKey] => $explodedDate[3]];
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
        $elasticField = ["innovation_image_id" => ""];
        $elasticDocument = array_merge($elasticDocument, $elasticField);
        //"HDL"
        $elasticField = ["HDL" => $innovation["persistId"]];
        $elasticDocument = array_merge($elasticDocument, $elasticField);
        //"mel_id_single"
        $elasticField = ["mel_id_single" => ""];
        $elasticDocument = array_merge($elasticDocument, $elasticField);
        //"mel_ids_of_related_innovations"
        $elasticField = ["mel_ids_of_related_innovations" => [" "]];
        $elasticDocument = array_merge($elasticDocument, $elasticField);
        //"last_updated"
        $d = new DateTime( '@'. $innovation["updatedAt"]/1000 );
        $transformedValue =  $d->format("d/m/Y");
        $elasticField = ["last_updated" => $transformedValue];
        $elasticDocument = array_merge($elasticDocument, $elasticField);
        //"mel_id_version_single"
        $elasticField = ["mel_id_version_single" => null];
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
            Log::info("CHECK THIS BOIIIII", [$singleLocation]);
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
            Log::info("CHECK THIS BOIIIII", [$singleImpact]);
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


        $params = [
            'index' => 'rtb_innovations',
            'body'  => $elasticDocument
        ];
        $response = $client->index($params);


        return response()->json(["result" => "ok", "elasticDoco" => $elasticDocument ,"response" => $response->asObject()], 200);

    }
}
