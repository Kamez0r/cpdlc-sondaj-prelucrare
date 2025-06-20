<?php
require_once("raw_stats.php"); // Functii generare statistici pe datele de intrare
require_once("dataset_proc.php"); // Functii procesare date

require_once("path_compare.php"); // Functii de evaluare a apropiperii liniilor trasate
require_once("new_path_compare.php");
require_once("stats_proc.php"); // Functii generare statistici pe datele procesate



function array_to_csv_download($array, $filename = "export.csv", $delimiter=";") {
    // open raw memory as file so no temp files needed, you might run out of memory though
    $f = fopen('php://memory', 'w'); 
    // loop over the input array
    foreach ($array as $line) { 
        // generate csv lines from the inner arrays
        fputcsv($f, $line, $delimiter); 
    }
    // reset the file pointer to the start of the file
    fseek($f, 0);
    // tell the browser it's going to be a csv file
    header('Content-Type: text/csv');
    // tell the browser we want to save it instead of displaying it
    header('Content-Disposition: attachment; filename="'.$filename.'";');
    // make php send the generated csv lines to the browser
    fpassthru($f);
}

function extract_times($input)
{
    $times = [];

    foreach($input as $dif => $data)
    {
        if(isset($data["sample_size"]))
        {
            $times[] = $data["sample_size"];
            $times[] = $data["avg"];
            $times[] = $data["avg99"];
            $times[] = $data["med"];
        }
        else {
            // No data available
            $times[] = -1;
            $times[] = -1;
            $times[] = -1;
            $times[] = -1;
        }
    }

    return $times;
}

function times_to_array($times)
{
    $rows = [];

    $table_head = [
        "DS",
        "GROUP_BY",
        "GROUP"
    ];
    foreach(["1", "2", "3", "_overall"] as $dif)
    {
        $table_head[] = $dif . "_" . "sample_size";
        $table_head[] = $dif . "_" . "avg";
        $table_head[] = $dif . "_" . "avg99";
        $table_head[] = $dif . "_" . "med";   
    }

    $rows[] = $table_head;

    foreach($times as $ds_name => $ds)
    {
        foreach($ds as $group_by => $groups)
        {
            foreach($groups as $group => $values)
            {
                $times = extract_times($values["times"]);
                
                $row = [$ds_name, $group_by, $group];

                $row = array_merge($row, $times);
    
                $rows[] = $row;
            }
        }
    }

    return $rows;
}

function get_ratings_group_by_occupation($datasets)
{
    $result = [];

    foreach($datasets as $ds_name => $ds)
    {
        // if($ds_name != "DS_MATCH_DB_CSV") continue;
        $temp_ds_data = [];
        $ds_data = [];

        foreach($ds["content"] as $submition)
        {
            foreach($submition["meta"]["csv_occupation"] as $occupation_name)
            {
                if(!isset($temp_ds_data[$occupation_name])) $temp_ds_data[$occupation_name] = [];

                foreach($submition["task_list"] as $task)
                {
                    $new_task = [];
                    $new_task["id"] = $task["task_id"];
                    $new_task["dif"] = $task["task_difficulty"];
                    $new_task["ratings"] = $task["ratings"];
                    $temp_ds_data[$occupation_name][] = $new_task;
                }
            }
        }

        $result[$ds_name] = $temp_ds_data;
        continue;


    }

    return $result;
}

