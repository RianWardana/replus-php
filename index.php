<?php

/**
 * Copyright 2016 LINE Corporation
 *
 * LINE Corporation licenses this file to you under the Apache License,
 * version 2.0 (the "License"); you may not use this file except in compliance
 * with the License. You may obtain a copy of the License at:
 *
 *   https://www.apache.org/licenses/LICENSE-2.0
 *
 * Unless required by applicable law or agreed to in writing, software
 * distributed under the License is distributed on an "AS IS" BASIS, WITHOUT
 * WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied. See the
 * License for the specific language governing permissions and limitations
 * under the License.
 */

require_once('./LINEBotTiny.php');
require_once("./phpMQTT.php");

$channelAccessToken = '';
$channelSecret = '';
$client = new LINEBotTiny($channelAccessToken, $channelSecret);

$mqtt = new phpMQTT("pociremote.com", 1883, "Client".rand());

foreach ($client->parseEvents() as $event) {
    switch ($event['type']) {
        case 'message':
            $message = $event['message'];
            switch ($message['type']) {
                case 'text':
                    $textRaw = $message['text'];
                    $text = str_replace(" ", "%20", $textRaw);
                    $replyText = "Instruction unclear :(";
                    
                    $url = "https://api.wit.ai/message?v=23/05/2017&q=" . $text;
                    $headers = array(
                        "Authorization: Bearer ZGATIUE2ZTAFQ3IMP2YRBER64NFHYPR5"
                    ); 

                    $ch = curl_init();
                    curl_setopt($ch, CURLOPT_URL, $url); 
                    curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1); 
                    curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
                    $serverOutput = curl_exec($ch);
                    curl_close ($ch);

                    $array = json_decode($serverOutput, true);
                    $entities = $array["entities"];
                    
                    $intent = $entities["intent"][0]["value"];
                    $tv_channel = $entities["tv_channel"][0]["value"];
                    $device_type = $entities["device_type"][0]["value"];
                    $on_off = $entities["on_off"][0]["value"];
                    $temperature = $entities["temperature"][0]["value"];
                    $greetings = $entities["greetings"][0]["value"];
                    $location = strtolower($entities["location"][0]["value"]);

                    if ($intent == "lamp_command") {
                        if (!empty($location) && !empty($on_off)) {
                            $replyText = "Lamp in " . $location . " is turned " . $on_off;
                            
                            if ($location == "bedroom") $macId = "66E7";
                            else if ($location == "garage") $macId = "671";  
                            else if ($location == "kitchen") $macId = "0001";
                            else if ($location == "living room") $macId = "0002";
                            else if ($location == "toilet") $macId = "0003";
                            else {
                                $macId = "0000";
                                $replyText = "Sorry I couldn't find the room called '{$location}'...";   
                            } 
                                

                            $topic = $macId . "/fromClient";
                            $topicStatus = $macId . "/toClient";

                            if ($on_off == "on") $lamp_command = "1";
                            else $lamp_command = "0";

                            if ($mqtt->connect()) {
                                $mqtt->publish($topic, $lamp_command, 0);
                                $mqtt->publish($topicStatus, $lamp_command, 0);
                                $mqtt->close();
                            }

                        } else {
                            $replyText = "You want to control a lamp but not specifying on/off or location";
                        }
                    } 

                    else if ($intent == "ac_command") {
                        $replyText = "You want to control AC unit";
                    }

                    else if ($intent == "tv_command") {
                        $replyText = "You want to control a television";
                    }

                    else if ($intent == "command") {
                        $replyText = "You want to control more than one appliance, right?";
                    }

                    else if ($intent == "greeting") {
                        $replyText = "This is a greeting, right? :)";
                    }

                    else {
                        $replyText = "Your intention is not clear..";
                    }

                    $client->replyMessage(array(
                        'replyToken' => $event['replyToken'],
                        'messages' => array(
                            array(
                                'type' => 'text',
                                'text' => $replyText
                            )
                        )
                    ));
                    break;
                default:
                    error_log("Unsupported message type: " . $message['type']);
                    break;
            }
            break;
        default:
            error_log("Unsupported event type: " . $event['type']);
            break;
    }
};
