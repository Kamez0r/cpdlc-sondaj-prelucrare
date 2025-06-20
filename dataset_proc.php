<?php

const UNMATCHED_MARKER = "!netrimis!";

function get_datasets($mid_data)
{
    $ds_all_db = get_ds_all_db($mid_data);
    $ds_unique_db = filter_ds_all_db__to__ds_unique_db($ds_all_db);

    $ds_match_db_csv = filter_ds_unique__to__final($ds_unique_db);

    return [
        
        // Toate intrarile unice potrivite 
        "DS_MATCH_DB_CSV" => [
            "name" => "DS Final Potrivit",
            "description" => "Dataset cu intrarile unice in baza de date care au avut parte de formular trimis pe Google Forms.",
            "content" => $ds_match_db_csv
        ],

        // Toate intrarile unice in DB
        "DS_UNIQUE_DB" => [
            "name" => "DS DB Unic",
            "description" => "Dataset cu intarile unice in baza de date. Unde nu exista potrivire cu formular, a fost completata ocupatia cu '".UNMATCHED_MARKER."'",
            "content" => $ds_unique_db
        ],

        // Toate intrarile in DB
        "DS_ALL_DB" => [
            "name" => "DS DB Complet",
            "description" => "Dataset cu toate intarile in baza de date. Unde nu exista potrivire cu formular, a fost completata ocupatia cu '".UNMATCHED_MARKER."'",
            "content" => $ds_all_db
        ],
    ];
}

function get_ds_all_db($mid_data)
{
    $result = [];

    foreach($mid_data["db"] as $row)
    {
        $new_row = [
            "meta" => [
                "db_id"             => $row["id"],
                "code"              => $row["code"],
                "db_ts"             => date("Y-m-d H:i:s", strtotime($row["ts"]) + 3 * 60 * 60) ,
                "db_ts_unix"        => strtotime($row["ts"]) + 3 * 60 * 60,
                "csv_ts"            => $row["ts"],
                "csv_ts_unix"       => strtotime($row["ts"]),
                "delta_ts"          => 0,
                "delta_ts_pretty"   => date("i:s", 0), // MM:SS
                "csv_submit"        => false,
                "csv_occupation"    => [UNMATCHED_MARKER],
            ],
            "task_list" => []
        ];
        
            
        foreach($row["content"] as $task)
        {
            list($minutes, $seconds) = explode(":", $task["result"]["time"]);
            $totalSeconds = ($minutes * 60) + $seconds;

            $task = [
                "img"               => $task["img"],
                "task_id"           => $task["id"],
                "task_difficulty"   => $task["result"]["dif"],

                "task_prompt_1"     => $task["line1"],
                "task_prompt_2"     => $task["line2"],
                "task_prompt_3"     => $task["line3"],
                "task_prompt_4"     => $task["line4"],
                
                "start"             => $task["start"],
                "destination"       => $task["dest"],

                "task_time_pretty"  => $task["result"]["time"],
                "task_time_seconds" => $totalSeconds,

                "intended_line"     => $task["positions"],
                "submitted_line"    => $task["result"]["points"],
            ];
            $new_row["task_list"][] = $task;
        }

        $result[] = $new_row;
    }

    foreach($result as $k => $row)
    {
        foreach($mid_data["csv"] as $csv_row)
        {
            if($csv_row["code"] == $row["meta"]["code"])
            {
                $result[$k]["meta"]["csv_ts"] = $csv_row["ts"];
                $result[$k]["meta"]["csv_ts_unix"] = strtotime($csv_row["ts"]);
                $result[$k]["meta"]["delta_ts"] = $result[$k]["meta"]["csv_ts_unix"] -  $result[$k]["meta"]["db_ts_unix"];
                $result[$k]["meta"]["delta_ts_pretty"] = date("i:s",  $result[$k]["meta"]["delta_ts"]);
                $result[$k]["meta"]["csv_submit"] = true;
                $result[$k]["meta"]["csv_occupation"] = $csv_row["occupation"];
            }
        }
    }

    return $result;
}


function filter_ds_all_db__to__ds_unique_db($ds)
{
    $codes = [];
    $result = [];
    foreach($ds as $row)
    {
        if(!in_array($row["meta"]["code"], $codes))
        {
            $codes[] = $row["meta"]["code"];
            $result[] = $row;
        }
    }

    return $result;
}

function filter_ds_unique__to__final($ds)
{
    $result = [];

    foreach($ds as $row)
    {
        if($row["meta"]["csv_submit"])
        {
            $result[] = $row;
        }
    }

    return $result;
}