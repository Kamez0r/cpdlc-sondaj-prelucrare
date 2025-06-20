<?php

require_once("utils.php");

$json_url = "_dummy_data.json";
// $csv_url = "https://docs.google.com/spreadsheets/d/e/2PACX-1vRH66Ov6UaXIgeW4_yWT3fHxvaA2aW6UWrapQ1DZiTz93v_YVYavcd0E3p7V33C0K1sj7ninYRgQXTy/pub?output=csv";
$csv_url = "_dummy_data.csv";

$json = file_get_contents($json_url);


// Check if the file was read successfully
if ($json === false) {
    die('Error reading the JSON file');
}

// Decode the JSON file
$json_data = json_decode($json, true); 

// $mid_json_data = [];
foreach($json_data as $k => $v)
{
    $json_data[$k]["content"] = json_decode($v["content"], true);
}



// Check if the JSON was decoded successfully
if ($json_data === null) {
    die('Error decoding the JSON file');
}


if(!ini_set('default_socket_timeout', 15)) echo "<!-- unable to change socket timeout -->";

if (($handle = fopen($csv_url, "r")) !== FALSE) {
    fgetcsv($handle, 1000, ",");
    while (($data = fgetcsv($handle, 1000, ",")) !== FALSE) {
        $csv_data[] = [
            "ts" => $data[0],
            "occupation" => array_filter(array_map('trim', explode(",", $data[1]))),
            "code" => $data[3]
        ];
    }
    fclose($handle);
}
else
    die("Problem reading csv");

$mid_data = [
    "db" => $json_data,
    "csv" => $csv_data
];



$raw_stats = get_statistics($mid_data);

$datasets = get_datasets($mid_data);

$ratings = get_datasets_ratings($datasets);

$new_ratings = get_new_ratings($datasets);

$times = get_datasets_times($datasets);


if(isset($_REQUEST["csv"]))
{
    $arr = [];
    if($_REQUEST["csv"] == "times")
    {
        $arr = times_to_array($times);
    }
    else if($_REQUEST["csv"] == "ratings")
    {
        $arr = ratings_to_array($new_ratings);
    }
    array_to_csv_download($arr);
}
else {

    // Last Print
    $arr = ratings_to_array($new_ratings);
    echo "<pre>";
    // print_r($new_ratings);
    print_r($arr);
    echo "</pre>";
    
    
    echo "<pre>RawStatistics//" . json_encode($raw_stats) . "</pre>";
    echo "<pre>MidData//" . json_encode($mid_data) . "</pre>";
    echo "<pre>Datasets//" . json_encode($datasets) . "</pre>";
    echo "<pre>Ratings//" . json_encode($ratings) . "</pre>";
    echo "<pre>New Ratings//" . json_encode($new_ratings) . "</pre>";
    echo "<pre>Times//" . json_encode($times) . "</pre>";
    
    
    // var_dump(json_encode($ratings));

}