function map_accumulator_to_occupation($inp)
{
    // echo "<pre>";
    // print_r($inp);
    // echo "</pre>";
    // exit();
    $result = [
        "d_start" => 0,
        "d_dest" => 0,
        "samples_inc" => []
    ];

    $sample_sizes = [50, 100, 500, 1000];
    $increments = [1, 2, 3, 4, 5];
    $task_count = count($inp);

    // Init
    foreach ($sample_sizes as $sr) {
        $result["samples_inc"][$sr] = [
            "d_avg" => 0,
            "d_med" => 0,
            "d_avg_inc" => []
        ];
        foreach ($increments as $inc) {
            $result["samples_inc"][$sr]["d_avg_inc"][$inc] = 0;
        }
    }

    // Accumulate
    foreach ($inp as $rating) {
        if (isset($rating["d_start"])) {
            $result["d_start"] += $rating["d_start"];
        } else {
            $result["d_start"] += -1;
        }

        if (isset($rating["d_dest"])) {
            $result["d_dest"] += $rating["d_dest"];
        } else {
            $result["d_dest"] += -1;
        }

        if (isset($rating["samples_inc"]) && is_array($rating["samples_inc"])) {
            foreach ($sample_sizes as $sr) {
                $sample_data = $rating["samples_inc"][$sr] ?? [];

                $result["samples_inc"][$sr]["d_avg"] += $sample_data["d_avg"] ?? -1;
                $result["samples_inc"][$sr]["d_med"] += $sample_data["d_med"] ?? -1;

                $avg_inc = $sample_data["d_avg_inc"] ?? [];
                foreach ($increments as $inc) {
                    $val = $avg_inc[$inc] ?? -1;
                    $result["samples_inc"][$sr]["d_avg_inc"][$inc] += $val;
                }
            }
        } else {
            // missing samples_inc → pad with -1
            foreach ($sample_sizes as $sr) {
                $result["samples_inc"][$sr]["d_avg"] += -1;
                $result["samples_inc"][$sr]["d_med"] += -1;
                foreach ($increments as $inc) {
                    $result["samples_inc"][$sr]["d_avg_inc"][$inc] += -1;
                }
            }
        }
    }

    // Average
    $result["d_start"] /= $task_count;
    $result["d_dest"] /= $task_count;

    foreach ($sample_sizes as $sr) {
        $result["samples_inc"][$sr]["d_avg"] /= $task_count;
        $result["samples_inc"][$sr]["d_med"] /= $task_count;
        foreach ($increments as $inc) {
            $result["samples_inc"][$sr]["d_avg_inc"][$inc] /= $task_count;
        }
    }

    return $result;
}
function get_ratings_group_by_dif($ratings)
{
    $result = [];

    foreach($ratings as $ds_name => $ds)
    {
        $ds_data = [];

        foreach($ds as $occupation_name => $task_list)
        {
            $accumulator = [
                "1" => [],
                "2" => [],
                "3" => [],
                "_overall" => [],
            ];

            foreach($task_list as $task)
            {
                $accumulator[$task["dif"]][] = $task["ratings"];
                $accumulator["_overall"][] = $task["ratings"];
            }

            // $occupation = map_accumulator_to_occupation($accumulator);
            $occupation = [];

            foreach($accumulator as $dif_name => $ratings)
            {
                $occupation[$dif_name] = map_accumulator_to_occupation($ratings);
            }

            $ds_data[$occupation_name] = $occupation;
        }

        $result[$ds_name] = $ds_data;
    }
    return $result;
}


function ratings_to_array($datasets)
{
    $result = get_ratings_group_by_occupation($datasets);
    $result = get_ratings_group_by_dif($result);

    $map = [];
    // listification but as key=>value still...
    foreach($result as $ds_name => $ds)
    {
        foreach($ds as $occupation_name => $dif)
        {
            foreach($dif as $dif_name => $rating)
            {
                $new_rating = [];
                $new_rating["d_start"] = $rating["d_start"];
                $new_rating["d_dest"] = $rating["d_dest"];

                foreach($rating["samples_inc"] as $sr => $deep_rating)
                {
                    $new_rating["SR".$sr."_d_avg"] = $deep_rating["d_avg"];
                    $new_rating["SR".$sr."_d_med"] = $deep_rating["d_med"];

                    foreach($deep_rating["d_avg_inc"] as $inc => $inc_rating)
                    {
                        $new_rating["SR".$sr."INC".$inc."_avg"] = $inc_rating;
                    }
                }
                // Here new rating is ready as plain map
                $result[$ds_name][$occupation_name][$dif_name] = $new_rating;
            }
        }
    }

    $rows = ["DS", "OCCUPATION", "DIF"];
    // Push table head
    foreach($result as $ds_name => $ds)
    {
        foreach($ds as $occupation_name => $dif)
        {
            foreach($dif as $dif_name => $rating)
            {
                foreach(array_keys($rating) as $rating_name)
                {
                    $rows[] = $rating_name;
                }
                break;
            }
            break;
        }
        break;
    }
    $rows = [$rows];


    // key to rows
    foreach($result as $ds_name => $ds)
    {
        foreach($ds as $occupation_name => $dif)
        {
            foreach($dif as $dif_name => $rating)
            {
                $row = [$ds_name, $occupation_name, $dif_name];
                foreach($rating as $rating_name => $rating_value)
                {
                    $row[] = $rating_value;
                }

                $rows[] = $row;
            }
        }
    }

    return $rows;
}