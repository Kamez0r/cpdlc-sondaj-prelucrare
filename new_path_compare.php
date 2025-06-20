<?php

function get_new_ratings($datasets)
{
    $result = [];
    foreach($datasets as $k => $ds)
    {
        $result[$k] = get_new_ratings_from_one_dataset($ds);
    }
    return $result;
}

function get_new_ratings_from_one_dataset($ds)
{
    $final = [];
    $final["name"] = $ds["name"];
    $final["description"] = $ds["description"];
    $final["content"] = [];

    foreach($ds["content"] as $submition)
    {
        $new_submition = [];
        $new_submition["meta"] = $submition["meta"];
        $new_task_list = [];
        foreach($submition["task_list"] as $task)
        {
            $new_task = get_new_ratings_for_task($task);
            $new_task_list[] = $new_task;
        }
        $new_submition["task_list"] = $new_task_list;


        $final["content"][] = $new_submition;
    }

    return $final;
}

function median_of_sorted($list)
{
    if(count($list) == 0)
    {
        return -1;
    }
    else if(count($list) % 2 == 1)
    {
        return $list[floor(count($list) / 2)];
    }
    else {
        $a = $list[count($list)/2];
        $b = $list[(count($list)/2)-1];
        return ($a + $b) / 2;
    }
}

function average_percintile_of_sorted($list, $eliminated)
{
    if(count($list) == 0)
    {
        return -1;
    }
    if(count($list)<= 2 * $eliminated)
    {
        $eliminated = floor((count($list) - 1) / 2);
        // if($eliminated < 0) $eliminated = 0;
        return array_sum($list) / count(value: $list);
    }

    $new_list = array_splice($list, $eliminated, -$eliminated);

    return array_sum($new_list) / count($new_list);

}

function get_new_ratings_for_task($task)
{  
    $new_task = $task;

    if(count($task["submitted_line"]) > 0)
    {
        // marja start_dest: orig:100 / punem:150
        $d_start = euclidean($task["start"], $task["submitted_line"][0]);
        $d_dest = euclidean($task["destination"], $task["submitted_line"][array_key_last($task["submitted_line"])]);
        $samples_inc = [];
        foreach([50, 100, 500, 1000] as $delta_t)
        {
            if(!is_Array($task["submitted_line"]) || !count($task["submitted_line"]))
            {
                $d_start = -1;
                $d_dest = -1;
                $samples_inc[$delta_t] = [
                    "d_avg" => -1,
                    "d_med" => -1,
                    "d_avg_inc" => [],
                    // "d_samples" => [-1]
                ];
                continue;
            }

            $samples = sampleDistances($task["intended_line"], $task["submitted_line"], $delta_t);
            $distances = $samples;
            sort($distances);

            $d_avg_inc = [];
            foreach([1,2,3,4,5] as $avg_inc)
            {
                $d_avg_inc[$avg_inc] = average_percintile_of_sorted($distances, $avg_inc);
            }

            $samples_inc[$delta_t] = [
                "d_avg" => array_sum($distances) / (count($distances) ? count($distances) : -1),
                "d_med" => median_of_sorted($distances),
                "d_avg_inc" => $d_avg_inc,
                // "d_samples" => $samples,
                // "sampppppp" => $distances
            ];
        }

        $new_task["ratings"] = [
            "d_start" => $d_start,
            "d_dest" => $d_dest,
            "samples_inc" => $samples_inc,
        ];
    }
    else {
        $new_task["ratings"] = [
            "d_start" => -1,
            "d_dest" => -1,
            "samples_inc" => []
        ];

        foreach([50,100,500,1000] as $sr)
        {
            $new_task["ratings"]["samples_inc"]["d_avg"] = -1;
            $new_task["ratings"]["samples_inc"]["d_med"] = -1;
            
            // foreach([1,2,3,4,5] as $inc)
            // {
            //     $new_task["ratings"]["d_avg_"]
            // }
        }
    }
    unset($new_task["intended_line"]);
    unset($new_task["submitted_line"]);
    return $new_task;
}


//=============================== Distance functions

function euclidean(array $p1, array $p2): float {
    return hypot($p2[0] - $p1[0], $p2[1] - $p1[1]);
}

function lineLength(array $line): float {
    $length = 0.0;
    for ($i = 0; $i < count($line) - 1; $i++) {
        $length += euclidean($line[$i], $line[$i + 1]);
    }
    return $length;
}

function pointAtDistance(array $line, float $t): array {
    if ($t < 0) {
        throw new InvalidArgumentException("Distance t must be non-negative");
    }

    $accumulated = 0.0;
    for ($i = 0; $i < count($line) - 1; $i++) {
        $p1 = $line[$i];
        $p2 = $line[$i + 1];
        $segmentLength = euclidean($p1, $p2);

        if ($accumulated + $segmentLength >= $t) {
            $ratio = ($t - $accumulated) / $segmentLength;
            $x = $p1[0] + $ratio * ($p2[0] - $p1[0]);
            $y = $p1[1] + $ratio * ($p2[1] - $p1[1]);
            return [$x, $y];
        }

        $accumulated += $segmentLength;
    }

    // If we reach the end exactly
    return end($line);
}

function pointSegmentDistance(array $p, array $a, array $b): float {
    $ax = $a[0]; $ay = $a[1];
    $bx = $b[0]; $by = $b[1];
    $px = $p[0]; $py = $p[1];

    $dx = $bx - $ax;
    $dy = $by - $ay;

    if ($dx == 0 && $dy == 0) {
        return euclidean($p, $a);
    }

    $t = (($px - $ax) * $dx + ($py - $ay) * $dy) / ($dx * $dx + $dy * $dy);
    $t = max(0, min(1, $t));

    $closestX = $ax + $t * $dx;
    $closestY = $ay + $t * $dy;

    return euclidean($p, [$closestX, $closestY]);
}

function distanceToLine(array $line, array $point): float {
    $minDistance = INF;
    for ($i = 0; $i < count($line) - 1; $i++) {
        $d = pointSegmentDistance($point, $line[$i], $line[$i + 1]);
        if ($d < $minDistance) {
            $minDistance = $d;
        }
    }
    if($minDistance == INF) return -1;
    return $minDistance;
}

function sampleDistances(array $intendedLine, array $submittedLine, float $deltaT): array {
    if ($deltaT <= 0) {
        throw new InvalidArgumentException("deltaT must be positive.");
    }

    $totalLength = lineLength($intendedLine);
    $result = [];

    for ($t = 0.0; $t < $totalLength; $t += $deltaT) {
        $samplePoint = pointAtDistance($intendedLine, $t);
        $distance = distanceToLine($submittedLine, $samplePoint);
        $result[] = $distance;
    }

    // Optional: Add final point at the exact end
    if ($t < $totalLength + 1e-6) {
        $samplePoint = pointAtDistance($intendedLine, $totalLength);
        $distance = distanceToLine($submittedLine, $samplePoint);
        $result[] = $distance;
    }

    return $result;
}
