<?php
/**
 * Path-comparison toolbox   (vanilla PHP – no external libs)
 *
 * All functions are pure – they take ordinary PHP arrays like
 *     [[x0, y0], [x1, y1], …]
 * and return numeric results in the same coordinate units.
 *
 * ------------------------------------------------------------
 * Main entry point:
 *   get_rating( $lineA, $lineB )  → associative array
 * ------------------------------------------------------------
 */

/*------------------------------------------------------------
 |  Basic geometry helpers
 *-----------------------------------------------------------*/

/** Euclidean distance between two points. */
function dist(array $a, array $b): float {
    return hypot($a[0] - $b[0], $a[1] - $b[1]);
}

/** Bounding-box diagonal length of two polylines, used for normalisation. */
function bboxDiag(array ...$lines): float {
    $xs = $ys = [];
    foreach ($lines as $L) {
        foreach ($L as $p) { $xs[] = $p[0]; $ys[] = $p[1]; }
    }
    return hypot(max($xs) - min($xs), max($ys) - min($ys));
}

/*------------------------------------------------------------
 |  1. Resample polyline to N evenly-spaced points
 *-----------------------------------------------------------*/
function resamplePolyline(array $pts, int $N = 100): array {
    if ($N < 2 || count($pts) < 2) return $pts;

    /* segment lengths and total length */
    $segLen = []; $tot = 0;
    for ($i = 0; $i < count($pts) - 1; $i++) {
        $d = dist($pts[$i], $pts[$i+1]);
        $segLen[] = $d; $tot += $d;
    }
    $step = $tot / ($N - 1);
    $out  = [$pts[0]];               // first point fixed

    $seg = 0; $acc = 0;              // current segment & distance walked so far
    for ($k = 1; $k < $N - 1; $k++) {
        $target = $k * $step;
        /* move to segment containing target distance */
        while ($seg < count($segLen)-1 && $acc + $segLen[$seg] < $target) {
            $acc += $segLen[$seg++];
        }
        /* linear interpolation inside current segment */
        $remain = $target - $acc;
        $t      = $segLen[$seg] ? $remain / $segLen[$seg] : 0;
        $a      = $pts[$seg];
        $b      = $pts[$seg + 1];
        $out[]  = [$a[0] + $t*($b[0]-$a[0]), $a[1] + $t*($b[1]-$a[1])];
    }
    $out[] = end($pts);              // last point fixed
    return $out;
}

/*------------------------------------------------------------
 |  2. Point-wise average distance  (shape similarity)
 *-----------------------------------------------------------*/
function averageDistance(array $A, array $B): float {
    $n = min(count($A), count($B));

    // TODO analyize proper return
    if ($n === 0) return -1; // or return 0, or throw, depending on your design


    $sum = 0;
    for ($i = 0; $i < $n; $i++) $sum += dist($A[$i], $B[$i]);
    return $sum / $n;
}

/*------------------------------------------------------------
 |  3. Heading difference  (directionality)
 *-----------------------------------------------------------*/
function averageHeadingDifference(array $A, array $B): float {
    $n = min(count($A), count($B));
    if ($n < 2) return 0;
    $sum = 0;
    for ($i = 0; $i < $n-1; $i++) {
        $v1 = [$A[$i+1][0]-$A[$i][0], $A[$i+1][1]-$A[$i][1]];
        $v2 = [$B[$i+1][0]-$B[$i][0], $B[$i+1][1]-$B[$i][1]];
        $a1 = atan2($v1[1], $v1[0]);
        $a2 = atan2($v2[1], $v2[0]);
        $d  = abs($a1 - $a2);
        if ($d > M_PI) $d = 2*M_PI - $d;          // wrap
        $sum += $d;
    }
    return $sum / ($n - 1);                       // radians
}

/*------------------------------------------------------------
 |  4. Discrete Fréchet distance  (shape + order)
 *-----------------------------------------------------------*/
function discreteFrechet(array $P, array $Q): float {
    $n = count($P);
    $m = count($Q);

    // TODO check...
    if ($n === 0 || $m === 0) return -1; // or throw / return 0

    $c = array_fill(0, $n, array_fill(0, $m, -1));
    for ($i = 0; $i < $n; $i++) {
        for ($j = 0; $j < $m; $j++) {
            if ($i === 0 && $j === 0)
                $c[$i][$j] = dist($P[0], $Q[0]);
            elseif ($i === 0)
                $c[$i][$j] = max($c[$i][$j - 1], dist($P[0], $Q[$j]));
            elseif ($j === 0)
                $c[$i][$j] = max($c[$i - 1][$j], dist($P[$i], $Q[0]));
            else {
                $minPrev = min($c[$i - 1][$j], $c[$i - 1][$j - 1], $c[$i][$j - 1]);
                $c[$i][$j] = max($minPrev, dist($P[$i], $Q[$j]));
            }
        }
    }
    return $c[$n - 1][$m - 1];
}

/*------------------------------------------------------------
 |  5. Hausdorff distance  (worst-case deviation)
 *-----------------------------------------------------------*/
function pointToSegment(array $p, array $a, array $b): float {
    $dx = $b[0]-$a[0]; $dy = $b[1]-$a[1];
    $len2 = $dx*$dx + $dy*$dy;
    if ($len2 == 0) return dist($p, $a);
    $t = (($p[0]-$a[0])*$dx + ($p[1]-$a[1])*$dy) / $len2;
    $t = max(0, min(1, $t));
    return dist($p, [$a[0]+$t*$dx, $a[1]+$t*$dy]);
}
function directedHausdorff(array $P, array $Q): float {
    $max = 0;
    for ($i = 0; $i < count($P); $i++) {
        $best = INF;
        for ($j = 0; $j < count($Q)-1; $j++)
            $best = min($best, pointToSegment($P[$i], $Q[$j], $Q[$j+1]));
        $max = max($max, $best);
    }
    if($max == INF)
        return -1;
    return $max;
}
function hausdorff(array $P, array $Q): float {
    return max(directedHausdorff($P, $Q), directedHausdorff($Q, $P));
}

/*------------------------------------------------------------
 |  6.  Master wrapper – get_rating()
 *-----------------------------------------------------------*/
function get_rating(array $lineA, array $lineB): array {
    /* --- resample to fixed length for pointwise metrics --- */
    $A100 = resamplePolyline($lineA, 100);
    $B100 = resamplePolyline($lineB, 100);

    /* --- compute individual criteria --- */
    $avgPointDist   = averageDistance($A100, $B100);         // px
    $avgHeadingDiff = averageHeadingDifference($A100, $B100);// rad
    $frechet        = discreteFrechet($lineA, $lineB);       // px
    $hausdorff      = hausdorff($lineA, $lineB);             // px

    /* --- optional normalised + blended score --- */
    $diag   = bboxDiag($lineA, $lineB) ?: 1;                 // avoid ÷0
    $shapeN = $frechet  / $diag;                             // 0…1
    $dirN   = $avgHeadingDiff / M_PI;                        // 0…1
    $blend  = 0.7 * $shapeN + 0.3 * $dirN;                   // weighted

    return [
        'avg_point_error_px'   => $avgPointDist,
        'avg_heading_error_rad'=> $avgHeadingDiff,
        'frechet_distance_px'  => $frechet,
        'hausdorff_distance_px'=> $hausdorff,
        'shape_norm_0_1'       => $shapeN,
        'dir_norm_0_1'         => $dirN,
        'blended_score_0_1'    => $blend,
    ];
}
