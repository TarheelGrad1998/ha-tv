<?php

function programToJson($program) {
    $returnProgram = array();    

    $returnProgram["title"] = (string)$program -> title;
    $returnProgram["start"] = utcToTime($program["start"]);
    $returnProgram["end"] = utcToTime($program["stop"]);

    if (strtotime("now") >= strtotime($program["start"])) {
        $returnProgram["pct"] = timePercent($program["start"], $program["stop"]);
    }
    else {
        $returnProgram["start_full"] = date_format(new DateTime($program["start"]), "c");
        $returnProgram["end_full"] = date_format(new DateTime($program["stop"]), "c");
    }

    if (property_exists($program, 'sub-title')) {
        $returnProgram["sub_title"] = (string)$program -> {'sub-title'};
    }
    
    if (property_exists($program, 'desc')) {
        $returnProgram["desc"] = (string)$program -> desc;
    }

    if (property_exists($program, 'category')) {
        $returnProgram["category"] = (string)$program -> category;
    }

    if (property_exists($program, 'icon')) {
        $returnProgram["icon"] = str_replace("http://", "https://", $program -> icon["src"]);
    }

    if (property_exists($program, 'new')) {
        $returnProgram["new"] = true;
    }

    if (property_exists($program, 'credits')) {
        $returnProgram["credits"] = $program -> credits;
    }

    return $returnProgram;
 }

function utcToTime($sDate) {
    if (strlen($sDate) == 0) {
        return "";
    }
    else {
        $date = new DateTime($sDate);
        $date->setTimeZone(new DateTimeZone('America/New_York'));
        return $date->format('g:i A');
    }
}

function timePercent($start, $end) {
    if (strlen($start) == 0 or strlen($end) == 0) {
        return 0;
    }
    else {
        $startDate = strtotime($start);
        $endDate = strtotime($end);
        $currentDate = strtotime("now");

        return round(round(abs($currentDate - $startDate) / 60,0) / round(abs($endDate - $startDate) / 60,0) * 100, 0);
    }
}

$debug = $_GET['debug'];
if ($debug == '') 
    error_reporting(E_ERROR | E_PARSE);

//Get the YouTube TV data file
$youtubeSource = 'downloads/browse.json';
$youtubeRaw = json_decode(file_get_contents($youtubeSource), true);

$idValues = json_decode(file_get_contents('downloads/channel_ids.json'), true);
$aliasValues = json_decode(file_get_contents('downloads/channel_aliases.json'), true);

try {
    //Get the XMLTV data
    $list8080 = new SimpleXMLElement(file_get_contents('downloads/8080.xml'));
    $list8098 = new SimpleXMLElement(file_get_contents('downloads/8098.xml'));
}
catch(Exception $e) {
    $list8080 = new SimpleXMLElement('<?xml version="1.0" encoding="UTF-8"?><tv/>');
    $list8098 = new SimpleXMLElement('<?xml version="1.0" encoding="UTF-8"?><tv/>');
}

$listings["8080"] = $list8080;
$listings["8098"] = $list8098;

$current_date = gmdate("YmdHis0000");

$returnValues = array();

foreach ($youtubeRaw["contents"]["epgRenderer"]["paginationRenderer"]["epgPaginationRenderer"]["contents"] as $channelObj) {    
    $channelName = $channelObj["epgRowRenderer"]["station"]["epgStationRenderer"]["icon"]["accessibility"]["accessibilityData"]["label"];
    $channelValue = $channelObj["epgRowRenderer"]["airings"][0]["epgAiringRenderer"]["navigationEndpoint"]["watchEndpoint"]["videoId"];

    if ($channelValue == "") {
        $channelValue = $channelObj["epgRowRenderer"]["airings"][0]["epgAiringRenderer"]["navigationEndpoint"]["unpluggedPopupEndpoint"]["popupRenderer"]["unpluggedSelectionMenuDialogRenderer"]["items"][0]["unpluggedMenuItemRenderer"]["command"]["watchEndpoint"]["videoId"];
    }

    $channelName = strtoupper(trim($channelName));
    $channelValue = trim($channelValue);

    //Workaround for 2 x PBS NC
    if ($channelValue == "BT4zv9QGxcQ") {
        $channelName = "PBS KIDS";
    }

    $searchIdx = array_search($channelName, array_column($idValues,"name"));
    if ($searchIdx !== false) {
        $channelId = $idValues[$searchIdx]["id"];
        $channelListing = $idValues[$searchIdx]["listing_id"];
        $channelCategory = $idValues[$searchIdx]["category"];

        if ($channelId != null and $channelListing != null) {
            $icon = str_replace("http://", "https://", $listings[$channelListing]->xpath('channel[@id="'.$channelId.'"]')[0] -> icon["src"]);
            $programs = $listings[$channelListing]->xpath('programme[@channel="'.$channelId.'" and translate(@stop, " +", "") > "'.$current_date.'"]');
            $on_now = $programs[0];
            $on_next = $programs[1];
        }
        else {
            $icon = null;
            $on_now = null;
            $on_next = null;
        }
    }

    array_push($returnValues, ["name" => $channelName, "category" => $channelCategory, "code" => $channelValue, "icon" => (string)$icon,                 
                "on_now" => programToJson($on_now), "on_next" => programToJson($on_next),         
                "is_alias" => false]);
}

if (!empty($aliasValues)) {
    foreach ($aliasValues as $row) {
        $channelName = $row["name"];
        $channelAlias = $row["alias"];

        $searchIdx = array_search($channelName, array_column($returnValues, 'name'));
        if ($searchIdx !== false) {
            array_push($returnValues, ["name" => $channelAlias, "code" => $returnValues[$searchIdx]["code"], "is_alias" => true ]);
        }
    }
}

if (empty($returnValues)) {
    print '{"count":0,"details":[]}';
} else {
    printf('{"count":%s,"details":%s}', count($returnValues), json_encode($returnValues));
}


?>
