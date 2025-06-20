<?php

function get_statistics($mid_data)
{


    return [
        // cate sondaje au fost incarcate in DB
        "db_submit_count" => count($mid_data["db"]),

        // cate taskuri au fost completate in DB
        "db_task_submit_count" => get_db_task_submit_count($mid_data),

        // cate taskuri au fost completate in DB unice (indiferent de dificultate)
        "db_unique_tasks" => get_db_unique_tasks($mid_data),

        // cate taskuri au fost completate in DB unice (discriminat de dificultate)
        "db_unique_tasks_dif" => get_db_unique_tasks_dif($mid_data),

        // cate formulare au fost trimise pe google forms
        "form_submit_count" => count($mid_data["csv"]),

        // cate formulare unice au fost potrivite cu intrari unice din db
        "matched_submit_count" => get_matched_submit_count($mid_data),
    ];
}

function get_matched_submit_codes($mid_data)
{
    $codes = [];
    foreach($mid_data["csv"] as $row)
    {
        $codes[$row["code"]] = false;
    }
    
    foreach($mid_data["db"] as $row)
    {
        if(array_key_exists($row["code"], $codes))
        {
            $codes[$row["code"]] = true;
        }
    }

    $result = [];
    foreach($codes as $k => $v)
    {
        if($v) $result[] = $k;
    }
    return $result;
}

function get_matched_submit_count($mid_data)
{
    return count(get_matched_submit_codes($mid_data));
}

function get_db_task_submit_count($mid_data)
{
    $total_tasks = 0;
    foreach($mid_data["db"] as $row)
    {
        $total_tasks += count($row["content"]);
    }
    return $total_tasks;
}

function get_db_unique_tasks($mid_data)
{
    $tasks = [];
    foreach($mid_data["db"] as $row)
    {
        foreach($row["content"] as $task)
        {
            $tasks[$task["id"]] = true;
        }
    }
    return count($tasks);
}

function get_db_unique_tasks_dif($mid_data)
{
    $tasks = [];
    foreach($mid_data["db"] as $row)
    {
        foreach($row["content"] as $task)
        {
            $key = $task["result"]["dif"] . "-" . $task["id"];
            $tasks[$key] = true;
        }
    }
    return count($tasks);
}