<?php

function get_datasets_ratings($datasets)
{
    $result = [];
    foreach($datasets as $k => $ds)
    {
        $result[$k] = get_ratings_for_one_dataset($ds);
    }
    return $result;
}

function get_ratings_for_one_dataset($ds)
{
    $result = [];
    foreach($ds["content"] as $row)
    {
        $new_row["meta"] = $row["meta"];
        $new_row["task_list"] = [];

        foreach($row["task_list"] as $task)
        {
            $new_row["task_list"] = [
                "rating" => get_rating(
                    $task["intended_line"],
                    $task["submitted_line"]
                ),
                "task" => $task
            ];
        } 
        $result[] = $new_row;
    }
    return $result;
}

function get_datasets_times($datasets)
{
    $result = [];
    foreach($datasets as $k => $ds)
    {
        $result[$k] = get_times_for_one_dataset($ds);
    }
    return $result;

}

function get_times_for_one_dataset($ds)
{
    $result = [];

    $result["by_code"] = get_times_by_code($ds);
    $result["by_occupation"] = get_times_by_occupation($result["by_code"]);
    $result["by_task_id"] = [];//get_times_by_task_id($ds);

    return $result;
}


function times_table_to_avg_med($times)
{

    foreach ($times as $k => $v)
    {
        sort($v);
        if(count($v) >= 1)
        {
            $avg99 = $v;
            if(count($v) > 2)
            {
                $avg99 = array_splice($avg99, 1, -1);
            }

            $times[$k] = [
                "samples" => $v,
                // "samples99" => $avg99,
                "sample_size" => count($v),
                "avg" => array_sum($v) / count($v),
                "avg99" => array_sum($avg99) / count($avg99),
                "med" => count($v) % 2 ? ($v[floor((count($v) - 1) / 2)]) : ($v[floor((count($v) - 1) / 2) + 1]),
                // TODO Median for event set???
            ];
        }
        else {
            $times[$k] = "No Data Available";
        }
    }


    return $times;
}

function get_times_by_code($ds)
{
    $result = [];
    
    foreach($ds["content"] as $code_entry)
    {
        $times = [
            1 => [],
            2 => [],
            3 => [],
            "_overall" => [],
        ];

        foreach($code_entry["task_list"] as $task)
        {
            $times[$task["task_difficulty"]][] = $task["task_time_seconds"];
            $times["_overall"][] = $task["task_time_seconds"];
        }
        
        $result[$code_entry["meta"]["code"]]["times"] = times_table_to_avg_med($times);
        $result[$code_entry["meta"]["code"]]["occupation"] = $code_entry["meta"]["csv_occupation"];
    }

    return $result;
}

function get_times_by_occupation($ds)
{
    $result = [];
    $times = [];

    foreach($ds as $code)
    {
        foreach($code["occupation"] as $occupation)
        {
            $times[$occupation][] = $code["times"];
        }
    }

    $result = [];
    foreach($times as $occupation => $v)
    {
        $new_row = [
            1 => [],
            2 => [],
            3 => [],
            "_overall" => [],
        ];

        foreach($v as $time_entry)
        {
            foreach([1,2,3,"_overall"] as $dif_level)
            {
                if(isset($time_entry[$dif_level]["samples"]))
                    $new_row[$dif_level] = array_merge($new_row[$dif_level], $time_entry[$dif_level]["samples"]);
            }
        }

        $result[$occupation] = [];
        $result[$occupation]["times"] = times_table_to_avg_med($new_row);
    }

    return $result;
}