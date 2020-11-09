<?php
if (!defined('DP_BASE_DIR')) {
	die('You should not access this file directly.');
}

$do_report = dPgetParam($_POST, 'do_report', 0);
$log_start_date = dPgetCleanParam($_POST, 'log_start_date', 0);
$log_end_date = dPgetCleanParam($_POST, 'log_end_date', 0);
$log_all = (int)dPgetParam($_POST['log_all'], 0);
$group_by_unit = dPgetCleanParam($_POST['group_by_unit'],'day');

// create Date objects from the datetime fields
$start_date = intval($log_start_date) ? new CDate($log_start_date) : new CDate();
$end_date = intval($log_end_date) ? new CDate($log_end_date) : new CDate();

if (!$log_start_date) {
	$start_date->subtractSpan(new Date_Span('14,0,0,0'));
}
$end_date->setTime(23, 59, 59);
?>


<form name="editFrm" action="index.php?m=projects&a=reports" method="post">
<input type="hidden" name="project_id" value="<?php echo $project_id;?>" />
<input type="hidden" name="report_type" value="<?php echo $report_type;?>" />

<table cellspacing="0" cellpadding="4" border="0" width="100%" class="std">


<tr>
	<td align="right" nowrap="nowrap"><?php echo $AppUI->_('For period');?>:</td>
	<td nowrap="nowrap">
		<input type="date" name="log_start_date" value="<?php echo $start_date->format(FMT_DATE_HTML5);?>" class="text dpDateField">
	</td>
	<td align="right" nowrap="nowrap"><?php echo $AppUI->_('to');?></td>
	<td nowrap="nowrap">
		<input type="date" name="log_end_date" value="<?php echo $end_date ? $end_date->format(FMT_DATE_HTML5) : '';?>" class="text dpDateField">
	</td>

	<td nowrap="nowrap">
		<input type="checkbox" name="log_all" id="log_all" value="1" <?php if ($log_all) echo 'checked'; ?> />
		<label for="log_all"><?php echo $AppUI->_('Log All');?></label>
	</td>

	<td align="right" width="50%" nowrap="nowrap">
		<input class="button" type="submit" name="do_report" value="<?php echo $AppUI->_('submit');?>" />
	</td>
</tr>

</table>
</form>

