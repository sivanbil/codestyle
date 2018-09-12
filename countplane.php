<?php
// interval序列
$plane_time_list = [[1, 10], [2, 3], [4, 6], [5, 9],[4, 4]];
echo compute_plane_nums_meanwhile($plane_time_list, TRUE);

function compute_plane_nums_meanwhile($plane_time_list = [], $is_off_first=TRUE)
{
    if(!is_array($plane_time_list) || !$plane_time_list) {
        return 0;
    }
    $start = [];
   
    $end = [];
  
    $meanwhile_nums = 0;
   
    foreach($plane_time_list as $plane_time_info) 
    {
        $start[] = $plane_time_info[0];
        $end[] = $plane_time_info[1];
    }
   
    $min_start = min($start);

    $max_end = max($end);
    
    $bitbuket = [];
    for($i=$min_start; $i<=$max_end;$i++) {
        $bitbuket[$i] = 0;
    }
    
    foreach($start as $start_key => $time_item) 
    {
        $bitbuket[$time_item]++;
        $bitbuket[$end[$start_key]]--;
       
    }
  
    $log = [];
    foreach($bitbuket as $num) 
    {
        $meanwhile_nums+=$num;
        $log[] = $meanwhile_nums;
    }
    return max($log);
}