<?php
if ($do_report) {
	
	// Let's figure out which users we have
	$q = new DBQuery;
	$q->addTable('users', 'u');
	$q->addQuery('u.user_id, u.user_username, contact_first_name, contact_last_name');
	$q->addJoin('contacts', 'c', 'u.user_contact = contact_id');
    $user_list = $q->loadHashList('user_id');
    $q->clear();
    $list_report_kpi = array();
    foreach ($user_list as $ul_id) {
		$q = new DBQuery;
		#total_task
		$uname = $ul_id['user_username'];
		$ulist = array();
        $idu = intval($ul_id['user_id']);
        $q->addTable('user_tasks', 'ut');
        $q->addQuery('COUNT(distinct(t.task_id)) as total_task');
        $q->addJoin('tasks', 't', 'ut.task_id = t.task_id');
		$q->addWhere('ut.user_id = ' . $idu);
        $q->addWhere('(t.task_start_date BETWEEN "' . $start_date->format(FMT_DATETIME_MYSQL) 
				 . '" AND "' . $end_date->format(FMT_DATETIME_MYSQL) 
				 . '" OR t.task_end_date BETWEEN "' . $start_date->format(FMT_DATETIME_MYSQL) 
				 . '" AND "' . $end_date->format(FMT_DATETIME_MYSQL) 
				 . '" OR (t.task_start_date >= "' . $start_date->format(FMT_DATETIME_MYSQL) 
				 . '" AND task_end_date <= "' . $end_date->format(FMT_DATETIME_MYSQL) . '"))');
        $r = $q->loadHashList('total_task');
		$q->clear();
		$ulist[] =array_pop($r);


		#total_task_complete
		$q = new DBQuery;
		$q->addQuery("COUNT(DISTINCT(tt.task_log_task)) as total_task_complete
		FROM dotp_task_log tt
		INNER JOIN
			(SELECT L.task_log_task, MAX(task_log_id) AS task_log_id
			FROM dotp_task_log L inner join dotp_tasks T on L.task_log_task = T.task_id
			where L.task_log_date <= T.task_end_date
			GROUP BY L.task_log_task) groupedtt 
		ON tt.task_log_task = groupedtt.task_log_task 
		AND tt.task_log_id = groupedtt.task_log_id
		WHERE 
		tt.task_log_task IN (select distinct ut.task_id 
		from dotp_user_tasks ut 
		left join dotp_tasks t 
		on ut.task_id = t.task_id 
		where ut.user_id = " .$idu."
		and ((t.task_start_date 
		BETWEEN '" . $start_date->format(FMT_DATETIME_MYSQL) ."'  
		AND '".$end_date->format(FMT_DATETIME_MYSQL)."') OR (t.task_end_date BETWEEN '". $start_date->format(FMT_DATETIME_MYSQL)."' AND '".$end_date->format(FMT_DATETIME_MYSQL)."'))) AND tt.task_log_percent_complete = 100");
		
        $r = $q->loadHashList('total_task_complete');
		$ulist[] = array_pop($r);
		$q->clear();
		$q = new DBQuery;

		#total_task_overtime
		// $q->addQuery("COUNT(DISTINCT(tt.task_log_id)) as total_task_overtime
		// FROM dotp_task_log tt
		// INNER JOIN
		// 	( SELECT L.task_log_task, MAX(task_log_id) AS task_log_id
		// 	FROM dotp_task_log L inner join dotp_tasks T on L.task_log_task = T.task_id
		// 	where L.task_log_date <= T.task_end_date
		// 	GROUP BY L.task_log_task) groupedtt 
		// ON tt.task_log_task = groupedtt.task_log_task 
		// AND tt.task_log_id = groupedtt.task_log_id
		// WHERE 
		// tt.task_log_task IN (select distinct ut.task_id 
		// from dotp_user_tasks ut 
		// left join dotp_tasks t 
		// on ut.task_id = t.task_id

		// where t.task_end_date < NOW()
		// AND ut.user_id = " .$idu.
		// " AND ((t.task_start_date 
		// BETWEEN '" . $start_date->format(FMT_DATETIME_MYSQL) ."'  
		// AND '".$end_date->format(FMT_DATETIME_MYSQL)."') OR (t.task_end_date BETWEEN '". $start_date->format(FMT_DATETIME_MYSQL)."' AND '".$end_date->format(FMT_DATETIME_MYSQL)."'))) AND tt.task_log_percent_complete < 100");
		
		// $r = $q->loadHashList('total_task_overtime');
		// $ulist[] = array_pop($r);
		// $q->clear();
		// $q = new DBQuery;
		$q->addTable('tasks', 't');
		$q->addQuery('COUNT(DISTINCT(t.task_id)) as total_task_overtime');
		$q->addWhere('t.task_percent_complete < 100');
		$q->addWhere('t.task_end_date < NOW()');
		
		$q ->addWhere("t.task_id IN (SELECT distinct ut.task_id from dotp_user_tasks ut left join dotp_tasks t on ut.task_id = t.task_id where ut.user_id = " . $idu . ' AND (t.task_start_date BETWEEN  "' . $start_date->format(FMT_DATETIME_MYSQL) 
		. '" AND "' . $end_date->format(FMT_DATETIME_MYSQL) 
		. '" OR t.task_end_date BETWEEN "' . $start_date->format(FMT_DATETIME_MYSQL) 
		. '" AND "' . $end_date->format(FMT_DATETIME_MYSQL) 
		. '" OR (t.task_start_date >= "' . $start_date->format(FMT_DATETIME_MYSQL) 
		. '" AND (t.task_start_date <= "' . $end_date->format(FMT_DATETIME_MYSQL) . '"))))');
		$r = $q->loadHashList('total_task_overtime');
		$ulist[] = array_pop($r);
		$q->clear();
		$q = new DBQuery;
		#total_task_ip
		$q->addTable('tasks', 't');
		$q->addQuery('COUNT(DISTINCT(t.task_id)) as total_task_in_processing');
		$q->addWhere('t.task_percent_complete < 100');
		$q->addWhere('t.task_end_date >= NOW()');
		
		$q ->addWhere("t.task_id IN (SELECT distinct ut.task_id from dotp_user_tasks ut left join dotp_tasks t on ut.task_id = t.task_id where ut.user_id = " . $idu . ' AND (t.task_start_date BETWEEN  "' . $start_date->format(FMT_DATETIME_MYSQL) 
		. '" AND "' . $end_date->format(FMT_DATETIME_MYSQL) 
		. '" OR t.task_end_date BETWEEN "' . $start_date->format(FMT_DATETIME_MYSQL) 
		. '" AND "' . $end_date->format(FMT_DATETIME_MYSQL) 
		. '" OR (t.task_start_date >= "' . $start_date->format(FMT_DATETIME_MYSQL) 
		. '" AND (t.task_start_date <= "' . $end_date->format(FMT_DATETIME_MYSQL) . '"))))');
		$r = $q->loadHashList('total_task_in_processing');
		$ulist[] = array_pop($r);
		$q->clear();
		$q = new DBQuery;

		#total_ERROR
		$q->addTable('tasks', 't');
		$q->addQuery('COUNT(DISTINCT(t.task_id)) as total_ERROR');
		$q->addWhere('t.task_type = 4');

		$q->addWhere('t.task_id IN (SELECT distinct ut.task_id 
					from dotp_user_tasks ut 
					left join dotp_tasks t 
					on ut.task_id = t.task_id 
					where ut.user_id ='.$idu.' 
					and (t.task_start_date BETWEEN "' . $start_date->format(FMT_DATETIME_MYSQL) 
					. '" AND "' . $end_date->format(FMT_DATETIME_MYSQL) 
					. '" OR t.task_end_date BETWEEN "' . $start_date->format(FMT_DATETIME_MYSQL) 
					. '" AND "' . $end_date->format(FMT_DATETIME_MYSQL) 
					. '" OR (t.task_start_date >= "' . $start_date->format(FMT_DATETIME_MYSQL) 
					. '" AND (t.task_start_date <= "' . $end_date->format(FMT_DATETIME_MYSQL) . '"))))');
		$r = $q->loadHashList('total_ERROR');
		$ulist[] = array_pop($r);
		$q->clear();
		$q = new DBQuery;

		#total_work_hours
		$q->addTable('tasks', 't');
		$q->addQuery('ROUND(SUM(t.task_duration * t.task_duration_type),0) as total_work_hours');
		$q->addWhere('t.task_id IN (SELECT distinct ut.task_id 
					from dotp_user_tasks ut 
					left join dotp_tasks t 
					on ut.task_id = t.task_id 
					where ut.user_id ='.$idu.' 
					and (t.task_start_date BETWEEN "' . $start_date->format(FMT_DATETIME_MYSQL) 
					. '" AND "' . $end_date->format(FMT_DATETIME_MYSQL) 
					. '" OR t.task_end_date BETWEEN "' . $start_date->format(FMT_DATETIME_MYSQL) 
					. '" AND "' . $end_date->format(FMT_DATETIME_MYSQL) 
					. '" OR (t.task_start_date >= "' . $start_date->format(FMT_DATETIME_MYSQL) 
					. '" AND (t.task_start_date <= "' . $end_date->format(FMT_DATETIME_MYSQL) . '"))))');
		$r = $q->loadHashList('total_work_hours');
		$ulist[] = array_pop($r);
		$q->clear();
		#total_release_hours
		########################
		$q = new DBQuery;
		$q->addQuery('p.project_id, p.project_name, t.*, 
			CONCAT_WS(\' \',contact_first_name,contact_last_name) AS creator,
			if (bc.billingcode_name is null, \'\', bc.billingcode_name) as billingcode_name');
		$q->addTable('task_log', 't');
		$q->leftJoin('billingcode','bc', 'bc.billingcode_id = t.task_log_costcode');
		$q->leftJoin('users', 'u', 'user_id = task_log_creator');
		$q->leftJoin('contacts', 'c', 'u.user_contact = contact_id');
		$q->innerJoin('tasks', 'tsk', 't.task_log_task = tsk.task_id');
		$q->leftJoin('projects', 'p', 'p.project_id = task_project');
		// if ($project_id != 0) {
			// $q->addWhere('task_project = ' . (int)$project_id);
		// }
		// if (!$log_all) {
		$q->addWhere('task_log_date >= \''.$start_date->format(FMT_DATETIME_MYSQL).'\'');
		$q->addWhere('task_log_date <= \''.$end_date->format(FMT_DATETIME_MYSQL)."'");
		// }
		// if ($log_ignore) {
		$q->addWhere('task_log_hours > 0');
		// }
		// if ($log_userfilter) {
		$q->addWhere('task_log_creator = ' . (int)$idu);

		$arrr = array();
		$arr2 = array(); 
		$logs = $q->loadList();
		foreach ($logs as $log) {
			$hours += $log['task_log_hours'];
		};
		if ($hours != null){
			$arrr['total_release_hours'] = $hours;
		}
			else{
				$arrr['total_release_hours'] = 0;
			 }
		$arr2[] = $arrr;
		$ulist[] = array_pop($arr2);
		$ulist[] = $ul_id;
		
		$list_report_kpi[$uname] = $ulist;
		$q->clear();
		$hours = 0.0;
		########################

		// $q->addQuery('ROUND(SUM(tl.task_log_hours),0) as total_release_hours');
		// $q->addTable('task_log', 'tl');
		// $q->addWhere('tl.task_log_task IN (SELECT distinct ut.task_id 
		// 			from dotp_user_tasks ut 
		// 			left join dotp_tasks t 
		// 			on ut.task_id = t.task_id 
		// 			where ut.user_id ='.$idu.' 
		// 			and (t.task_start_date BETWEEN "' . $start_date->format(FMT_DATETIME_MYSQL) 
		// 			. '" AND "' . $end_date->format(FMT_DATETIME_MYSQL) 
		// 			. '" OR t.task_end_date BETWEEN "' . $start_date->format(FMT_DATETIME_MYSQL) 
		// 			. '" AND "' . $end_date->format(FMT_DATETIME_MYSQL) 
		// 			. '" OR (t.task_start_date >= "' . $start_date->format(FMT_DATETIME_MYSQL) 
		// 			. '" AND (t.task_start_date <= "' . $end_date->format(FMT_DATETIME_MYSQL) . '"))))');
		// $r = $q->loadHashList('total_release_hours');
		// $ulist[] = array_pop($r);
		// $ulist[] = $ul_id;
		// $q->clear();
		// $list_report_kpi[$uname] = $ulist;
    };
?>

<table cellspacing="1" cellpadding="8" border="0" class="tbl">
	<tr>
		<th colspan='2'><?php echo $AppUI->_('User');?></th>
		<th><?php echo $AppUI->_('Tổng số task được giao'); ?></th>
		<th><?php echo $AppUI->_('Tổng số task hoàn thành'); ?></th>
		<th><?php echo $AppUI->_('Tổng số task trễ deadline'); ?></th>
		<th><?php echo $AppUI->_('Tồng số task chưa tới deadline'); ?></th>
		<th><?php echo $AppUI->_('Tổng số lỗi lặp lại'); ?></th>
		<th><?php echo $AppUI->_('Tổng số giờ được giao'); ?></th>
		<th><?php echo $AppUI->_('Tổng số giờ thực tế'); ?></th>
	</tr>

<?php
	if (count($user_list)) {
		function check_values($var){
		if (empty($var)){
			$var = 0;
			return $var;
		}
		else{
			return $var;
		}
	}
//TODO: Split times for which more than one users were working...	
		foreach ($list_report_kpi as $lrk){
			$total_task = check_values($lrk[0]['total_task']);
			$total_task_complete = check_values($lrk[1]['total_task_complete']);
			$total_task_overtime = check_values($lrk[2]['total_task_overtime']);
			$total_task_in_processing = check_values($lrk[3]['total_task_in_processing']);
			$total_ERROR = check_values($lrk[4]['total_ERROR']);
			$total_work_hours = check_values($lrk[5]['total_work_hours']);
			$total_release_hours = check_values($lrk[6]['total_release_hours']);
			$user = $lrk[7];		
				?>
				<tr>
					<td><?php echo '('.$user['user_username'].') </td><td> '.$user['contact_first_name'].' '.$user['contact_last_name']; ?></td>
					<td align='right'><?php echo $total_task; ?> </td>
					<td align='right'><?php echo $total_task_complete; ?> </td>
					<td align='right'><?php echo $total_task_overtime; ?> </td>
					<td align='right'><?php echo $total_task_in_processing; ?> </td>
					<td align='right'><?php echo $total_ERROR; ?> </td>
					<td align='right'><?php echo $total_work_hours; ?> </td>
					<td align='right'><?php echo $total_release_hours; ?> </td>
				</tr>
				<?php
		}
	

	} else {
		?>
		<tr>
		    <td><p><?php echo $AppUI->_('There are no tasks that fulfill selected filters');?></p></td>
		</tr>
		<?php
	}
}
?>
</table>